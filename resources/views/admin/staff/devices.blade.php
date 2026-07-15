@extends('admin.layouts.app')

@section('title', 'Staff Equipment')
@section('page_title', 'Staff Equipment')

@section('content')
@php
    $assignments = $assignments ?? ($issued ?? collect());
    $availableDevices = $availableDevices ?? collect();

    $staffName = trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? ''));
    $staffName = $staffName !== '' ? $staffName : ($staff->name ?? 'Staff');

    $office = $staff->office ?? null;
    $location = $office?->location ?? $office?->college;

    $deviceLabel = function ($device) {
        if (! $device) {
            return 'Unknown equipment';
        }

        $parts = array_filter([
            $device->type?->name ?? 'Equipment',
            $device->property_number ? 'Property #: ' . $device->property_number : null,
            $device->serial_number ? 'Serial #: ' . $device->serial_number : null,
            trim(($device->brand ?? '') . ' ' . ($device->model ?? '')) ?: null,
        ]);

        return implode(' | ', $parts);
    };
@endphp

<div class="space-y-5">
    {{-- Page Header --}}
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                Equipment Assigned to {{ $staffName }}
            </h1>

            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $office?->name ?? 'No office assigned' }}
                @if($location)
                    <span class="mx-1">•</span>{{ $location->name }}
                @endif
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            @if($office)
                <a
                    href="{{ route('admin.staff.index', $office) }}"
                    class="inline-flex items-center rounded-xl bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                >
                    Back to Staff
                </a>
            @endif

            <a
                href="{{ route('admin.devices.index') }}"
                class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
            >
                Equipment
            </a>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-400">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-400">
            <div class="font-semibold">Please check the form.</div>
            <ul class="mt-1 list-inside list-disc">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Staff Summary + Issue Form --}}
    <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 xl:col-span-1">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Staff Information</h2>

            <dl class="mt-4 space-y-3 text-sm">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Name</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{{ $staffName }}</dd>
                </div>

                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Position</dt>
                    <dd class="text-gray-900 dark:text-white">{{ $staff->position ?: '-' }}</dd>
                </div>

                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Office</dt>
                    <dd class="text-gray-900 dark:text-white">{{ $office?->name ?? '-' }}</dd>
                </div>

                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Location</dt>
                    <dd class="text-gray-900 dark:text-white">{{ $location?->name ?? '-' }}</dd>
                </div>

                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Currently Issued</dt>
                    <dd class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $assignments->count() }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 xl:col-span-2">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Issue Equipment</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Select available equipment to assign to this staff member.
            </p>

            <form method="POST" action="{{ route('admin.staff.devices.issue', $staff) }}" class="mt-4 space-y-4">
                @csrf

                <div>
                    <label for="device_id" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Available Equipment
                    </label>

                    <select
                        id="device_id"
                        name="device_id"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:ring-blue-900/30"
                        required
                    >
                        <option value="">-- Select equipment --</option>

                        @foreach($availableDevices as $device)
                            <option value="{{ $device->id }}" @selected(old('device_id') == $device->id)>
                                {{ $deviceLabel($device) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="remarks" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Remarks <span class="font-normal text-gray-400">(optional)</span>
                    </label>

                    <textarea
                        id="remarks"
                        name="remarks"
                        rows="3"
                        maxlength="1000"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 dark:focus:ring-blue-900/30"
                        placeholder="Example: Issued for office use"
                    >{{ old('remarks') }}</textarea>
                </div>

                <div>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-blue-500 dark:hover:bg-blue-600"
                        @disabled($availableDevices->isEmpty())
                    >
                        Issue Equipment
                    </button>
                </div>
            </form>

            @if($availableDevices->isEmpty())
                <div class="mt-4 rounded-xl bg-yellow-50 px-4 py-3 text-sm text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300">
                    No available equipment can be issued right now.
                </div>
            @endif
        </div>
    </div>

    {{-- Assigned Equipment --}}
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Currently Assigned Equipment</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Active equipment assigned to this staff member.
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Equipment</th>
                        <th class="px-5 py-3 font-semibold">Property #</th>
                        <th class="px-5 py-3 font-semibold">Serial #</th>
                        <th class="px-5 py-3 font-semibold">Condition</th>
                        <th class="px-5 py-3 font-semibold">Issued Date</th>
                        <th class="px-5 py-3 font-semibold">Remarks</th>
                        <th class="px-5 py-3 font-semibold">Action</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($assignments as $assignment)
                        @php
                            $device = $assignment->device;
                            $brandModel = trim(($device?->brand ?? '') . ' ' . ($device?->model ?? ''));
                        @endphp

                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-5 py-4">
                                <div class="font-medium text-gray-900 dark:text-white">
                                    {{ $device?->type?->name ?? 'Equipment' }}
                                </div>

                                @if($brandModel !== '')
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $brandModel }}
                                    </div>
                                @endif
                            </td>

                            <td class="px-5 py-4 text-gray-700 dark:text-gray-300">
                                {{ $device?->property_number ?? '-' }}
                            </td>

                            <td class="px-5 py-4 text-gray-700 dark:text-gray-300">
                                {{ $device?->serial_number ?: '-' }}
                            </td>

                            <td class="px-5 py-4 capitalize text-gray-700 dark:text-gray-300">
                                {{ $device?->condition ?? 'serviceable' }}
                            </td>

                            <td class="px-5 py-4 text-gray-700 dark:text-gray-300">
                                {{ $assignment->issued_at ? $assignment->issued_at->format('M d, Y') : '-' }}
                            </td>

                            <td class="px-5 py-4 text-gray-700 dark:text-gray-300">
                                <div class="max-w-xs truncate">
                                    {{ $assignment->remarks ?: '-' }}
                                </div>
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    @if($device)
                                        <a
                                            href="{{ route('admin.devices.show', $device) }}"
                                            class="rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700 dark:bg-green-500 dark:hover:bg-green-600"
                                        >
                                            View
                                        </a>
                                    @endif

                                    <form method="POST" action="{{ route('admin.staff.devices.return', [$staff, $assignment]) }}">
                                        @csrf

                                        <button
                                            type="submit"
                                            class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600"
                                            onclick="return confirm('Return this equipment from {{ $staffName }}?')"
                                        >
                                            Return
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                No equipment is currently assigned to this staff member.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
