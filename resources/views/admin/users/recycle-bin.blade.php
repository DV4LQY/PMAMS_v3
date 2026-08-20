@extends('admin.layouts.app')

@section('title', 'Recycle Bin')
@section('page_title', 'Recycle Bin')

@section('content')
<div class="space-y-5">
    @php
        $deletedUserCount = $deletedUsers->total();
        $deletedDeviceCount = $deletedDevices->total();
        $deletedMaintenancePlanCount = $deletedMaintenancePlans->total();
        $deletedLocationCount = $deletedLocations->total();
        $deletedOfficeCount = $deletedOffices->total();
        $deletedStaffCount = $deletedStaff->total();
        $hasDeletedRecords = ($deletedUserCount + $deletedDeviceCount + $deletedMaintenancePlanCount + $deletedLocationCount + $deletedOfficeCount + $deletedStaffCount) > 0;
    @endphp
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Restore deleted users, equipment, locations, offices, staff, and PM Plans. Checklist history is managed in Checklist Cleanup. Permanent deletion is available only to Super Admins.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if($hasDeletedRecords)
            <form method="POST" action="{{ route('admin.recycle-bin.restoreAll') }}" onsubmit="return confirm('Restore all deleted users, equipment, locations, offices, staff, and PM Plans from the recycle bin?')">
                @csrf
                @method('PATCH')
                <button type="submit" class="rounded-xl bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">
                    Restore All
                </button>
            </form>
            <button type="button" onclick="emptyRecycleBin()" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                Permanently Delete All
            </button>
            @endif
            <a href="{{ route('admin.users.index') }}" wire:navigate class="inline-flex items-center justify-center rounded-xl bg-gray-700 px-4 py-2 text-sm font-medium text-white hover:bg-gray-600">
                Back to Users
            </a>
        </div>
    </div>

    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
        Restoring returns the record to the active list. Permanent deletion cannot be undone.
    </div>

    @if($hasDeletedRecords)
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200" role="status">
            <p class="font-semibold">Deleted records are still present in the database.</p>
            <p class="mt-1">{{ number_format($deletedUserCount) }} user(s), {{ number_format($deletedDeviceCount) }} equipment record(s), {{ number_format($deletedLocationCount) }} location(s), {{ number_format($deletedOfficeCount) }} office(s), {{ number_format($deletedStaffCount) }} staff member(s), and {{ number_format($deletedMaintenancePlanCount) }} PM Plan(s) are in the recycle bin. Use the row buttons, selected buttons, or <strong>Permanently Delete All</strong> to manage them.</p>
        </div>
    @endif

    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Deleted Users</h2>
    @if($deletedUsers->total() > 0)
    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-5 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
            <input id="delete-all-users" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-red-600 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-700" onchange="toggleRecycleBinSelection('users', this.checked)" aria-label="Select all deleted users matching the current filters">
            Select all deleted users matching the current filters
        </label>
        <span class="text-sm text-gray-500 dark:text-gray-400">{{ number_format($deletedUsers->total()) }} matching</span>
    </div>
    @endif
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left dark:bg-gray-900/40">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Select</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Name</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Email</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Role</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Deleted</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($deletedUsers as $user)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3"><input type="checkbox" data-bin-checkbox="users" value="{{ $user->id }}" class="h-4 w-4" onchange="syncRecycleBinSelectAll('users')"></td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $user->roleLabel() }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">{{ $user->deleted_at?->format('M d, Y h:i A') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex flex-wrap items-center gap-2">
                                    <form method="POST" action="{{ route('admin.users.restore', $user->id) }}">
                                        @csrf @method('PATCH')
                                        <x-action-icon type="submit" icon="restore" variant="green" label="Restore user" />
                                    </form>
                                    <x-action-icon type="button" icon="trash" variant="red" label="Permanently delete user" onclick="permanentDeleteSingle('users', {{ $user->id }})" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">No deleted users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($deletedUsers->total() > 0)
    <div id="recycle-actions-users" class="hidden flex flex-wrap items-center gap-3">
        <button type="button" onclick="restoreSelectedRecycleBin('users')" class="rounded-lg bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700">Restore Selected Users</button>
        <button type="button" onclick="permanentDeleteSelected('users')" class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">Permanently Delete Selected Users</button>
    </div>
    @endif
    {{ $deletedUsers->links() }}

    <h2 class="pt-3 text-lg font-semibold text-gray-900 dark:text-white">Deleted Equipment</h2>
    @if($deletedDevices->total() > 0)
    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-5 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
            <input id="delete-all-devices" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-red-600 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-700" onchange="toggleRecycleBinSelection('devices', this.checked)" aria-label="Select all deleted equipment matching the current filters">
            Select all deleted equipment matching the current filters
        </label>
        <span class="text-sm text-gray-500 dark:text-gray-400">{{ number_format($deletedDevices->total()) }} matching</span>
    </div>
    @endif
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left dark:bg-gray-900/40">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Select</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Property</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Type</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">History</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Photos</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Deleted</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($deletedDevices as $device)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3"><input type="checkbox" data-bin-checkbox="devices" value="{{ $device->id }}" class="h-4 w-4" onchange="syncRecycleBinSelectAll('devices')"></td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $device->property_number ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $device->type?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $device->maintenance_records_including_trashed_count }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $device->maintenance_photos_count }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">{{ $device->deleted_at?->format('M d, Y h:i A') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex flex-wrap items-center gap-2">
                                    <form method="POST" action="{{ route('admin.devices.restore', $device->id) }}">
                                        @csrf @method('PATCH')
                                        <x-action-icon type="submit" icon="restore" variant="green" label="Restore equipment" />
                                    </form>
                                    <x-action-icon type="button" icon="trash" variant="red" label="Permanently delete equipment" onclick="permanentDeleteSingle('devices', {{ $device->id }})" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">No deleted equipment found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($deletedDevices->total() > 0)
    <div id="recycle-actions-devices" class="hidden flex flex-wrap items-center gap-3">
        <button type="button" onclick="restoreSelectedRecycleBin('devices')" class="rounded-lg bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700">Restore Selected Equipment</button>
        <button type="button" onclick="permanentDeleteSelected('devices')" class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">Permanently Delete Selected Equipment</button>
    </div>
    @endif
    {{ $deletedDevices->links() }}

    <h2 class="pt-3 text-lg font-semibold text-gray-900 dark:text-white">Deleted PM Plans</h2>
    @if($deletedMaintenancePlans->total() > 0)
    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-5 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
            <input id="delete-all-maintenance_plans" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-red-600 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-700" onchange="toggleRecycleBinSelection('maintenance_plans', this.checked)" aria-label="Select all deleted PM Plans matching the current filters">
            Select all deleted PM Plans matching the current filters
        </label>
        <span class="text-sm text-gray-500 dark:text-gray-400">{{ number_format($deletedMaintenancePlans->total()) }} matching</span>
    </div>
    @endif
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left dark:bg-gray-900/40">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Select</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Location / Office</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Schedule</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Assigned Admins / Unit Heads</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Deleted</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($deletedMaintenancePlans as $plan)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3"><input type="checkbox" data-bin-checkbox="maintenance_plans" value="{{ $plan->id }}" class="h-4 w-4" onchange="syncRecycleBinSelectAll('maintenance_plans')"></td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                {{ $plan->location?->name ?? 'Unassigned location' }}{{ $plan->office?->name ? ' - ' . $plan->office->name : '' }}
                                <span class="mt-1 block text-xs font-normal text-gray-500 dark:text-gray-400">{{ $plan->title }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                {{ optional($plan->schedule_month_from ?: $plan->scheduled_date)->format('M Y') }}
                                @if($plan->schedule_month_to && ! optional($plan->schedule_month_from ?: $plan->scheduled_date)->isSameMonth($plan->schedule_month_to))
                                    – {{ $plan->schedule_month_to->format('M Y') }}
                                @endif
                                @if($plan->latestOverride)
                                    <span class="mt-1 block text-xs text-amber-700 dark:text-amber-300">Override: {{ optional($plan->latestOverride->override_date ?: $plan->latestOverride->override_month_from)->format('m/d/Y') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                {{ $plan->assignedUsers->pluck('name')->filter()->join(', ') ?: ($plan->assignedUser?->name ?? 'All eligible admins') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">{{ $plan->deleted_at?->format('M d, Y h:i A') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex flex-wrap items-center gap-2">
                                    <form method="POST" action="{{ route('admin.maintenance-plan.restore', $plan->id) }}">
                                        @csrf @method('PATCH')
                                        <x-action-icon type="submit" icon="restore" variant="green" label="Restore PM plan" />
                                    </form>
                                    <x-action-icon type="button" icon="trash" variant="red" label="Permanently delete PM plan" onclick="permanentDeleteSingle('maintenance_plans', {{ $plan->id }})" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">No deleted PM Plans found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($deletedMaintenancePlans->total() > 0)
    <div id="recycle-actions-maintenance_plans" class="hidden flex flex-wrap items-center gap-3">
        <button type="button" onclick="restoreSelectedRecycleBin('maintenance_plans')" class="rounded-lg bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700">Restore Selected PM Plans</button>
        <button type="button" onclick="permanentDeleteSelected('maintenance_plans')" class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">Permanently Delete Selected PM Plans</button>
    </div>
    @endif
    {{ $deletedMaintenancePlans->links() }}

    <h2 class="pt-3 text-lg font-semibold text-gray-900 dark:text-white">Deleted Offices</h2>
    @if($deletedOffices->total() > 0)
    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-5 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
            <input id="delete-all-offices" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-red-600 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-700" onchange="toggleRecycleBinSelection('offices', this.checked)" aria-label="Select all deleted offices matching the current filters">
            Select all deleted offices matching the current filters
        </label>
        <span class="text-sm text-gray-500 dark:text-gray-400">{{ number_format($deletedOffices->total()) }} matching</span>
    </div>
    @endif
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left dark:bg-gray-900/40">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Select</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Office</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Location</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Staff</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Deleted</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($deletedOffices as $office)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3"><input type="checkbox" data-bin-checkbox="offices" value="{{ $office->id }}" class="h-4 w-4" onchange="syncRecycleBinSelectAll('offices')"></td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $office->name }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $office->location?->name ?? 'Deleted location' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $office->staff_count }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">{{ $office->deleted_at?->format('M d, Y h:i A') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex flex-wrap items-center gap-2">
                                    <form method="POST" action="{{ route('admin.offices.restore', $office->id) }}">
                                        @csrf @method('PATCH')
                                        <x-action-icon type="submit" icon="restore" variant="green" label="Restore office" />
                                    </form>
                                    <x-action-icon type="button" icon="trash" variant="red" label="Permanently delete office" onclick="permanentDeleteSingle('offices', {{ $office->id }})" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">No deleted offices found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($deletedOffices->total() > 0)
    <div id="recycle-actions-offices" class="hidden flex flex-wrap items-center gap-3">
        <button type="button" onclick="restoreSelectedRecycleBin('offices')" class="rounded-lg bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700">Restore Selected Offices</button>
        <button type="button" onclick="permanentDeleteSelected('offices')" class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">Permanently Delete Selected Offices</button>
    </div>
    @endif
    {{ $deletedOffices->links() }}

    <h2 class="pt-3 text-lg font-semibold text-gray-900 dark:text-white">Deleted Staff</h2>
    @if($deletedStaff->total() > 0)
    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-5 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
            <input id="delete-all-staff" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-red-600 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-700" onchange="toggleRecycleBinSelection('staff', this.checked)" aria-label="Select all deleted staff matching the current filters">
            Select all deleted staff matching the current filters
        </label>
        <span class="text-sm text-gray-500 dark:text-gray-400">{{ number_format($deletedStaff->total()) }} matching</span>
    </div>
    @endif
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left dark:bg-gray-900/40">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Select</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Name</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Position</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Office / Location</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Deleted</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($deletedStaff as $staff)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3"><input type="checkbox" data-bin-checkbox="staff" value="{{ $staff->id }}" class="h-4 w-4" onchange="syncRecycleBinSelectAll('staff')"></td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $staff->display_name }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $staff->position ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $staff->office?->name ?? 'Deleted office' }}{{ $staff->office?->location?->name ? ' / ' . $staff->office->location->name : '' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">{{ $staff->deleted_at?->format('M d, Y h:i A') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex flex-wrap items-center gap-2">
                                    <form method="POST" action="{{ route('admin.staff.restore', $staff->id) }}">
                                        @csrf @method('PATCH')
                                        <x-action-icon type="submit" icon="restore" variant="green" label="Restore staff member" />
                                    </form>
                                    <x-action-icon type="button" icon="trash" variant="red" label="Permanently delete staff member" onclick="permanentDeleteSingle('staff', {{ $staff->id }})" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">No deleted staff found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($deletedStaff->total() > 0)
    <div id="recycle-actions-staff" class="hidden flex flex-wrap items-center gap-3">
        <button type="button" onclick="restoreSelectedRecycleBin('staff')" class="rounded-lg bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700">Restore Selected Staff</button>
        <button type="button" onclick="permanentDeleteSelected('staff')" class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">Permanently Delete Selected Staff</button>
    </div>
    @endif
    {{ $deletedStaff->links() }}

    <h2 class="pt-3 text-lg font-semibold text-gray-900 dark:text-white">Deleted Locations</h2>
    @if($deletedLocations->total() > 0)
    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-5 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
            <input id="delete-all-locations" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-red-600 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-700" onchange="toggleRecycleBinSelection('locations', this.checked)" aria-label="Select all deleted locations matching the current filters">
            Select all deleted locations matching the current filters
        </label>
        <span class="text-sm text-gray-500 dark:text-gray-400">{{ number_format($deletedLocations->total()) }} matching</span>
    </div>
    @endif
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left dark:bg-gray-900/40">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Select</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Location</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Code</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Offices</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Deleted</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($deletedLocations as $location)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3"><input type="checkbox" data-bin-checkbox="locations" value="{{ $location->id }}" class="h-4 w-4" onchange="syncRecycleBinSelectAll('locations')"></td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $location->name }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $location->code ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $location->offices_count }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">{{ $location->deleted_at?->format('M d, Y h:i A') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex flex-wrap items-center gap-2">
                                    <form method="POST" action="{{ route('admin.locations.restore', $location->id) }}">
                                        @csrf @method('PATCH')
                                        <x-action-icon type="submit" icon="restore" variant="green" label="Restore location" />
                                    </form>
                                    <x-action-icon type="button" icon="trash" variant="red" label="Permanently delete location" onclick="permanentDeleteSingle('locations', {{ $location->id }})" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">No deleted locations found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($deletedLocations->total() > 0)
    <div id="recycle-actions-locations" class="hidden flex flex-wrap items-center gap-3">
        <button type="button" onclick="restoreSelectedRecycleBin('locations')" class="rounded-lg bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700">Restore Selected Locations</button>
        <button type="button" onclick="permanentDeleteSelected('locations')" class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">Permanently Delete Selected Locations</button>
    </div>
    @endif
    {{ $deletedLocations->links() }}

    <form id="recycle-bin-permanent-delete-form" method="POST" action="{{ route('admin.recycle-bin.permanentDelete') }}" class="hidden">
        @csrf
        <input type="hidden" name="type" id="recycle-bin-delete-type">
        <input type="hidden" name="select_all" id="recycle-bin-delete-select-all" value="0">
        <input type="hidden" name="remarks" id="recycle-bin-delete-remarks">
    </form>

    <form id="recycle-bin-restore-selected-form" method="POST" action="{{ route('admin.recycle-bin.restoreSelected') }}" class="hidden">
        @csrf
        @method('PATCH')
        <input type="hidden" name="type" id="recycle-bin-restore-type">
    </form>

    <div id="recycle-bin-remarks-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="recycle-bin-remarks-title">
        <div class="w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="recycle-bin-remarks-title" class="text-lg font-semibold text-gray-900 dark:text-white">Permanent deletion remarks</h2>
                    <p id="recycle-bin-remarks-message" class="mt-1 text-sm text-gray-600 dark:text-gray-300">This action cannot be undone. Add an optional reason for the activity log.</p>
                </div>
                <button type="button" onclick="closeRecycleBinRemarks()" class="rounded-lg px-2 py-1 text-2xl leading-none text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-700 dark:hover:text-white" aria-label="Close">&times;</button>
            </div>
            <label for="recycle-bin-modal-remarks" class="mt-4 block text-sm font-medium text-gray-700 dark:text-gray-200">Remarks <span class="text-gray-500">(optional)</span></label>
            <textarea id="recycle-bin-modal-remarks" rows="4" maxlength="1000" class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-red-500 focus:ring-2 focus:ring-red-200 dark:border-gray-600 dark:bg-gray-900 dark:text-white" placeholder="Optional reason for permanent deletion"></textarea>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" onclick="closeRecycleBinRemarks()" class="rounded-lg bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600">Cancel</button>
                <button type="button" onclick="confirmRecycleBinRemarks()" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Permanently delete</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let pendingRecycleBinDelete = null;

function openRecycleBinRemarks(action) {
    pendingRecycleBinDelete = action;
    const modal = document.getElementById('recycle-bin-remarks-modal');
    const textarea = document.getElementById('recycle-bin-modal-remarks');
    const message = document.getElementById('recycle-bin-remarks-message');
    const labels = {
        users: 'user',
        devices: 'equipment',
        maintenance_plans: 'PM Plan',
        locations: 'location',
        offices: 'office',
        staff: 'staff member',
    };
    const label = labels[action.type] || 'record';
    if (message) message.textContent = action.empty
        ? 'This will permanently delete every deleted user, equipment, location, office, staff member, and PM Plan record in the recycle bin.'
        : action.selectAll
            ? `This will permanently delete every deleted ${label} record in the recycle bin.`
        : 'This action permanently deletes the selected recycle-bin records and cannot be undone.';
    if (textarea) textarea.value = '';
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        window.setTimeout(() => textarea?.focus(), 30);
    }
}

function closeRecycleBinRemarks() {
    const modal = document.getElementById('recycle-bin-remarks-modal');
    modal?.classList.add('hidden');
    modal?.classList.remove('flex');
    pendingRecycleBinDelete = null;
}

function confirmRecycleBinRemarks() {
    const textarea = document.getElementById('recycle-bin-modal-remarks');
    const reason = String(textarea?.value || '').trim();
    const action = pendingRecycleBinDelete;
    closeRecycleBinRemarks();
    if (action) submitRecycleBinDelete(action.type, action.ids || [], action.selectAll, reason, action.empty === true);
}

function restoreSelectedRecycleBin(type) {
    const boxes = [...document.querySelectorAll('[data-bin-checkbox="' + type + '"]:checked')];
    if (boxes.length === 0) {
        alert('Select at least one deleted record to restore.');
        return;
    }

    const labels = {
        users: 'user',
        devices: 'equipment',
        maintenance_plans: 'PM Plan',
        locations: 'location',
        offices: 'office',
        staff: 'staff member',
    };
    const label = labels[type] || 'record';
    if (!confirm(`Restore ${boxes.length} selected ${label} record(s) from the recycle bin?`)) return;

    const form = document.getElementById('recycle-bin-restore-selected-form');
    if (!form) return;
    form.querySelectorAll('input[name="ids[]"]').forEach((input) => input.remove());
    document.getElementById('recycle-bin-restore-type').value = type;
    boxes.forEach((box) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = box.value;
        form.appendChild(input);
    });
    form.submit();
}

