@extends('admin.layouts.app')

@section('title', 'Change Password')
@section('page_title', 'Change Password')

@section('content')
<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
            Change Password
        </h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Update your account password. Use at least 8 characters with uppercase, lowercase, and one special character such as #, @, $, or !.
        </p>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700 dark:border-green-800 dark:bg-green-950/40 dark:text-green-300">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300">
            <div class="font-semibold">Please fix the following:</div>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <form
            method="POST"
            action="{{ route('admin.change-password.update') }}"
            x-data="{
                showCurrentPassword: false,
                showNewPassword: false,
                showConfirmPassword: false
            }"
            class="space-y-5"
        >
            @csrf
            @method('PUT')

            <div>
                <label for="current_password" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    Current Password <span class="text-red-500">*</span>
                </label>
                <div class="relative mt-1">
                    <input
                        id="current_password"
                        :type="showCurrentPassword ? 'text' : 'password'"
                        name="current_password"
                        required
                        autocomplete="current-password"
                        class="w-full rounded-xl border border-gray-300 px-3 py-2 pr-12 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >

                    <button
                        type="button"
                        x-on:click="showCurrentPassword = !showCurrentPassword"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white"
                    >
                        <span x-show="!showCurrentPassword">👁</span>
                        <span x-show="showCurrentPassword" x-cloak>🙈</span>
                    </button>
                </div>
                @error('current_password')
                    <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="password" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    New Password <span class="text-red-500">*</span>
                </label>
                <div class="relative mt-1">
                    <input
                        id="password"
                        :type="showNewPassword ? 'text' : 'password'"
                        name="password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        class="w-full rounded-xl border border-gray-300 px-3 py-2 pr-12 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >

                    <button
                        type="button"
                        x-on:click="showNewPassword = !showNewPassword"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white"
                    >
                        <span x-show="!showNewPassword">👁</span>
                        <span x-show="showNewPassword" x-cloak>🙈</span>
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Must be at least 8 characters and include uppercase, lowercase, and one special character.
                </p>
                @error('password')
                    <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    Confirm New Password <span class="text-red-500">*</span>
                </label>
                <div class="relative mt-1">
                    <input
                        id="password_confirmation"
                        :type="showConfirmPassword ? 'text' : 'password'"
                        name="password_confirmation"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        class="w-full rounded-xl border border-gray-300 px-3 py-2 pr-12 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >

                    <button
                        type="button"
                        x-on:click="showConfirmPassword = !showConfirmPassword"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white"
                    >
                        <span x-show="!showConfirmPassword">👁</span>
                        <span x-show="showConfirmPassword" x-cloak>🙈</span>
                    </button>
                </div>
            </div>

            <label class="flex items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-300">
                <input
                    type="checkbox"
                    name="logout_after_change"
                    value="1"
                    class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                >
                <span>
                    Log me out after changing my password.
                    <span class="block text-xs text-gray-500 dark:text-gray-400">
                        Recommended when using a shared or public computer.
                    </span>
                </span>
            </label>

            <div class="flex items-center gap-3 pt-2">
                <button
                    type="submit"
                    class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                >
                    Update Password
                </button>

                <a
                    href="{{ route('admin.dashboard') }}"
                    class="rounded-xl bg-gray-100 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                >
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
