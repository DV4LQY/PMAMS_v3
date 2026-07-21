@extends('admin.layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')

@section('content')
<style>
    .dashboard-quick-actions > a,
    .dashboard-quick-actions > button {
        min-height: 4.75rem;
        width: 100%;
    }

    .dashboard-quick-actions .quick-action-text {
        flex: 1 1 0%;
        min-width: 0;
    }

    .dashboard-quick-actions .quick-action-text span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    html.dark .dashboard-quick-actions a:hover,
    html.dark .dashboard-quick-actions button:hover {
        background-color: #1e293b !important;
        border-color: #475569 !important;
        color: #f8fafc !important;
    }

    html.dark .dashboard-quick-actions a:hover span,
    html.dark .dashboard-quick-actions button:hover span,
    html.dark .dashboard-quick-actions a:hover svg,
    html.dark .dashboard-quick-actions button:hover svg {
        color: #f8fafc !important;
    }

    html.dark .dashboard-quick-actions a:hover span span:last-child,
    html.dark .dashboard-quick-actions button:hover span span:last-child {
        color: #cbd5e1 !important;
    }
</style>
<div
    x-data="{
        addDeviceOpen: {{ ($errors->any() || request('action') === 'add-equipment') ? 'true' : 'false' }},
        addTypeId: '{{ old('device_type_id', $types->first()?->id) }}',
        addOsVersion: @js(old('os_version', '')),
        addMsVersion: @js(old('ms_office_version', '')),

        typeNames: @js($types->pluck('name', 'id')),

        syncAddEquipmentType() {
            const select = this.$el.querySelector('[data-equipment-type-select]');

            if (select) {
                this.addTypeId = String(select.value || '');
            }
        },

        openAddEquipment() {
            this.addDeviceOpen = true;
            window.pmamsOpenModal?.('dashboard-add-equipment-modal');
            this.$nextTick(() => this.syncAddEquipmentType());
        },

        getTypeName(typeId) {
            const key = String(typeId ?? '');
            const mappedName = this.typeNames?.[key];

            if (mappedName) {
                return String(mappedName).trim().toLowerCase();
            }

            const select = this.$el.querySelector('[data-equipment-type-select]');
            return String(select?.options[select.selectedIndex]?.textContent || '')
                .trim()
                .toLowerCase();
        },

        isComputerType(typeId) {
            const name = this.getTypeName(typeId);
            return name === 'desktop' || name === 'laptop';
        },

        isDesktopType(typeId) {
            return this.getTypeName(typeId) === 'desktop';
        },

        formatUnitPriceValue(value) {
            value = String(value ?? '').replace(/[^0-9.]/g, '');

            let parts = value.split('.');
            let whole = parts.shift() || '';
            let decimals = parts.length ? '.' + parts.join('').slice(0, 2) : '';

            whole = whole.replace(/^0+(?=\d)/, '');
            whole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            return whole + decimals;
        },

        formatUnitPriceInput(event) {
            event.target.value = this.formatUnitPriceValue(event.target.value);
        },

        cleanUnitPrices(form) {
            form.querySelectorAll('.unit-price-input').forEach((input) => {
                input.value = String(input.value ?? '').replace(/,/g, '');
            });
        }
    }"
    x-init="$nextTick(() => { $el.querySelectorAll('.unit-price-input').forEach((input) => input.value = $data.formatUnitPriceValue(input.value)); $data.syncAddEquipmentType(); })"
    class="space-y-6"
