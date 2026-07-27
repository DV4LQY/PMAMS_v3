<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\DeviceMaintenancePhoto;
use App\Models\DeviceMaintenanceRecord;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    private function permissionRules(): array
    {
        return [
            'permissions_present' => ['nullable', 'boolean'],
            'permissions_changed' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.menus' => ['nullable', 'array'],
            'permissions.menus.*' => [Rule::in(array_keys(User::PERMISSION_MENUS))],
            'permissions.actions' => ['nullable', 'array'],
            'permissions.actions.*' => ['array'],
            'permissions.actions.*.*' => [Rule::in(array_keys(User::PERMISSION_ACTIONS))],
        ];
    }

    private function permissionsFromRequest(Request $request, string $role, ?array $fallback = null): ?array
    {
        // Super Admin is intentionally unrestricted and cannot be accidentally
        // locked out by a custom permission set.
        if ($role === User::ROLE_SUPER_ADMIN) {
            return null;
        }

        // Keep legacy accounts working when an older form is submitted.
        if (! $request->boolean('permissions_present')) {
            return $fallback ?? User::permissionsForRole($role);
        }

        $menuKeys = array_keys(User::PERMISSION_MENUS);
        $actionKeys = array_keys(User::PERMISSION_ACTIONS);
        $menus = array_values(array_intersect((array) $request->input('permissions.menus', []), $menuKeys));
        $actions = [];

        foreach (array_keys(User::PERMISSION_RESOURCES) as $resource) {
            $selected = $request->input("permissions.actions.{$resource}");
            $actions[$resource] = is_array($selected)
                ? array_values(array_intersect($selected, $actionKeys))
                : [];
        }

        return ['menus' => $menus, 'actions' => $actions];
    }

    private function saveRolePermissions(string $role, ?array $permissions, bool $changed): void
    {
        if (! $changed || $role === User::ROLE_SUPER_ADMIN || ! is_array($permissions)) {
            return;
        }

        $profiles = User::allRolePermissions();
        $profiles[$role] = $permissions;
        SystemSetting::putValue(User::ROLE_PERMISSIONS_KEY, json_encode($profiles, JSON_THROW_ON_ERROR));
        User::forgetRolePermissionsCache();
    }

    private function buildSummary(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roleLabel(),
            'permission_menus' => User::permissionsForRole((string) $user->role)['menus'] ?? [],
            'permission_actions' => User::permissionsForRole((string) $user->role)['actions'] ?? [],
        ];
    }

    private function buildCreateSummary(User $user): array
    {
        return $this->buildSummary($user);
    }

    private function buildUpdateSummary(User $user, bool $passwordChanged = false): array
    {
        $summary = $this->buildSummary($user);

        if ($passwordChanged) {
            $summary['password'] = 'New Password';
        }

        return $summary;
    }

    private function buildDeleteSummary(User $user): array
    {
        return $this->buildSummary($user);
    }

    public function index()
    {
        $users = User::orderBy('name')->paginate(15);
        $rolePermissions = User::allRolePermissions();

        return view('admin.users.index', compact('users', 'rolePermissions'));
    }

    public function recycleBin()
    {
        $deletedUsers = User::onlyTrashed()
            ->orderByDesc('deleted_at')
            ->paginate(15, ['*'], 'users_page')
            ->withQueryString();

        $deletedDevices = Device::onlyTrashed()
            ->with('type')
            ->withCount(['assignments', 'maintenanceRecordsIncludingTrashed', 'maintenancePhotos'])
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'devices_page')
            ->withQueryString();

        return view('admin.users.recycle-bin', compact('deletedUsers', 'deletedDevices'));
    }

    public function permanentDelete(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'type' => ['required', Rule::in(['users', 'devices', 'all'])],
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer', 'distinct'],
            'select_all' => ['nullable', 'boolean'],
            'empty' => ['nullable', 'boolean'],
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        if (trim($data['remarks']) === '') {
            return back()->withErrors(['remarks' => 'Remarks are required before permanent deletion.']);
        }

        $type = $data['type'];
        $empty = (bool) ($data['empty'] ?? false);
        $selectAll = (bool) ($data['select_all'] ?? false) || $empty;
        $ids = collect($data['ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();

        if ($type === 'all' && ! $empty) {
            return back()->withErrors(['type' => 'Use Empty Recycle Bin when permanently deleting all record types.']);
        }

        if (! $selectAll && $ids->isEmpty()) {
            return back()->withErrors(['ids' => 'Select at least one recycle-bin record or choose the empty-bin action.']);
        }

        $types = $type === 'all' ? ['users', 'devices'] : [$type];
        $deletedCounts = [];

        DB::transaction(function () use ($types, $selectAll, $ids, $data, &$deletedCounts) {
            foreach ($types as $currentType) {
                $deletedCounts[$currentType] = match ($currentType) {
                    'users' => $this->permanentlyDeleteUsers($selectAll, $ids),
                    'devices' => $this->permanentlyDeleteDevices($selectAll, $ids),
                };
            }
        });

        ActivityLog::record(
            'force_deleted',
            'Permanently deleted recycle-bin records.',
            null,
            ActivityLog::makePayload([
                'types' => $types,
                'counts' => $deletedCounts,
                'remarks' => trim($data['remarks']),
                'empty_bin' => $empty,
            ])
        );

        $total = array_sum($deletedCounts);
        return back()->with('success', "{$total} recycle-bin record(s) permanently deleted.");
    }

    private function permanentlyDeleteUsers(bool $selectAll, $ids): int
    {
        $query = User::onlyTrashed();
        if (! $selectAll) {
            $query->whereIn('id', $ids);
        }

        $users = $query->get();
        foreach ($users as $user) {
            $user->forceDelete();
        }

        return $users->count();
    }

    private function permanentlyDeleteDevices(bool $selectAll, $ids): int
    {
        $query = Device::onlyTrashed();
        if (! $selectAll) {
            $query->whereIn('id', $ids);
        }

        $devices = $query->get();
        foreach ($devices as $device) {
            $records = DeviceMaintenanceRecord::withTrashed()
                ->where('device_id', $device->id)
                ->get();

            foreach ($records as $record) {
                $record->photos()->get()->each(function (DeviceMaintenancePhoto $photo) {
                    if (filled($photo->photo_path)) {
                        Storage::disk('public')->delete($photo->photo_path);
                    }
                    $photo->delete();
                });
                $record->forceDelete();
            }

            DeviceMaintenancePhoto::where('device_id', $device->id)
                ->get()
                ->each(function (DeviceMaintenancePhoto $photo) {
                    if (filled($photo->photo_path)) {
                        Storage::disk('public')->delete($photo->photo_path);
                    }
                    $photo->delete();
                });

            DeviceAssignment::where('device_id', $device->id)->delete();
            if (filled($device->photo_path)) {
                Storage::disk('public')->delete($device->photo_path);
            }
            $device->forceDelete();
        }

        return $devices->count();
    }

    public function store(Request $request)
    {
        $data = $request->validateWithBag('add', array_merge([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->symbols(),
            ],
        ], $this->permissionRules()), [
            'email.unique' => 'This email is already registered.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        if ($data['role'] === User::ROLE_UNIT_HEAD && User::where('role', User::ROLE_UNIT_HEAD)->exists()) {
            return back()
                ->withErrors([
                    'role' => 'A Unit Head account already exists. Please update the existing Unit Head.',
                ], 'add');
        }

        $rolePermissions = $this->permissionsFromRequest($request, $data['role']);
        $this->saveRolePermissions($data['role'], $rolePermissions, $request->boolean('permissions_changed'));

        $newUser = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'password' => Hash::make($data['password']),
            // Permissions are role profiles and apply to every account with
            // this role, rather than being stored on one account.
            'permissions' => null,
        ]);

        ActivityLog::record(
            'created',
            "Created user account \"{$newUser->name}\"",
            $newUser,
            ActivityLog::makePayload(
                $this->buildCreateSummary($newUser)
            )
        );
        return back()->with('success', 'User created.');
    }

    public function update(Request $request, User $user)
    {
        $rules = array_merge([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => [
                'nullable',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->symbols(),
            ],
        ], $this->permissionRules());

        $data = $request->validateWithBag('edit', $rules, [
            'email.unique' => 'This email is already registered.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        // Safety net: an admin can't demote themselves away from admin —
        // avoids accidentally locking every admin out of the system.
        if (
            $user->id === auth()->id()
            && in_array($user->role, [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN, User::ROLE_UNIT_HEAD], true)
            && $data['role'] !== $user->role
        ) {
            return back()->withErrors([
                'role' => 'You cannot change your own protected role.',
            ], 'edit');
        }

        if (
            $data['role'] === User::ROLE_UNIT_HEAD &&
            $user->role !== User::ROLE_UNIT_HEAD &&
            User::where('role', User::ROLE_UNIT_HEAD)->exists()
        ) {
            return back()->withErrors([
                'role' => 'A Unit Head account already exists.',
            ], 'edit');
        }

        $passwordChanged = !empty($data['password']);

        $rolePermissions = $this->permissionsFromRequest(
            $request,
            $data['role'],
            User::permissionsForRole($data['role'])
        );
        $this->saveRolePermissions($data['role'], $rolePermissions, $request->boolean('permissions_changed'));

        $before = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roleLabel(),
            'permission_menus' => User::permissionsForRole((string) $user->role)['menus'] ?? [],
            'permission_actions' => User::permissionsForRole((string) $user->role)['actions'] ?? [],
        ];

        if ($passwordChanged) {
            $before['password'] = null;
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $data['role'];
        // Role profiles are shared by every account with this role.
        $user->permissions = null;

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        ActivityLog::record(
            'updated',
            "Updated user account \"{$user->name}\"",
            $user,
            ActivityLog::makePayload(
                $this->buildUpdateSummary($user, $passwordChanged),
                ActivityLog::buildChanges(
                    $before,
                    array_merge(
                        [
                            'name' => $user->name,
                            'email' => $user->email,
                            'role' => $user->roleLabel(),
            'permission_menus' => User::permissionsForRole((string) $user->role)['menus'] ?? [],
            'permission_actions' => User::permissionsForRole((string) $user->role)['actions'] ?? [],
                        ],
                        $passwordChanged
                        ? ['password' => 'New Password']
                        : []
                    )
                )
            )
        );

        return back()->with('success', 'User updated.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account while logged in as it.');
        }

        $summary = $this->buildDeleteSummary($user);

        $name = $user->name;

        ActivityLog::record(
            'deleted',
            "Deleted user account \"{$name}\"",
            $user,
            ActivityLog::makePayload($summary)
        );

        $user->delete();

        return back()
            ->with('success', 'User moved to the recycle bin.')
            ->with('recycle_bin_notice', 'The deleted user is retained in the recycle bin and has not been permanently erased.');
    }

    public function restore(int $user)
    {
        $deletedUser = User::onlyTrashed()->findOrFail($user);
        $deletedUser->restore();

        ActivityLog::record(
            'restored',
            "Restored user account \"{$deletedUser->name}\"",
            $deletedUser,
            ActivityLog::makePayload($this->buildSummary($deletedUser))
        );

        return back()->with('success', 'User restored.');
    }

    public function forceDestroy(int $user)
    {
        $deletedUser = User::onlyTrashed()->findOrFail($user);

        $summary = $this->buildDeleteSummary($deletedUser);
        $name = $deletedUser->name;
        ActivityLog::record(
            'force_deleted',
            "Permanently deleted user account \"{$name}\"",
            null,
            ActivityLog::makePayload($summary)
        );

        $deletedUser->forceDelete();

        return back()->with('success', 'User permanently deleted.');
    }
}
