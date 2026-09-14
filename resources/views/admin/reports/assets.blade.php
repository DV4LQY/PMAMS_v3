@extends('admin.layouts.app')

@section('title', 'All Assets Report')
@section('page_title', 'All Assets Report')

@section('breadcrumbs')
    <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Dashboard</a>
    <span class="dark:text-gray-500">/</span>
    <a href="{{ route('admin.reports.index') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Reports</a>
    <span class="dark:text-gray-500">/</span>
    <span class="font-medium text-gray-800 dark:text-gray-200">All Assets</span>
@endsection

@section('content')
<div class="space-y-5">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Choose a filter or press Reset to load the asset records.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if($loadReport)
                <a
                    href="{{ route('admin.reports.assets.export', request()->query()) }}"
                    data-no-spa="true"
                    class="no-print inline-flex items-center justify-center rounded-xl bg-emerald-500 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600"
                >
                    Export Report
                </a>
            @endif
            <a
                href="{{ route('admin.reports.index') }}"
                class="no-print inline-flex items-center justify-center rounded-xl bg-gray-700 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600"
            >
                Back to Reports
            </a>
        </div>
    </div>

    <div class="no-print rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <form
            id="asset-filter-form"
            method="GET"
            class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-8"
        >
            <x-search-field
                id="asset-search"
                name="q"
                :value="$q"
                placeholder="Search property #, serial #, brand..."
                ariaLabel="Search assets"
                wrapperClass="xl:col-span-2"
                inputClass="w-full rounded-lg border border-gray-300 px-3 py-2 pr-20 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:placeholder-gray-500 dark:focus:border-blue-500 dark:focus:ring-blue-900"
            />

            <select
                id="asset-type-filter"
                name="type_id"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:focus:border-blue-500 dark:focus:ring-blue-900"
            >
                <option value="">All equipment types</option>
                @foreach($types as $type)
                    <option value="{{ $type->id }}" @selected((int) $selectedTypeId === $type->id)>
                        {{ $type->name }}
                    </option>
                @endforeach
            </select>

            <select
                id="asset-maintenance-status-filter"
                name="maintenance_status"
                aria-label="Maintenance status"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:focus:border-blue-500 dark:focus:ring-blue-900"
            >
                <option value="">All maintenance status</option>
                <option value="maintained" @selected($maintenanceStatus === 'maintained')>Maintained</option>
                <option value="not_maintained" @selected($maintenanceStatus === 'not_maintained')>Not maintained</option>
            </select>

            <select
                id="asset-maintenance-semester-filter"
                name="semester"
                aria-label="Semi-annual maintenance period"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:focus:border-blue-500 dark:focus:ring-blue-900"
            >
                <option value="">All semi-annual periods</option>
                <option value="1" @selected((int) $maintenanceSemester === 1)>Jan-Jun</option>
                <option value="2" @selected((int) $maintenanceSemester === 2)>Jul-Dec</option>
            </select>

            <input
                id="asset-maintenance-year-filter"
                name="year"
                type="number"
                min="2000"
                max="2100"
                value="{{ $maintenanceYear ?: '' }}"
                placeholder="Maintenance year"
                aria-label="Maintenance year"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:placeholder-gray-500 dark:focus:border-blue-500 dark:focus:ring-blue-900"
            >

            <label
                for="asset-pm-plan-scope-filter"
                class="flex items-start gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 dark:border-gray-600 dark:text-gray-200 xl:col-span-2"
            >
                <input
                    id="asset-pm-plan-scope-filter"
                    name="pm_plan_scope"
                    value="1"
                    type="checkbox"
                    @checked($pmPlanScopeOnly)
                    class="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-800"
                >
                <span>
                    <span class="block font-medium">PM Plan scope only</span>
                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">Active Desktop/Laptop targets in published plans</span>
                </span>
            </label>

            <select
                id="asset-college-filter"
                name="college_id"
                aria-label="Location"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:focus:border-blue-500 dark:focus:ring-blue-900"
            >
                <option value="">All locations</option>
                @foreach($colleges as $college)
                    <option value="{{ $college->id }}" @selected((int) $selectedCollegeId === $college->id)>
                        {{ $college->code ? $college->code . ' — ' : '' }}{{ $college->name }}
                    </option>
                @endforeach
            </select>

            <select
                id="asset-office-filter"
                name="office_id"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:focus:border-blue-500 dark:focus:ring-blue-900"
            >
                <option value="">All offices</option>
                @foreach($offices as $office)
                    <option
                        value="{{ $office->id }}"
                        data-college-id="{{ $office->location_id }}"
                        @selected((int) $selectedOfficeId === $office->id)
                    >
                        {{ $office->name }} @if($office->college) — {{ $office->college->code ?: $office->college->name }} @endif
                    </option>
                @endforeach
            </select>

            <div class="flex gap-2 xl:col-span-2">
                <a
                    href="{{ route('admin.reports.assets', ['load' => 1]) }}"
                    class="inline-flex items-center rounded-xl bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    Reset
                </a>
            </div>
        </form>

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            Press Enter or select the search icon to apply the keyword. Maintenance status can be narrowed by equipment type, semi-annual period, and year. PM Plan scope only limits results to active Desktop/Laptop targets and uses each plan's effective checklist cycle. Other filters submit when changed. The report stays unloaded until a filter is applied or Reset is pressed.
        </p>
    </div>

    @if($loadReport)
    <div id="print-area" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
            <div>
                <h2 class="font-semibold text-gray-900 dark:text-gray-100">Assets</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ number_format($devices->total()) }} result(s)
                    <span class="mx-1" aria-hidden="true">·</span>
                    {{ $maintenanceStatus === 'maintained' ? 'Maintained' : ($maintenanceStatus === 'not_maintained' ? 'Not maintained' : 'All maintenance status') }}
                    <span class="mx-1" aria-hidden="true">·</span>
                    {{ $maintenancePeriodLabel }}
                    @if($pmPlanScopeOnly)
                        <span class="mx-1" aria-hidden="true">·</span>
                        PM Plan scope only
                    @endif
                </p>
            </div>

        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Property #</th>
                        <th class="px-4 py-3">Serial #</th>
                        <th class="px-4 py-3">Brand / Model</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Condition</th>
                        <th class="px-4 py-3">Maintenance</th>
                        <th class="px-4 py-3">Unit Price</th>
                        <th class="px-4 py-3">Location</th>
                        <th class="px-4 py-3">Office</th>
                        <th class="px-4 py-3">Assigned To</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($devices as $device)
                        @php
                            $assignmentContext = $device->effectiveAssignmentContext();
                            $assignment = $assignmentContext['assignment'];
                            $staff = $assignmentContext['staff'];
                            $deploymentOffice = $device->deployedOffice ?: $device->parentProperty?->deployedOffice;
                            $deploymentLocation = $device->deployedLocation
                                ?: $deploymentOffice?->location
                                ?: $device->parentProperty?->deployedLocation
                                ?: $device->parentProperty?->deployedOffice?->location;
                            $office = $assignmentContext['office'] ?: $deploymentOffice;
                            $college = $assignmentContext['location'] ?: $deploymentLocation ?: $office?->college;
                            $staffName = $staff
                                ? trim(($staff->last_name ?? '') . ', ' . ($staff->first_name ?? ''))
                                : ($assignment?->location ? 'Location assignment' : '-');
                            $effectiveUnitPrice = $device->effectiveUnitPrice();
                            $effectiveMaintenanceDate = $device->effectiveLastMaintenanceDate();
                        @endphp

                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $device->type?->name ?? '-' }}</td>

                            <td class="px-4 py-3 font-medium text-blue-700 dark:text-blue-400">
                                <a href="{{ route('admin.devices.show', $device) }}" class="hover:underline">
                                    {{ $device->property_number }}
                                </a>
                            </td>

                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $device->serial_number ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ trim(($device->brand ?? '') . ' ' . ($device->model ?? '')) ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300 capitalize">{{ $device->status ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300 capitalize">{{ $device->condition ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                @if($effectiveMaintenanceDate)
                                    <span class="font-medium text-emerald-700 dark:text-emerald-300">Maintained</span>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $effectiveMaintenanceDate->format('M d, Y') }}</div>
                                @else
                                    <span class="font-medium text-amber-700 dark:text-amber-300">Not maintained</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $effectiveUnitPrice !== null && $effectiveUnitPrice !== '' ? number_format((float) $effectiveUnitPrice, 2) : '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $college?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $office?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $staffName ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                No assets found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="no-print border-t border-gray-200 px-5 py-4 dark:border-gray-700">
            {{ $devices->links() }}
        </div>
    </div>
    @else
        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-6 py-12 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900/50">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Asset report is ready</h2>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Apply a filter or press Reset to load the equipment records.</p>
        </div>
    @endif