>
    {{-- Page Header --}}
    <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-semibold text-gray-900">Dashboard</h1>
        <p class="text-sm text-gray-500">Overview of equipment inventory, issuing activity, and recent maintenance records.</p>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <div class="font-semibold">Please check the form.</div>
            <ul class="mt-1 list-inside list-disc">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
        <a href="{{ route('admin.devices.index') }}" class="rounded-2xl border-l-4 border-blue-500 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <p class="text-xs font-semibold uppercase tracking-widest text-blue-500">Total Equipment</p>
            <div class="mt-2 text-4xl font-bold text-gray-900">{{ number_format($totalDevices ?? 0) }}</div>
            <p class="mt-1 text-sm text-gray-400">All registered equipment</p>
        </a>
        <a href="{{ route('admin.devices.index', ['status' => 'available']) }}" class="rounded-2xl border-l-4 border-emerald-500 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <p class="text-xs font-semibold uppercase tracking-widest text-emerald-500">Available</p>
            <div class="mt-2 text-4xl font-bold text-gray-900">{{ number_format($availableDevices ?? 0) }}</div>
            <p class="mt-1 text-sm text-gray-400">Ready to be issued</p>
        </a>
        <a href="{{ route('admin.devices.index', ['status' => 'issued']) }}" class="rounded-2xl border-l-4 border-indigo-500 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <p class="text-xs font-semibold uppercase tracking-widest text-indigo-500">Issued</p>
            <div class="mt-2 text-4xl font-bold text-gray-900">{{ number_format($issuedDevices ?? 0) }}</div>
            <p class="mt-1 text-sm text-gray-400">Assigned to staff</p>
        </a>
        <a href="{{ route('admin.devices.index', ['condition' => 'serviceable']) }}" class="rounded-2xl border-l-4 border-green-500 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <p class="text-xs font-semibold uppercase tracking-widest text-green-500">Serviceable</p>
            <div class="mt-2 text-4xl font-bold text-gray-900">{{ number_format($serviceableDevices ?? 0) }}</div>
            <p class="mt-1 text-sm text-gray-400">Working condition</p>
        </a>
        <a href="{{ route('admin.devices.index', ['condition' => 'unserviceable']) }}" class="rounded-2xl border-l-4 border-red-500 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <p class="text-xs font-semibold uppercase tracking-widest text-red-500">Unserviceable</p>
            <div class="mt-2 text-4xl font-bold text-gray-900">{{ number_format($unserviceableDevices ?? 0) }}</div>
            <p class="mt-1 text-sm text-gray-400">Needs checking</p>
        </a>
        <a href="{{ route('admin.devices.index', ['condition' => 'condemned']) }}" class="rounded-2xl border-l-4 border-gray-500 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">Condemned</p>
            <div class="mt-2 text-4xl font-bold text-gray-900">{{ number_format($condemnedDevices ?? 0) }}</div>
            <p class="mt-1 text-sm text-gray-400">For disposal</p>
        </a>
    </div>

    {{-- Quick Actions --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="mb-4">
            <h2 class="text-base font-semibold text-gray-900">Quick Actions</h2>
            <p class="mt-1 text-sm text-gray-500">Common tasks you may need to access quickly.</p>
        </div>
        <div class="dashboard-quick-actions grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <a href="{{ route('admin.dashboard', ['action' => 'add-equipment']) }}" data-open-add-equipment-modal data-open-modal="dashboard-add-equipment-modal" @click.prevent="openAddEquipment()" class="group flex items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 text-left transition hover:-translate-y-0.5 hover:border-blue-200 hover:bg-blue-50 hover:shadow-sm">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700 transition group-hover:bg-blue-600 group-hover:text-white">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                </span>
                <span class="quick-action-text min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-gray-900">Add Equipment</span>
                    <span class="block text-xs text-gray-500">Register item</span>
                </span>
            </a>
            <a href="{{ route('admin.devices.index') }}" class="group flex items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 text-left transition hover:-translate-y-0.5 hover:border-slate-200 hover:bg-slate-50 hover:shadow-sm">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700 transition group-hover:bg-slate-700 group-hover:text-white">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 7h18M3 12h18M3 17h18"/></svg>
                </span>
                <span class="quick-action-text min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-gray-900">View Equipment</span>
                    <span class="block text-xs text-gray-500">Inventory list</span>
                </span>
            </a>
            <a href="{{ route('admin.locations.index', ['action' => 'add']) }}" class="group flex items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 text-left transition hover:-translate-y-0.5 hover:border-emerald-200 hover:bg-emerald-50 hover:shadow-sm">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 transition group-hover:bg-emerald-600 group-hover:text-white">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s7-4.5 7-11a7 7 0 0 0-14 0c0 6.5 7 11 7 11Z"/><circle cx="12" cy="10" r="2"/></svg>
                </span>
                <span class="quick-action-text min-w-0 flex-1"><span class="block text-sm font-semibold text-gray-900">Add Location</span><span class="block text-xs text-gray-500">Open locations</span></span>
            </a>
            @if(auth()->user()?->isSuperAdmin())
            <a href="{{ route('admin.users.index', ['action' => 'add']) }}" class="group flex items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 text-left transition hover:-translate-y-0.5 hover:border-indigo-200 hover:bg-indigo-50 hover:shadow-sm">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700 transition group-hover:bg-indigo-600 group-hover:text-white">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M16 11h6"/></svg>
                </span>
                <span class="quick-action-text min-w-0 flex-1"><span class="block text-sm font-semibold text-gray-900">Add User</span><span class="block text-xs text-gray-500">Manage accounts</span></span>
            </a>
            @endif
            <a href="{{ route('admin.reports.assets') }}" class="group flex items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 text-left transition hover:-translate-y-0.5 hover:border-amber-200 hover:bg-amber-50 hover:shadow-sm">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700 transition group-hover:bg-amber-500 group-hover:text-white">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
                </span>
                <span class="quick-action-text min-w-0 flex-1"><span class="block text-sm font-semibold text-gray-900">Export Inventory</span><span class="block text-xs text-gray-500">Print report</span></span>
            </a>
            <a href="{{ route('admin.scanner', ['start' => 1]) }}" class="group flex items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 text-left transition hover:-translate-y-0.5 hover:border-purple-200 hover:bg-purple-50 hover:shadow-sm">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-purple-100 text-purple-700 transition group-hover:bg-purple-600 group-hover:text-white">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 8h1M11 8h1M16 8h1M7 12h1M11 12h5M7 16h5M16 16h1"/></svg>
                </span>
                <span class="quick-action-text min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-gray-900">Scan QR Code</span>
                    <span class="block text-xs text-gray-500">Start scanner</span>
                </span>
            </a>
        </div>
    </div>

    {{-- Charts 2x2 --}}
    <div
        class="grid grid-cols-1 gap-6 xl:grid-cols-2"
        data-admin-dashboard-charts
        data-chart-data="{{ json_encode([
            'condition' => ['labels' => array_keys($devicesByCondition ?? []), 'values' => array_values($devicesByCondition ?? [])],
            'availability' => ['labels' => array_keys($devicesByAvailability ?? []), 'values' => array_values($devicesByAvailability ?? [])],
            'type' => ['labels' => ($devicesByType ?? collect())->keys()->values()->all(), 'values' => ($devicesByType ?? collect())->values()->all()],
            'office' => ['labels' => ($devicesByOffice ?? collect())->keys()->values()->all(), 'values' => ($devicesByOffice ?? collect())->values()->all()],
            'maintenance' => ['labels' => ($maintenanceSemiannually ?? collect())->keys()->values()->all(), 'values' => ($maintenanceSemiannually ?? collect())->values()->all()]
        ]) }}"
    >

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Equipment by Condition</h2>
            <p class="mt-1 mb-4 text-sm text-gray-500">Current condition breakdown.</p>
            <div style="position:relative; height:250px;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Equipment by Type</h2>
            <p class="mt-1 mb-4 text-sm text-gray-500">Distribution across equipment categories.</p>
            <div style="position:relative; height:250px;">
                <canvas id="typeChart"></canvas>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Equipment by Office</h2>
            <p class="mt-1 mb-4 text-sm text-gray-500">Issued equipment per office.</p>
            <div style="position:relative; height:250px;">
                <canvas id="officeChart"></canvas>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Total Equipment Registered</h2>
            <p class="mt-1 mb-4 text-sm text-gray-500">Available and issued equipment breakdown.</p>
            <div style="position:relative; height:250px;"><canvas id="totalEquipmentChart"></canvas></div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Equipment Maintained Semiannually</h2>
            <p class="mt-1 mb-4 text-sm text-gray-500">Number of completed maintenance checklists per six-month period.</p>
            <div style="position:relative; height:250px;"><canvas id="maintenanceChart"></canvas></div>
        </div>

    </div>

    {{-- Recent Tables --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        {{-- Recent Issued Equipment --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Recent Issued Equipment</h2>
                    <p class="mt-1 text-sm text-gray-500">Latest equipment assigned to staff.</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Equipment</th>
                            <th class="px-5 py-3 font-semibold">Issued To</th>
                            <th class="px-5 py-3 font-semibold">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($recentIssuedDevices as $assignment)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-4">
                                    @if($assignment->device)
                                        <a href="{{ route('admin.devices.show', $assignment->device) }}" class="font-medium text-blue-600 hover:underline">{{ $assignment->device->property_number }}</a>
                                        <div class="mt-1 text-xs text-gray-500">{{ $assignment->device->type?->name ?? 'Equipment' }}@if($assignment->device->serial_number) • SN: {{ $assignment->device->serial_number }}@endif</div>
                                    @else
                                        <span class="text-gray-400">Equipment deleted</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-gray-700">
                                    @if($assignment->staff)
                                        <div class="font-medium text-gray-900">{{ $assignment->staff->last_name }}, {{ $assignment->staff->first_name }}</div>
                                        <div class="mt-1 text-xs text-gray-500">{{ $assignment->staff->office?->name ?? 'No office' }}</div>
                                    @else
                                        <span class="text-gray-400">Staff deleted</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-gray-700">{{ $assignment->issued_at ? $assignment->issued_at->format('M d, Y') : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-8 text-center text-gray-500">No issued equipment yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Recent Maintenance Records --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Recent Maintenance Records</h2>
                    <p class="mt-1 text-sm text-gray-500">Latest checked or maintained equipment.</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Equipment</th>
                            <th class="px-5 py-3 font-semibold">Date</th>
                            <th class="px-5 py-3 font-semibold">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($recentMaintenanceRecords as $record)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-4">
                                    @if($record->device)
                                        <a href="{{ route('admin.devices.show', $record->device) }}" class="font-medium text-blue-600 hover:underline">{{ $record->device->property_number }}</a>
                                        <div class="mt-1 text-xs text-gray-500">{{ $record->device->type?->name ?? 'Equipment' }}@if($record->device->serial_number) • SN: {{ $record->device->serial_number }}@endif</div>
                                    @else
                                        <span class="text-gray-400">Equipment deleted</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-gray-700">{{ $record->maintenance_date ? $record->maintenance_date->format('M d, Y') : '-' }}</td>
                                <td class="px-5 py-4 text-gray-700"><div class="max-w-xs truncate">{{ $record->remarks ?: '-' }}</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-8 text-center text-gray-500">No maintenance records yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Add Equipment Modal --}}
    <div id="dashboard-add-equipment-modal" role="dialog" aria-modal="true" x-show="addDeviceOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-black/50 px-4">
        <div @click.self="addDeviceOpen = false" class="flex min-h-full items-center justify-center py-4 sm:py-6">
            <div class="relative flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Add Equipment</h2>
                        <p class="mt-1 text-sm text-gray-500">Register new equipment in the inventory.</p>
                    </div>
                    <button type="button" data-native-modal-close="dashboard-add-equipment-modal" @click="addDeviceOpen = false" class="rounded-lg px-3 py-1 text-xl text-gray-500 hover:bg-gray-100 hover:text-gray-700">&times;</button>
                </div>

                <form method="POST" action="{{ route('admin.devices.store') }}" enctype="multipart/form-data" class="flex min-h-0 flex-1 flex-col" x-on:submit="cleanUnitPrices($event.target)">
                    @csrf
                    <input type="hidden" name="form_context" value="add_equipment">
                    <input type="hidden" name="status" value="available">
                    <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                        @include('admin.devices._add-equipment-fields')
                    </div>
                    <div class="flex shrink-0 justify-end gap-2 border-t border-gray-200 px-6 py-4">
                        <button type="button" data-native-modal-close="dashboard-add-equipment-modal" @click="addDeviceOpen = false" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Cancel</button>
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save Equipment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    new Chart(document.getElementById('statusChart'), {
        type: 'bar',
        data: {
            labels: @json(array_keys($devicesByCondition ?? [])),
            datasets: [{
                label: 'Equipment',
                data: @json(array_values($devicesByCondition ?? [])),
                backgroundColor: ['#22c55e', '#ef4444', '#6b7280'],
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    new Chart(document.getElementById('totalEquipmentChart'), {
        type: 'pie',
        data: {
            labels: @json(array_keys($devicesByAvailability ?? [])),
            datasets: [{
                data: @json(array_values($devicesByAvailability ?? [])),
                backgroundColor: ['#10b981', '#6366f1'],
                borderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { padding: 12, boxWidth: 12 } } }
        }
    });

    new Chart(document.getElementById('typeChart'), {
        type: 'doughnut',
        data: {
            labels: @json(($devicesByType ?? collect())->keys()),
            datasets: [{
                data: @json(($devicesByType ?? collect())->values()),
                backgroundColor: ['#3b82f6','#6366f1','#22c55e','#f59e0b','#ef4444','#14b8a6','#ec4899','#8b5cf6'],
                borderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { padding: 12, boxWidth: 12 } } }
        }
    });

    new Chart(document.getElementById('officeChart'), {
        type: 'bar',
        data: {
            labels: @json(($devicesByOffice ?? collect())->keys()),
            datasets: [{
                label: 'Issued Equipment',
                data: @json(($devicesByOffice ?? collect())->values()),
                backgroundColor: '#6366f1',
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    new Chart(document.getElementById('maintenanceChart'), {
        type: 'bar',
        data: {
            labels: @json(($maintenanceSemiannually ?? collect())->keys()),
            datasets: [{
                label: 'Maintained Equipment',
                data: @json(($maintenanceSemiannually ?? collect())->values()),
                backgroundColor: '#0ea5e9',
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
</script>
@endpush

@endsection
