<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    private function buildSummary(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roleLabel(),
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

        return view('admin.users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $data = $request->validateWithBag('add', [
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
        ], [
            'email.unique' => 'This email is already registered.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        if ($data['role'] === User::ROLE_UNIT_HEAD && User::where('role', User::ROLE_UNIT_HEAD)->exists()) {
            return back()
                ->withErrors([
                    'role' => 'A Unit Head account already exists. Please update the existing Unit Head.',
                ], 'add');
        }

        $newUser = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'password' => Hash::make($data['password']),
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
        $rules = [
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
        ];

        $data = $request->validateWithBag('edit', $rules, [
            'email.unique' => 'This email is already registered.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        // Safety net: an admin can't demote themselves away from admin —
        // avoids accidentally locking every admin out of the system.
        if ($user->id === auth()->id() && $user->isAdmin() && $data['role'] !== User::ROLE_ADMIN) {
            return back()->withErrors([
                'role' => 'You cannot remove your own admin role.',
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

        $before = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roleLabel(),
        ];

        if ($passwordChanged) {
            $before['password'] = null;
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $data['role'];

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

        return back()->with('success', 'User deleted.');
    }
}