</div>

<script>
    (function () {
        const form = document.getElementById('asset-filter-form');
        const typeFilter = document.getElementById('asset-type-filter');
        const maintenanceStatusFilter = document.getElementById('asset-maintenance-status-filter');
        const maintenanceSemesterFilter = document.getElementById('asset-maintenance-semester-filter');
        const maintenanceYearFilter = document.getElementById('asset-maintenance-year-filter');
        const pmPlanScopeFilter = document.getElementById('asset-pm-plan-scope-filter');
        const collegeFilter = document.getElementById('asset-college-filter');
        const officeFilter = document.getElementById('asset-office-filter');

        if (!form) return;

        function submitNow() {
            form.requestSubmit ? form.requestSubmit() : form.submit();
        }

        function filterOfficeOptions() {
            if (!collegeFilter || !officeFilter) return;

            const selectedCollegeId = collegeFilter.value;

            Array.from(officeFilter.options).forEach((option) => {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                const optionCollegeId = option.getAttribute('data-college-id');
                option.hidden = selectedCollegeId && optionCollegeId !== selectedCollegeId;
            });

            const selectedOption = officeFilter.options[officeFilter.selectedIndex];

            if (selectedOption && selectedOption.hidden) {
                officeFilter.value = '';
            }
        }

        filterOfficeOptions();

        [typeFilter, maintenanceStatusFilter, maintenanceSemesterFilter, maintenanceYearFilter, pmPlanScopeFilter, collegeFilter, officeFilter].forEach((control) => {
            if (!control) return;

            control.addEventListener('change', function () {
                if (control === collegeFilter) {
                    filterOfficeOptions();
                }

                submitNow();
            });
        });
    })();
</script>

<style>
    @media print {
        body * {
            visibility: hidden !important;
        }

        #print-area,
        #print-area * {
            visibility: visible !important;
        }

        #print-area {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            background: #ffffff !important;
            color: #000000 !important;
        }

        .no-print {
            display: none !important;
        }

        @page {
            size: A4 landscape;
            margin: 10mm;
        }
    }
</style>
@endsection
