@extends('admin.layouts.app')

@section('title', 'Equipment History')
@section('page_title', 'Equipment History')

@section('content')
<div class="space-y-5">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Equipment History</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ $device->type?->name ?? 'Equipment' }} | Property #: {{ $device->property_number }}
        </p>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
            <h2 class="font-semibold text-gray-900 dark:text-white">Checks and Maintenance</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left dark:bg-gray-900/40">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Date</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Type</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Condition</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Remarks</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Checked By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($records as $record)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $record->maintenance_date?->format('M d, Y') }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $record->maintenance_type }}</td>
                            <td class="px-4 py-3 capitalize text-gray-700 dark:text-gray-300">{{ $record->condition ?: ($device->condition ?: 'serviceable') }}</td>
                            <td class="max-w-md px-4 py-3 text-gray-700 dark:text-gray-300">{{ $record->remarks ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $record->checkedBy?->name ?? $record->checkedBy?->email ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">No maintenance records yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
            <h2 class="font-semibold text-gray-900 dark:text-white">End User and Location History</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left dark:bg-gray-900/40">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Issued</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Returned</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">End User</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Office / Location</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Remarks</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Issued By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($assignments as $assignment)
                        @php
                            $staff = $assignment->staff;
                            $office = $staff?->office;
                            $location = $office?->location;
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">{{ $assignment->issued_at?->format('M d, Y h:i A') ?? '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">{{ $assignment->returned_at?->format('M d, Y h:i A') ?? 'Current' }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $staff ? trim($staff->first_name . ' ' . $staff->last_name) : 'Staff deleted' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $office?->name ?? '-' }}@if($location) / {{ $location->name }}@endif</td>
                            <td class="max-w-md px-4 py-3 text-gray-700 dark:text-gray-300">{{ $assignment->remarks ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $assignment->issuer?->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">No issuance or relocation history yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($activityLogs->isNotEmpty())
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <h2 class="font-semibold text-gray-900 dark:text-white">Equipment Updates</h2>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($activityLogs as $log)
                    <div class="px-4 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-medium capitalize text-gray-900 dark:text-white">{{ $log->action }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $log->created_at?->format('M d, Y h:i A') }}</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $log->description }}</p>
                        @if($log->action === 'relocated' && is_array($log->changes))
                            @php $summary = $log->summary; @endphp
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ data_get($summary, 'from_end_user.value') }} ({{ data_get($summary, 'from_office.value') ?: 'No office' }} / {{ data_get($summary, 'from_location.value') ?: 'No location' }})
                                →
                                {{ data_get($summary, 'to_end_user.value') }} ({{ data_get($summary, 'to_office.value') ?: 'No office' }} / {{ data_get($summary, 'to_location.value') ?: 'No location' }})
                            </p>
                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">Remarks: {{ data_get($summary, 'remarks.value') ?: '-' }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
