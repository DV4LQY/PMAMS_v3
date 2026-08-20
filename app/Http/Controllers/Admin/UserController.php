<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\DeviceMaintenancePhoto;
use App\Models\DeviceMaintenanceRecord;
use App\Models\MaintenancePlanSchedule;
use App\Models\Location;
use App\Models\Office;
use App\Models\Staff;
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
            return User::sanitizePermissionsForRole(
                $role,
                $fallback ?? User::permissionsForRole($role)
            );
        }

        $menuKeys = array_keys(User::PERMISSION_MENUS);
        $actionKeys = array_keys(User::PERMISSION_ACTIONS);
        $menus = array_values(array_intersect((array) $request->input('permissions.menus', []), $menuKeys));
        $actions = [];

        foreach (array_keys(User::PERMISSION_RESOURCES) as $resource) {
            $selected = $request->input("permissions.actions.{$resource}");

            // The Maintenance Checklist action is an internal workflow
            // permission and is no longer exposed as a role-editor row. Keep
            // its existing role profile when older/newer forms are saved so
            // hiding the row cannot silently revoke checklist access.
            if ($resource === 'checklist' && ! is_array($selected)) {
                $current = $fallback ?? User::permissionsForRole($role);
                $actions[$resource] = array_values(array_intersect(
                    (array) data_get($current, 'actions.checklist', []),
                    $actionKeys
                ));
                continue;
            }

            $actions[$resource] = is_array($selected)
                ? array_values(array_intersect($selected, $actionKeys))
                : [];
        }

        return User::sanitizePermissionsForRole($role, [
            'menus' => $menus,
            'actions' => $actions,
        ]);
    }

    /**
     * Do not rely only on the browser's dirty flag. This comparison keeps
     * unchecked Delete (or Select All) boxes persistent even if a browser
     * submits the form without firing Alpine's change event.
     */
    private function permissionsPayloadChanged(Request $request, string $role, ?array $permissions): bool
    {
        if (! $request->boolean('permissions_present') || $role === User::ROLE_SUPER_ADMIN || ! is_array($permissions)) {
            return false;
        }

        $normalize = static function (array $value): array {
            $menus = array_values(array_unique((array) ($value['menus'] ?? [])));
            sort($menus);

            $actions = [];
            foreach (array_keys(User::PERMISSION_RESOURCES) as $resource) {
                $selected = array_values(array_unique((array) data_get($value, "actions.{$resource}", [])));
                sort($selected);
                $actions[$resource] = $selected;
            }

            return ['menus' => $menus, 'actions' => $actions];
        };

        return $normalize(User::permissionsForRole($role)) !== $normalize($permissions);
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
            'position' => $user->position,
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

        $deletedMaintenancePlans = MaintenancePlanSchedule::onlyTrashed()
            ->with(['location', 'office', 'assignedUser', 'assignedUsers', 'latestOverride', 'completion'])
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'plans_page')
            ->withQueryString();

        $deletedLocations = Location::onlyTrashed()
            ->withCount(['offices' => fn ($query) => $query->withTrashed()])
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'locations_page')
            ->withQueryString();

        $deletedOffices = Office::onlyTrashed()
            ->with(['location' => fn ($query) => $query->withTrashed()])
            ->withCount(['staff' => fn ($query) => $query->withTrashed()])
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'offices_page')
            ->withQueryString();

        $deletedStaff = Staff::onlyTrashed()
            ->with(['office' => fn ($query) => $query->withTrashed()->with(['location' => fn ($location) => $location->withTrashed()])])
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'staff_page')
            ->withQueryString();

        return view('admin.users.recycle-bin', compact('deletedUsers', 'deletedDevices', 'deletedMaintenancePlans', 'deletedLocations', 'deletedOffices', 'deletedStaff'));
    }

    public function permanentDelete(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'type' => ['required', Rule::in(['users', 'devices', 'maintenance_plans', 'locations', 'offices', 'staff', 'all'])],
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer', 'distinct'],
            'select_all' => ['nullable', 'boolean'],
            'empty' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

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

        $types = $type === 'all' ? ['users', 'devices', 'maintenance_plans', 'locations', 'offices', 'staff'] : [$type];
        $deletedCounts = [];

        DB::transaction(function () use ($types, $selectAll, $ids, $data, &$deletedCounts) {
            foreach ($types as $currentType) {
                $deletedCounts[$currentType] = match ($currentType) {
                    'users' => $this->permanentlyDeleteUsers($selectAll, $ids),
                    'devices' => $this->permanentlyDeleteDevices($selectAll, $ids),
                    'maintenance_plans' => $this->permanentlyDeleteMaintenancePlans($selectAll, $ids),
                    'locations' => $this->permanentlyDeleteLocations($selectAll, $ids),
                    'offices' => $this->permanentlyDeleteOffices($selectAll, $ids),
                    'staff' => $this->permanentlyDeleteStaff($selectAll, $ids),
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
                'remarks' => trim((string) ($data['remarks'] ?? '')),
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

    private function permanentlyDeleteMaintenancePlans(bool $selectAll, $ids): int
    {
        $query = MaintenancePlanSchedule::onlyTrashed();
        if (! $selectAll) {
            $query->whereIn('id', $ids);
        }

        $plans = $query->get();
        foreach ($plans as $plan) {
            // Keep this path in sync with the single-record PM Plan purge.
            // The schedule's checklist history is stored separately and is
            // intentionally not deleted here.
            $plan->assignedUsers()->detach();
            $plan->overrides()->delete();
            $plan->completion()->delete();
            $plan->forceDelete();
        }

        return $plans->count();
    }

    private function permanentlyDeleteLocations(bool $selectAll, $ids): int
    {
        $query = Location::onlyTrashed();
        if (! $selectAll) {
            $query->whereIn('id', $ids);
        }

        $locations = $query->get();
        $deletedCount = 0;
        foreach ($locations as $location) {
            // A normal location delete is blocked while it has offices,
            // assignments, or an active PM Plan. Keep the same protection for
            // bulk/permanent deletion so a recycle-bin action cannot orphan
            // dependent records.
            $hasAssignments = DeviceAssignment::query()
                ->where(function ($assignment) use ($location) {
                    $assignment->where('location_id', $location->id)
                        ->orWhereHas('office', fn ($office) => $office->where('location_id', $location->id))
                        ->orWhereHas('staff.office', fn ($office) => $office->where('location_id', $location->id));
                })
                ->exists();

            if ($location->offices()->withTrashed()->exists() || $hasAssignments || MaintenancePlanSchedule::withTrashed()->where('location_id', $location->id)->exists()) {
                continue;
            }

            $location->forceDelete();
            $deletedCount++;
        }

        return $deletedCount;
    }

    private function permanentlyDeleteOffices(bool $selectAll, $ids): int
    {
        $query = Office::onlyTrashed();
        if (! $selectAll) {
            $query->whereIn('id', $ids);
        }

        $offices = $query->get();
        $deletedCount = 0;
        foreach ($offices as $office) {
            $staffIds = Staff::withTrashed()->where('office_id', $office->id)->pluck('id');
            $hasAssignments = DeviceAssignment::query()
                ->where(function ($assignment) use ($office, $staffIds) {
                    $assignment->where('office_id', $office->id);
                    if ($staffIds->isNotEmpty()) {
                        $assignment->orWhereIn('staff_id', $staffIds);
                    }
                })
                ->exists();

            if ($staffIds->isNotEmpty() || $hasAssignments || MaintenancePlanSchedule::withTrashed()->where('office_id', $office->id)->exists()) {
                continue;
            }

            $office->forceDelete();
            $deletedCount++;
        }

        return $deletedCount;
    }

    private function permanentlyDeleteStaff(bool $selectAll, $ids): int
    {
        $query = Staff::onlyTrashed();
        if (! $selectAll) {
            $query->whereIn('id', $ids);
        }

        $staff = $query->get();
        $deletedCount = 0;
        foreach ($staff as $record) {
            if (DeviceAssignment::where('staff_id', $record->id)->exists()) {
                continue;
            }

            $record->forceDelete();
            $deletedCount++;
        }

        return $deletedCount;
    }

    public function store(Request $request)
    {
        $data = $request->validateWithBag('add', array_merge([
            'name' => ['required', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:150'],
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
        $this->saveRolePermissions(
            $data['role'],
            $rolePermissions,
            $request->boolean('permissions_changed')
                || $this->permissionsPayloadChanged($request, $data['role'], $rolePermissions)
        );

        $newUser = User::create([
            'name' => $data['name'],
            'position' => filled($data['position'] ?? null) ? trim($data['position']) : null,
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
            'position' => ['nullable', 'string', 'max:150'],
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

        // Capture the old shared role profile before saving the submitted
        // profile. Otherwise the activity log records the new permissions on
        // both sides and hides the actual role-permission change.
        $before = [
            'name' => $user->name,
            'position' => $user->position,
            'email' => $user->email,
            'role' => $user->roleLabel(),
            'permission_menus' => User::permissionsForRole((string) $user->role)['menus'] ?? [],
            'permission_actions' => User::permissionsForRole((string) $user->role)['actions'] ?? [],
        ];

        if ($passwordChanged) {
            $before['password'] = null;
        }

        $rolePermissions = $this->permissionsFromRequest(
            $request,
            $data['role'],
            User::permissionsForRole($data['role'])
        );
        $this->saveRolePermissions(
            $data['role'],
            $rolePermissions,
            $request->boolean('permissions_changed')
                || $this->permissionsPayloadChanged($request, $data['role'], $rolePermissions)
        );

        $user->name = $data['name'];
        $user->position = filled($data['position'] ?? null) ? trim($data['position']) : null;
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
                            'position' => $user->position,
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

    /** Restore selected records from one recycle-bin section. */
    public function restoreSelected(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'type' => ['required', Rule::in(['users', 'devices', 'maintenance_plans', 'locations', 'offices', 'staff'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $type = $data['type'];
        $ids = collect($data['ids'])->map(fn ($id) => (int) $id)->filter()->values();
        $restored = DB::transaction(fn () => match ($type) {
            'users' => $this->restoreSelectedUsers($ids),
            'devices' => $this->restoreSelectedDevices($ids),
            'maintenance_plans' => $this->restoreSelectedMaintenancePlans($ids),
            'locations' => $this->restoreSelectedLocations($ids),
            'offices' => $this->restoreSelectedOffices($ids),
            'staff' => $this->restoreSelectedStaff($ids),
        });

        $labels = [
            'users' => 'user',
            'devices' => 'equipment',
            'maintenance_plans' => 'PM Plan',
            'locations' => 'location',
            'offices' => 'office',
            'staff' => 'staff member',
        ];
        $label = $labels[$type];

        if ($restored === 0) {
            return back()->with('warning', "No selected {$label} records were found in the recycle bin.");
        }

        ActivityLog::record(
            'restored',
            "Restored {$restored} selected {$label} record(s) from the recycle bin.",
            null,
            ActivityLog::makePayload([
                'bulk' => true,
                'record_type' => $label,
                'ids' => $ids->all(),
                'restored' => $restored,
            ])
        );

        return back()->with('success', "Restored {$restored} selected {$label} record(s) from the recycle bin.");
    }

    private function restoreSelectedUsers($ids): int
    {
        $records = User::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($records as $record) {
            $record->restore();
        }

        return $records->count();
    }

    private function restoreSelectedDevices($ids): int
    {
        $records = Device::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($records as $record) {
            $record->restore();
        }

        return $records->count();
    }

    private function restoreSelectedMaintenancePlans($ids): int
    {
        $records = MaintenancePlanSchedule::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($records as $record) {
            $record->restore();
        }

        return $records->count();
    }

    private function restoreSelectedLocations($ids): int
    {
        $records = Location::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($records as $record) {
            $record->restore();
        }

        return $records->count();
    }

    private function restoreSelectedOffices($ids): int
    {
        $records = Office::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($records as $record) {
            $location = Location::withTrashed()->find($record->location_id);
            if ($location?->trashed()) {
                $location->restore();
            }
            $record->restore();
        }

        return $records->count();
    }

    private function restoreSelectedStaff($ids): int
    {
        $records = Staff::onlyTrashed()->whereIn('id', $ids)->get();
        foreach ($records as $record) {
            $office = Office::withTrashed()->find($record->office_id);
            if ($office?->trashed()) {
                $location = Location::withTrashed()->find($office->location_id);
                if ($location?->trashed()) {
                    $location->restore();
                }
                $office->restore();
            }
            $record->restore();
        }

        return $records->count();
    }

    public function restoreOffice(int $office)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
        $record = Office::onlyTrashed()->findOrFail($office);
        $this->restoreSelectedOffices(collect([$record->id]));

        ActivityLog::record('restored', "Restored office \"{$record->name}\" from the recycle bin.", $record, ActivityLog::makePayload([
            'office' => $record->name,
            'location_id' => $record->location_id,
        ]));

        return back()->with('success', 'Office restored.');
    }

    public function restoreStaff(int $staff)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
        $record = Staff::onlyTrashed()->findOrFail($staff);
        $this->restoreSelectedStaff(collect([$record->id]));

        ActivityLog::record('restored', "Restored staff member \"{$record->display_name}\" from the recycle bin.", $record, ActivityLog::makePayload([
            'staff' => $record->display_name,
            'office_id' => $record->office_id,
        ]));

        return back()->with('success', 'Staff member restored.');
    }

    /** Restore every record currently held in the recycle bin. */
    public function restoreAll(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $restoredUsers = 0;
        $restoredDevices = 0;
        $restoredMaintenancePlans = 0;
        $restoredLocations = 0;
        $restoredOffices = 0;
        $restoredStaff = 0;

        DB::transaction(function () use (&$restoredUsers, &$restoredDevices, &$restoredMaintenancePlans, &$restoredLocations, &$restoredOffices, &$restoredStaff): void {
            $deletedUsers = User::onlyTrashed()->get();
            foreach ($deletedUsers as $deletedUser) {
                $deletedUser->restore();
            }
            $restoredUsers = $deletedUsers->count();

            $deletedDevices = Device::onlyTrashed()->get();
            foreach ($deletedDevices as $deletedDevice) {
                $deletedDevice->restore();
            }
            $restoredDevices = $deletedDevices->count();

            $deletedMaintenancePlans = MaintenancePlanSchedule::onlyTrashed()->get();
            foreach ($deletedMaintenancePlans as $deletedMaintenancePlan) {
                $deletedMaintenancePlan->restore();
            }
            $restoredMaintenancePlans = $deletedMaintenancePlans->count();

            $deletedLocations = Location::onlyTrashed()->get();
            foreach ($deletedLocations as $deletedLocation) {
                $deletedLocation->restore();
            }
            $restoredLocations = $deletedLocations->count();

            $deletedOffices = Office::onlyTrashed()->get();
            foreach ($deletedOffices as $deletedOffice) {
                $deletedOffice->restore();
            }
            $restoredOffices = $deletedOffices->count();

            $deletedStaff = Staff::onlyTrashed()->get();
            foreach ($deletedStaff as $deletedStaffMember) {
                $deletedStaffMember->restore();
            }
            $restoredStaff = $deletedStaff->count();

        });

        $total = $restoredUsers + $restoredDevices + $restoredMaintenancePlans + $restoredLocations + $restoredOffices + $restoredStaff;
        if ($total > 0) {
            ActivityLog::record(
                'restored',
                'Restored all user, equipment, location, office, staff, and PM Plan records from the recycle bin.',
                null,
                ActivityLog::makePayload([
                    'users_restored' => $restoredUsers,
                    'equipment_restored' => $restoredDevices,
                    'maintenance_plans_restored' => $restoredMaintenancePlans,
                    'locations_restored' => $restoredLocations,
                    'offices_restored' => $restoredOffices,
                    'staff_restored' => $restoredStaff,
                ])
            );
        }

        return back()->with('success', $total > 0
            ? "Restored {$total} recycle-bin record(s)."
            : 'The recycle bin is already empty.');
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