function submitRecycleBinDelete(type, ids, selectAll, reason, emptyAll = false) {
    const form = document.getElementById('recycle-bin-permanent-delete-form');
    form.querySelectorAll('input[name="ids[]"], input[name="empty"]').forEach(input => input.remove());
    document.getElementById('recycle-bin-delete-type').value = type;
    document.getElementById('recycle-bin-delete-select-all').value = selectAll ? '1' : '0';
    document.getElementById('recycle-bin-delete-remarks').value = reason;

    if (!selectAll) {
        ids.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = id;
            form.appendChild(input);
        });
    }

    if (emptyAll) {
        const empty = document.createElement('input');
        empty.type = 'hidden';
        empty.name = 'empty';
        empty.value = '1';
        form.appendChild(empty);
    }

    form.submit();
}

function permanentDeleteSelected(type) {
    const boxes = [...document.querySelectorAll('[data-bin-checkbox="' + type + '"]:checked')];
    const selectAll = document.getElementById('delete-all-' + type)?.checked === true;
    if (!selectAll && boxes.length === 0) {
        alert('Select at least one record or choose the delete-all option.');
        return;
    }
    openRecycleBinRemarks({ type, ids: boxes.map(box => box.value), selectAll, empty: false });
}

function permanentDeleteAll(type) {
    openRecycleBinRemarks({ type, ids: [], selectAll: true, empty: false });
}

function permanentDeleteSingle(type, id) {
    openRecycleBinRemarks({ type, ids: [String(id)], selectAll: false, empty: false });
}

function emptyRecycleBin() {
    openRecycleBinRemarks({ type: 'all', ids: [], selectAll: true, empty: true });
}

function toggleRecycleBinSelection(type, checked) {
    document.querySelectorAll('[data-bin-checkbox="' + type + '"]').forEach((box) => {
        box.checked = checked;
    });
    syncRecycleBinSelectAll(type);
}

function syncRecycleBinSelectAll(type) {
    const master = document.getElementById('delete-all-' + type);
    const boxes = [...document.querySelectorAll('[data-bin-checkbox="' + type + '"]')];
    const selected = boxes.filter((box) => box.checked).length;
    if (master?.checked && boxes.some((box) => !box.checked)) master.checked = false;
    document.getElementById('recycle-actions-' + type)?.classList.toggle('hidden', selected === 0 && !master?.checked);
}

document.getElementById('recycle-bin-remarks-modal')?.addEventListener('click', (event) => {
    if (event.target.id === 'recycle-bin-remarks-modal') closeRecycleBinRemarks();
});
</script>
@endpush
@endsection
