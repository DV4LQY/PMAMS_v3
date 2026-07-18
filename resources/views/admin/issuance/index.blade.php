@extends('admin.layouts.app')

@section('title', 'Issuance')
@section('page_title', 'Issuance')

@section('breadcrumbs')
    <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Dashboard</a>
    <span class="dark:text-gray-500">/</span>
    <a href="{{ route('admin.reports.index') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Reports</a>
    <span class="dark:text-gray-500">/</span>
    <span class="font-medium text-gray-800 dark:text-gray-100">Issuance</span>
@endsection

@section('content')
<style>
    .issuance-filter-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
        gap: 0.75rem;
        align-items: center;
    }

    .issuance-filter-form > input,
    .issuance-filter-form > select {
        min-width: 0;
        width: 100%;
    }

    .issuance-filter-reset {
        width: 100%;
    }

    @media (min-width: 1280px) {
        .issuance-filter-form {
            grid-template-columns: minmax(18rem, 1.35fr) minmax(13rem, 1fr) minmax(13rem, 1fr) minmax(13rem, 1fr) auto;
        }

        .issuance-filter-reset {
            width: auto;
            white-space: nowrap;
        }
    }

    @media (max-width: 639px) {
        .issuance-filter-form {
            grid-template-columns: 1fr;
        }
    }
</style>

<div
    x-data="{
        submitTimer: null,
        submitFilters() {
            clearTimeout(this.submitTimer);
            this.submitTimer = setTimeout(() => this.$refs.filterForm.requestSubmit(), 450);
        }
    }"
    class="space-y-5"
>
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Issuance</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                End users with currently issued equipment.
            </p>
        </div>

        <a
            href="{{ route('admin.reports.issuance.export', request()->query()) }}"
            data-no-spa="true"
            class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-600"
        >
            Export Report
        </a>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <form
            x-ref="filterForm"
            method="GET"
            class="issuance-filter-form"
        >
            <input
                name="q"
                value="{{ $q }}"
                x-on:input="submitFilters()"
                x-on:keydown.enter.prevent="$refs.filterForm.requestSubmit()"
                placeholder="Auto search staff, office, property #..."
                autocomplete="off"
                class="min-w-0 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 dark:focus:ring-blue-900/40"
            >

            <select
                name="type_id"
                x-on:change="$refs.filterForm.requestSubmit()"
                class="min-w-0 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:ring-blue-900/40"
            >
                <option value="">All Equipment Types</option>
                @foreach($types as $type)
                    <option value="{{ $type->id }}" @selected((int) $selectedTypeId === $type->id)>
                        {{ $type->name }}
                    </option>
                @endforeach
            </select>

            <select
                name="location_id"
                x-on:change="$refs.filterForm.requestSubmit()"
                class="min-w-0 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:ring-blue-900/40"
            >
                <option value="">All Locations</option>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected((int) $selectedLocationId === $location->id)>
                        {{ $location->code ? $location->code . ' — ' : '' }}{{ $location->name }}
                    </option>
                @endforeach
            </select>

            <select
                name="office_id"
                x-on:change="$refs.filterForm.requestSubmit()"
                class="min-w-0 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:ring-blue-900/40"
            >
                <option value="">All Offices</option>
                @foreach($offices as $office)
                    <option value="{{ $office->id }}" @selected((int) $selectedOfficeId === $office->id)>
                        {{ $office->name }} @if($office->location) — {{ $office->location->code ?: $office->location->name }} @endif
                    </option>
                @endforeach
            </select>

             <div class="flex">
           <!--    <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                >
                    Search
                </button> -->

                <a
                    href="{{ route('admin.reports.issuance') }}"
                    class="issuance-filter-reset inline-flex items-center justify-center rounded-xl bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                >
                    Reset
                </a>
            </div>
        </form>

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            Search applies automatically after typing.
        </p>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
            <div>
                <h2 class="font-semibold text-gray-900 dark:text-gray-100">Issued Equipment</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ number_format($assignments->total()) }} active issued item(s)
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900/60 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">End User</th>
                        <th class="px-4 py-3">Office</th>
                        <th class="px-4 py-3">Location</th>
                        <th class="px-4 py-3">Equipment</th>
                        <th class="px-4 py-3">Property #</th>
                        <th class="px-4 py-3">Serial #</th>
                        <th class="px-4 py-3">Issued Date</th>
                        <th class="px-4 py-3">Issued By</th>
                        <th class="px-4 py-3">Remarks</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($assignments as $assignment)
                        @php
                            $staff = $assignment->staff;
                            $device = $assignment->device;
                            $office = $assignment->office ?: $staff?->office;
                            $location = $assignment->location ?: $office?->location;
                            $staffName = $staff ? trim(($staff->last_name ?? '') . ', ' . ($staff->first_name ?? '')) : 'Shared / Location assignment';
                            $equipmentName = trim(($device?->brand ?? '') . ' ' . ($device?->model ?? '')) ?: ($device?->type?->name ?? 'Equipment');
                        @endphp

                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $staffName }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $staff?->position ?: $staff?->email }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $office?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $location?->code ?: ($location?->name ?? '-') }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                <div>{{ $device?->type?->name ?? '-' }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $equipmentName }}</div>
                            </td>
                            <td class="px-4 py-3 font-medium text-blue-700 dark:text-blue-400">
                                @if($device)
                                    <a href="{{ route('admin.devices.show', $device) }}" class="hover:underline">
                                        {{ $device->property_number }}
                                    </a>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $device?->serial_number ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $assignment->issued_at?->format('M d, Y h:i A') ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $assignment->issuer?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                <div class="max-w-xs truncate">{{ $assignment->remarks ?: '-' }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                No issued equipment found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3">
            {{ $assignments->links() }}
        </div>
    </div>
</div>
@endsection
