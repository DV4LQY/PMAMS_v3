@extends('admin.layouts.app')

@section('title', 'Users')
@section('page_title', 'User Accounts')

@section('content')
@php
    $addBag = $errors->getBag('add');
    $editBag = $errors->getBag('edit');
    $roles = \App\Models\User::ROLES;
    $hasUnitHead = \App\Models\User::where('role', 'unit_head')->exists();
@endphp
<script>
function registerUserManager() {
    if (!window.Alpine) return;

    Alpine.data('userManager', () => ({
        addOpen: {{ $addBag->any() ? 'true' : 'false' }},
        editOpen: {{ $editBag->any() ? 'true' : 'false' }},
        deleteOpen: false,
        showAddPassword: false,
        showAddConfirmPassword: false,
        showEditPassword: false,
        showEditConfirmPassword: false,
        hasUnitHead: @js($hasUnitHead),

        addSingle: {
            name: @js(old('name', '')),
            email: @js(old('email', '')),
            role: @js(old('role', 'custodian')),
            password: '',
            password_confirmation: '',
            nameError: @js($addBag->first('name')),
            emailError: @js($addBag->first('email')),
            roleError: @js($addBag->first('role')),
            passwordError: @js($addBag->first('password'))
        },

        editUser: {
            id: @js(old('editing_id') !== null ? (int) old('editing_id') : null),
            name: @js(old('name', '')),
            email: @js(old('email', '')),
            role: @js(old('role', '')),
            password: '',
            password_confirmation: '',
            nameError: @js($editBag->first('name')),
            emailError: @js($editBag->first('email')),
            roleError: @js($editBag->first('role')),
            passwordError: @js($editBag->first('password'))
        },

        deleteUserId: null,

        openAdd() {
            this.addOpen = true;
            this.addSingle = {
                name: '', email: '', role: 'custodian', password: '', password_confirmation: '',
                nameError: '', emailError: '', roleError: '', passwordError: ''
            };
        },

        openEdit(user) {
            this.showEditPassword = false;
            this.showEditConfirmPassword = false;
            this.editUser = {
                id: user.id,
                name: user.name,
                email: user.email,
                role: user.role,
                password: '',
                password_confirmation: '',
                nameError: '', emailError: '', roleError: '', passwordError: ''
            };
            this.editOpen = true;
        },

        openDelete(id) {
            this.deleteUserId = id;
            this.deleteOpen = true;
            this.$nextTick(() => this.$refs.confirmDeleteBtn && this.$refs.confirmDeleteBtn.focus());
        }
    }));
    document.querySelectorAll('[x-data="userManager"]').forEach((element) => {
        if (!element._x_dataStack) Alpine.initTree(element);
    });
}
document.addEventListener('alpine:init', registerUserManager);
registerUserManager();
document.addEventListener('livewire:navigated', registerUserManager);
</script>
<div x-data="userManager" x-init="if (new URLSearchParams(window.location.search).get('action') === 'add') $nextTick(() => openAdd())" class="space-y-5">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">User Accounts</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Manage who can sign in and what they're allowed to do.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a
                href="{{ route('admin.users.recycleBin') }}"
                wire:navigate
                class="inline-flex items-center rounded-xl bg-gray-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-gray-600"
            >
                Recycle Bin
            </a>
            <button
                type="button"
                class="shrink-0 inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                @click="openAdd()"
            >
                + Add User
            </button>
        </div>
    </div>

    {{-- Mobile cards --}}
    <div class="grid grid-cols-1 gap-3 md:hidden">
        @forelse($users as $u)
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="font-semibold text-gray-900 dark:text-white">{{ $u->name }}</div>
                        <div class="mt-1 text-sm text-gray-500 break-all dark:text-gray-400">{{ $u->email }}</div>
                    </div>

                    <span class="inline-flex shrink-0 rounded-full {{ $u->isAdmin() ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' }} px-2.5 py-1 text-xs font-medium">
                        {{ $u->roleLabel() }}
                    </span>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-black dark:bg-gray-700 dark:hover:bg-gray-600"
                        @click="openEdit({
                            id: {{ $u->id }},
                            name: @js($u->name),
                            email: @js($u->email),
                            role: @js($u->role)
                        })"
                    >
                        Edit
                    </button>

                    @if($u->id !== auth()->id())
                        <button
                            type="button"
                            class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600"
                            @click="openDelete({{ $u->id }})"
                        >
                            Delete
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-gray-200 bg-white p-6 text-center text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                No users found.
            </div>
        @endforelse
    </div>

    {{-- Desktop table --}}
    <div class="hidden md:block overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left dark:bg-gray-900/40">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Name</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Email</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Role</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($users as $u)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $u->name }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $u->email }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full {{ $u->isAdmin() ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' }} px-2.5 py-1 text-xs font-medium">
                                    {{ $u->roleLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-black dark:bg-gray-700 dark:hover:bg-gray-600"
                                        @click="openEdit({
                                            id: {{ $u->id }},
                                            name: @js($u->name),
                                            email: @js($u->email),
                                            role: @js($u->role)
                                        })"
                                    >
                                        Edit
                                    </button>

                                    @if($u->id !== auth()->id())
                                        <button
                                            type="button"
                                            class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600"
                                            @click="openDelete({{ $u->id }})"
                                        >
                                            Delete
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">(you)</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                No users found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>
        {{ $users->links() }}
    </div>

    {{-- Add modal --}}
    <x-modal show="addOpen" title="Add User">
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-3">
            @csrf

            <div>
<label class="text-sm font-medium">Full Name <span class="text-red-600">*</span></label>
                <input
                    type="text"
                    name="name"
                    x-model="addSingle.name"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    required
                    maxlength="100"
                    placeholder="e.g. Juan Dela Cruz"
                >
                <div class="mt-1 text-sm text-red-600 dark:text-red-400" x-show="addSingle.nameError" x-text="addSingle.nameError"></div>
            </div>

            <div>
<label class="text-sm font-medium">Email <span class="text-red-600">*</span></label>
                <input
                    name="email"
                    type="email"
                    x-model="addSingle.email"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    required
                    maxlength="255"
                    pattern="[^\s@]+@[^\s@]+\.[^\s@]+"
                    title="Enter a complete email address"
                    placeholder="e.g. juan.delacruz@example.com"
                >
                <div class="mt-1 text-sm text-red-600 dark:text-red-400" x-show="addSingle.emailError" x-text="addSingle.emailError"></div>
            </div>

            <div>
<label class="text-sm font-medium">Role <span class="text-red-600">*</span></label>
                <select
                    name="role"
                    x-model="addSingle.role"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    required
                >
                    @foreach($roles as $value => $label)
                        <option
                            value="{{ $value }}"
                            @if($value === 'unit_head' && $hasUnitHead)
                                disabled
                            @endif
                        >
                            {{ $label }}
                            @if($value === 'unit_head' && $hasUnitHead)
                                (Already Assigned)
                            @endif
                        </option>
                    @endforeach
                </select>
                <div class="mt-1 text-sm text-red-600 dark:text-red-400" x-show="addSingle.roleError" x-text="addSingle.roleError"></div>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    Password
                </label>

                <div class="relative mt-1">
                    <input
                        :type="showAddPassword ? 'text' : 'password'"
                        name="password"
                        x-model="addSingle.password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 pr-12 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        placeholder="Enter password"
                    >

                    <button
                        type="button"
                        x-on:click="showAddPassword = !showAddPassword"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white"
                        :aria-label="showAddPassword ? 'Hide password' : 'Show password'"
                    >
                        <svg
                            x-show="!showAddPassword"
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm6 0c-1.5 4-5 6-9 6s-7.5-2-9-6c1.5-4 5-6 9-6s7.5 2 9 6z"/>
                        </svg>

                        <svg
                            x-show="showAddPassword"
                            x-cloak
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 4.2A10.8 10.8 0 0112 4c4 0 7.5 2 9 6a11.3 11.3 0 01-2.1 3.5M6.2 6.2A11.2 11.2 0 003 12c1.5 4 5 6 9 6 1.4 0 2.7-.2 3.8-.7"/>
                        </svg>
                    </button>
                </div>

                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Use at least 8 characters with uppercase, lowercase, and a special character.
                </p>

                <template x-if="addSingle.passwordError">
                    <p class="mt-1 text-sm text-red-600" x-text="addSingle.passwordError"></p>
                </template>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    Confirm Password
                </label>

                <div class="relative mt-1">
                    <input
                        :type="showAddConfirmPassword ? 'text' : 'password'"
                        name="password_confirmation"
                        x-model="addSingle.password_confirmation"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 pr-12 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        placeholder="Re-enter password"
                    >

                    <button
                        type="button"
                        x-on:click="showAddConfirmPassword = !showAddConfirmPassword"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white"
                        :aria-label="showAddConfirmPassword ? 'Hide password' : 'Show password'"
                    >
                        <svg
                            x-show="!showAddConfirmPassword"
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm6 0c-1.5 4-5 6-9 6s-7.5-2-9-6c1.5-4 5-6 9-6s7.5 2 9 6z"/>
                        </svg>

                        <svg
                            x-show="showAddConfirmPassword"
                            x-cloak
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 4.2A10.8 10.8 0 0112 4c4 0 7.5 2 9 6a11.3 11.3 0 01-2.1 3.5M6.2 6.2A11.2 11.2 0 003 12c1.5 4 5 6 9 6 1.4 0 2.7-.2 3.8-.7"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="flex gap-2 pt-2">
                <button class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600">Save</button>
                <button type="button" class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600" @click="addOpen=false">Cancel</button>
            </div>
        </form>
    </x-modal>

    {{-- Edit modal --}}
    <x-modal show="editOpen" title="Edit User">
        <form
            method="POST"
            :action="`{{ url('/admin/users') }}/${editUser.id}`"
            class="space-y-3"
        >
            @csrf
            @method('PUT')

            <input type="hidden" name="editing_id" :value="editUser.id">

            <div>
<label class="text-sm font-medium">Full Name <span class="text-red-600">*</span></label>
                <input
                    type="text"
                    name="name"
                    x-model="editUser.name"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    required
                    maxlength="100"
                >
                <div class="mt-1 text-sm text-red-600 dark:text-red-400" x-show="editUser.nameError" x-text="editUser.nameError"></div>
            </div>

            <div>
<label class="text-sm font-medium">Email <span class="text-red-600">*</span></label>
                <input
                    name="email"
                    type="email"
                    x-model="editUser.email"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    required
                    maxlength="255"
                    pattern="[^\s@]+@[^\s@]+\.[^\s@]+"
                    title="Enter a complete email address"
                >
                <div class="mt-1 text-sm text-red-600 dark:text-red-400" x-show="editUser.emailError" x-text="editUser.emailError"></div>
            </div>

            <div>
<label class="text-sm font-medium">Role <span class="text-red-600">*</span></label>
                <select
                    name="role"
                    x-model="editUser.role"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    required
                >
                    @foreach($roles as $value => $label)
                        <option
                            value="{{ $value }}"
                            @if($value === 'unit_head')
                                :disabled="hasUnitHead && editUser.role !== 'unit_head'"
                            @endif
                        >
                            {{ $label }}

                            @if($value === 'unit_head' && $hasUnitHead)
                                (Already Assigned)
                            @endif
                        </option>
                    @endforeach
                </select>
                <div class="mt-1 text-sm text-red-600 dark:text-red-400" x-show="editUser.roleError" x-text="editUser.roleError"></div>
            </div>

            <div>
                <label class="text-sm font-medium dark:text-gray-300">New Password</label>

                <div class="relative mt-1">
                    <input
                        :type="showEditPassword ? 'text' : 'password'"
                        name="password"
                        x-model="editUser.password"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 pr-12 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        minlength="8"
                        placeholder="Leave blank to keep current password"
                    >

                    <button
                        type="button"
                        x-on:click="showEditPassword = !showEditPassword"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white"
                        :aria-label="showEditPassword ? 'Hide password' : 'Show password'"
                    >
                        <svg
                            x-show="!showEditPassword"
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm6 0c-1.5 4-5 6-9 6s-7.5-2-9-6c1.5-4 5-6 9-6s7.5 2 9 6z"/>
                        </svg>

                        <svg
                            x-show="showEditPassword"
                            x-cloak
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 4.2A10.8 10.8 0 0112 4c4 0 7.5 2 9 6a11.3 11.3 0 01-2.1 3.5M6.2 6.2A11.2 11.2 0 003 12c1.5 4 5 6 9 6 1.4 0 2.7-.2 3.8-.7"/>
                        </svg>
                    </button>
                </div>

                <p class="mt-2 text-xs text-gray-400">
                    Leave blank to keep the current password.
                    If changing password, use at least 8 characters with uppercase,
                    lowercase, number, and special character.
                </p>

                @error('password', 'edit')
                    <p class="mt-2 text-sm text-red-400">
                        Password must contain at least 8 characters, including:
                        <br>
                        • One uppercase letter (A-Z)
                        <br>
                        • One lowercase letter (a-z)
                        <br>
                        • One number (0-9)
                        <br>
                        • One special character (!, @, #, $, %, etc.)
                    </p>
                @enderror
            </div>

            <div>
                <label class="text-sm font-medium dark:text-gray-300">Confirm New Password</label>

                <div class="relative mt-1">
                    <input
                        :type="showEditConfirmPassword ? 'text' : 'password'"
                        name="password_confirmation"
                        x-model="editUser.password_confirmation"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 pr-12 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        minlength="8"
                    >

                    <button
                        type="button"
                        x-on:click="showEditConfirmPassword = !showEditConfirmPassword"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white"
                        :aria-label="showEditConfirmPassword ? 'Hide password' : 'Show password'"
                    >
                        <svg
                            x-show="!showEditConfirmPassword"
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm6 0c-1.5 4-5 6-9 6s-7.5-2-9-6c1.5-4 5-6 9-6s7.5 2 9 6z"/>
                        </svg>

                        <svg
                            x-show="showEditConfirmPassword"
                            x-cloak
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 4.2A10.8 10.8 0 0112 4c4 0 7.5 2 9 6a11.3 11.3 0 01-2.1 3.5M6.2 6.2A11.2 11.2 0 003 12c1.5 4 5 6 9 6 1.4 0 2.7-.2 3.8-.7"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="flex gap-2 pt-2">
                <button class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600">Update</button>
                <button type="button" class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600" @click="editOpen=false">Cancel</button>
            </div>
        </form>
    </x-modal>

    {{-- Delete modal --}}
    <x-modal show="deleteOpen" title="Delete User">
        <div class="space-y-3">
            <div class="text-sm text-gray-700 dark:text-gray-300">
                Are you sure you want to delete this user account?
            </div>

            <form
                method="POST"
                :action="`{{ url('/admin/users') }}/${deleteUserId}`"
                @submit="if (!deleteUserId) $event.preventDefault()"
                class="flex gap-2"
            >
                @csrf
                @method('DELETE')

                <button type="submit" x-ref="confirmDeleteBtn" class="rounded-lg bg-red-600 px-4 py-2 text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600">Confirm</button>
                <button type="button" class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600" @click="deleteOpen=false">Cancel</button>
            </form>
        </div>
    </x-modal>
</div>
@endsection
