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

    .issuance-filter-form > div {
        min-width: 0;
    }

    .issuance-filter-reset {
        width: 100%;
    }

    @media (min-width: 1280px) {
        .issuance-filter-form {
            /* Keep every control, including Reset, inside the card at desktop widths. */
            grid-template-columns: minmax(14rem, 1.35fr) repeat(3, minmax(10rem, 1fr)) minmax(7rem, .6fr) minmax(12rem, 1fr) auto;
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
                @if($selectedSemester)
                    <p class="mt-1 text-xs font-medium text-blue-600 dark:text-blue-400">
                        Window: {{ $selectedSemester === 1 ? '1st Semi-Annually (January-June)' : '2nd Semi-Annually (July-December)' }} {{ $selectedYear }}
                    </p>
                @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if($loadReport)
                <a
                    href="{{ route('admin.reports.issuance.export', request()->query()) }}"
                    data-no-spa="true"
                    class="inline-flex items-center justify-center rounded-xl bg-emerald-500 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600"
                >
                    Export Report
                </a>
            @endif
            <a
                href="{{ route('admin.reports.index') }}"
                class="inline-flex items-center justify-center rounded-xl bg-gray-700 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600"
            >
                Back to Reports
            </a>
        </div>
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

            <input
                type="number"
                name="year"
                min="2000"
                max="2100"
                value="{{ $selectedYear }}"
                placeholder="Year"
                x-on:input="submitFilters()"
                x-on:keydown.enter.prevent="$refs.filterForm.requestSubmit()"
                class="min-w-0 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 dark:focus:ring-blue-900/40"
            >

            <select
                name="semester"
                x-on:change="$refs.filterForm.requestSubmit()"
                class="min-w-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:ring-blue-900/40"
            >
                <option value="">All semiannual windows</option>
                <option value="1" @selected($selectedSemester === 1)>1st Semi-Annually (Jan-Jun)</option>
                <option value="2" @selected($selectedSemester === 2)>2nd Semi-Annually (Jul-Dec)</option>
            </select>

             <div class="flex">
           <!--    <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                >
                    Search
                </button> -->

                <a
                    href="{{ route('admin.reports.issuance', ['load' => 1]) }}"
                    class="issuance-filter-reset inline-flex items-center justify-center rounded-xl bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                >
                    Reset
                </a>
            </div>
        </form>

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            Search applies automatically after typing. The report stays unloaded until a filter is applied or Reset is pressed.
        </p>
    </div>

    @if($loadReport)
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
                        <th class="px-4 py-3">Origin User</th>
                        <th class="px-4 py-3">Origin Office / Location</th>
                        <th class="px-4 py-3">Transferred To</th>
                        <th class="px-4 py-3">Destination Office / Location</th>
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
                            $origin = $assignment->previousAssignment();
                            $originStaff = $origin?->staff;
                            $originOffice = $origin?->office ?: $originStaff?->office;
                            $originLocation = $origin?->location ?: $originOffice?->location;
                            $office = $assignment->office ?: $staff?->office;
                            $location = $assignment->location ?: $office?->location;
                            $originName = $origin
                                ? ($originStaff ? trim(($originStaff->last_name ?? '') . ', ' . ($originStaff->first_name ?? '')) : 'Shared / Location assignment')
                                : 'Initial issue / inventory';
                            $destinationName = $staff ? trim(($staff->last_name ?? '') . ', ' . ($staff->first_name ?? '')) : 'Shared / Location assignment';
                            $equipmentName = trim(($device?->brand ?? '') . ' ' . ($device?->model ?? '')) ?: ($device?->type?->name ?? 'Equipment');
                        @endphp

                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $originName }}</div>
                                @if($originStaff)<div class="text-xs text-gray-500 dark:text-gray-400">{{ $originStaff->position ?: $originStaff->email }}</div>@endif
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                {{ $origin ? ($originOffice?->name ?? '-') : 'Inventory' }}
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $origin ? ($originLocation?->code ?: ($originLocation?->name ?? '-')) : '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $destinationName }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $staff?->position ?: $staff?->email }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                {{ $office?->name ?? '-' }}
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $location?->code ?: ($location?->name ?? '-') }}</div>
                            </td>
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
                            <td colspan="10" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
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
    @else
        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-6 py-12 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Issuance report is ready</h2>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Apply a filter or press Reset to load issued equipment.</p>
        </div>
    @endif
</div>
@endsection
