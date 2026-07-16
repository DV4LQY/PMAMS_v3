@extends('admin.layouts.app')

@section('title', 'Equipment')
@section('page_title', 'Equipment')

@section('breadcrumbs')
    <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Dashboard</a>
    <span>/</span>
    <span class="font-medium text-gray-800 dark:text-gray-100">Equipment</span>
@endsection

@section('content')
<div
    x-data="{
        addOpen: false,
        editOpen: false,
        issueOpen: false,
        deleteOpen: false,
        bulkDeleteOpen: false,
        importOpen: false,
        selectedDeviceIds: [],

        addTypeId: '{{ old('device_type_id', $types->first()?->id) }}',
        addComputerName: @js(old('computer_name', old('specs.computer_name', ''))),
        addOsVersion: @js(old('os_version', '')),
        addMsVersion: @js(old('ms_office_version', '')),

        typeNames: @js($types->pluck('name', 'id')),
        staffOptions: @js($staffOptions ?? []),

        issueDevice: {
            id: null,
            property_number: '',
            type: '',
            issue_url: ''
        },
        issueStaffQuery: '',
        issueStaffId: '',
        issueRemarks: '',

        editDevice: {
            id: null,
            device_type_id: '',
            property_number: '',
            serial_number: '',
            computer_name: '',
            brand: '',
            model: '',
            mac_address: '',
            unit_price: '',
            date_acquired: '',
            last_maintenance_date: '',
            maintenance_remarks: '',
            status: 'available',
            condition: 'serviceable',
            os_version: '',
            os_license: '',
            ms_office_version: '',
            ms_office_license: '',
            specs: {
                computer_name: '',
                memory: '',
                storage: '',
                form_factor: ''
            }
        },

        deleteDeviceId: null,

        pageDeviceIds: @js($devices->pluck('id')->values()),

        getTypeName(typeId) {
            return (this.typeNames[typeId] || '').toLowerCase();
        },

        isComputerType(typeId) {
            const name = this.getTypeName(typeId);
            return name === 'desktop' || name === 'laptop';
        },

        isDesktopType(typeId) {
            return this.getTypeName(typeId) === 'desktop';
        },

        filteredStaffOptions() {
            const query = this.issueStaffQuery.trim().toLowerCase();

            return this.staffOptions
                .filter((staff) => !query || staff.search.includes(query))
                .slice(0, 10);
        },

        selectedIssueStaff() {
            return this.staffOptions.find((staff) => String(staff.id) === String(this.issueStaffId));
        },

        selectIssueStaff(staff) {
            this.issueStaffId = staff.id;
            this.issueStaffQuery = staff.label;
        },

        formatUnitPriceValue(value) {
            value = String(value ?? '').replace(/[^0-9.]/g, '');

            const parts = value.split('.');
            let whole = parts.shift() || '';
            const decimals = parts.length ? '.' + parts.join('').slice(0, 2) : '';

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
        },

        openEdit(device) {
            device.specs = device.specs ?? {};
            device.specs.computer_name = device.specs.computer_name ?? '';
            device.specs.memory = device.specs.memory ?? '';
            device.specs.storage = device.specs.storage ?? '';
            device.specs.form_factor = device.specs.form_factor ?? '';

            device.computer_name = device.computer_name ?? device.specs.computer_name ?? '';
            device.serial_number = device.serial_number ?? '';
            device.status = device.status ?? 'available';
            device.condition = device.condition ?? 'serviceable';
            device.os_version = device.os_version ?? '';
            device.os_license = device.os_license ?? '';
            device.ms_office_version = device.ms_office_version ?? '';
            device.ms_office_license = device.ms_office_license ?? '';

            this.editDevice = device;
            this.editDevice.unit_price = this.formatUnitPriceValue(this.editDevice.unit_price);
            this.editOpen = true;
        },

        openIssue(device) {
            this.issueDevice = device;
            this.issueStaffQuery = '';
            this.issueStaffId = '';
            this.issueRemarks = '';
            this.issueOpen = true;
            this.$nextTick(() => this.$refs.issueStaffSearch?.focus());
        },

        openDelete(id) {
            this.deleteDeviceId = id;
            this.deleteOpen = true;
            this.$nextTick(() => this.$refs.confirmDeleteBtn?.focus());
        },

        toggleAllDevices(checked) {
            this.selectedDeviceIds = checked
                ? this.pageDeviceIds.map((id) => String(id))
                : [];
        },

        clearSelection() {
            this.selectedDeviceIds = [];
            this.bulkDeleteOpen = false;
        }
    }"
    x-init="$nextTick(() => $el.querySelectorAll('.unit-price-input').forEach((input) => input.value = formatUnitPriceValue(input.value)))"
    class="space-y-5"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Equipment</h1>
        </div>

        <div class="flex flex-wrap gap-2">
            @if(auth()->user()->isAdmin() || auth()->user()->isUnitHead())
                <button
                    type="button"
                    class="inline-flex shrink-0 items-center rounded-xl bg-amber-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-600"
                    x-on:click="importOpen = true"
                >
                    Import Equipment
                </button>
            @endif

            <a
                href="{{ route('admin.devices.qr.index') }}"
                data-no-spa="true"
                class="inline-flex shrink-0 items-center rounded-xl bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700 dark:bg-green-500 dark:hover:bg-green-600"
            >
                Generate QR
            </a>

            <a
                href="{{ route('admin.reports.preventiveMaintenance.export', request()->query()) }}"
                data-no-spa="true"
                class="inline-flex shrink-0 items-center rounded-xl bg-violet-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-violet-700 dark:bg-violet-500 dark:hover:bg-violet-600"
            >
                Export Excel Report
            </a>

            <button
                type="button"
                class="inline-flex shrink-0 items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                x-on:click="addOpen = true"
            >
                + Add Equipment
            </button>
        </div>
    </div>

    @if($errors->any())
        <div class="rounded-xl bg-red-100 px-4 py-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400">
            <div class="font-semibold">Please check the form.</div>
            <ul class="mt-1 list-inside list-disc">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Filters --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <form method="GET" class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="w-full lg:w-44">
                <select
                    name="type"
                    onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"
                    class="w-full truncate rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:ring-blue-900/40"
                >
                    <option value="" @selected(empty($typeId))>All Equipment Types</option>
                    @foreach($types as $type)
                        <option value="{{ $type->id }}" @selected(($typeId ?? '') == $type->id)>
                            {{ $type->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            @if(isset($colleges))
                <div class="w-full lg:w-44">
                    <select
                        name="college"
                        onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"
                        class="w-full truncate rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:ring-blue-900/40"
                    >
                        <option value="" @selected(empty($collegeId))>All Locations</option>
                        @foreach($colleges as $college)
                            <option value="{{ $college->id }}" @selected(($collegeId ?? '') == $college->id)>
                                {{ $college->code }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="w-full lg:w-44">
                <select
                    name="status"
                    onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"
                    class="w-full truncate rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:ring-blue-900/40"
                >
                    <option value="" @selected(empty($status))>All Statuses</option>
                    <option value="available" @selected(($status ?? '') === 'available')>Available</option>
                    <option value="issued" @selected(($status ?? '') === 'issued')>Issued</option>
                    <option value="repair" @selected(($status ?? '') === 'repair')>Repair</option>
                    <option value="retired" @selected(($status ?? '') === 'retired')>Retired</option>
                </select>
            </div>

            <div class="w-full lg:w-44">
                <select
                    name="condition"
                        onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"
                    class="w-full truncate rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:ring-blue-900/40"
                >
                    <option value="" @selected(empty($condition))>All Conditions</option>
                    <option value="serviceable" @selected(($condition ?? '') === 'serviceable')>Serviceable</option>
                    <option value="unserviceable" @selected(($condition ?? '') === 'unserviceable')>Unserviceable</option>
                    <option value="condemned" @selected(($condition ?? '') === 'condemned')>Condemned</option>
                </select>
            </div>

            <input
                name="q"
                value="{{ $q ?? '' }}"
                placeholder="Search property #, serial #..."
                class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 dark:focus:ring-blue-900/40"
            >

            <div class="flex gap-2">
                <button
                    type="submit"
                    class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                >
                    Search
                </button>

                <a
                    href="{{ route('admin.devices.index') }}"
                    class="inline-flex items-center rounded-xl bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                >
                    Reset
                </a>
            </div>
        </form>
    </div>

    @if(auth()->user()->isAdmin() || auth()->user()->isUnitHead())
        <div
            x-cloak
            x-show="selectedDeviceIds.length > 0"
            class="flex flex-col gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 sm:flex-row sm:items-center sm:justify-between dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200"
        >
            <span><strong x-text="selectedDeviceIds.length"></strong> equipment selected on this page.</span>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-lg bg-red-600 px-3 py-2 font-medium text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600"
                    x-on:click="bulkDeleteOpen = true"
                >
                    Delete selected
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-white px-3 py-2 font-medium text-red-700 hover:bg-red-100 dark:bg-gray-800 dark:text-red-300 dark:hover:bg-gray-700"
                    x-on:click="clearSelection()"
                >
                    Clear
                </button>
            </div>
        </div>
    @endif

    {{-- Mobile cards --}}
    <div class="grid grid-cols-1 gap-3 md:hidden">
        @forelse($devices as $d)
            @php
                $deviceTypeName = strtolower($d->type?->name ?? '');
                $isDesktop = $deviceTypeName === 'desktop';
                $isComputerDevice = in_array($deviceTypeName, ['desktop', 'laptop'], true);
            @endphp

            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                @if(auth()->user()->isAdmin() || auth()->user()->isUnitHead())
                    <label class="mb-3 inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input
                            type="checkbox"
                            class="h-5 w-5 rounded border-gray-300 text-red-600 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-700"
                            value="{{ $d->id }}"
                            x-model="selectedDeviceIds"
                            aria-label="Select equipment {{ $d->property_number }}"
                        >
                        Select for bulk deletion
                    </label>
                @endif

                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <div class="text-gray-500 dark:text-gray-400">Type</div>
                        <div class="font-semibold text-gray-900 dark:text-white">{{ $d->type?->name ?? '-' }}</div>
                    </div>

                    <div>
                        <div class="text-gray-500 dark:text-gray-400">Property #</div>
                        <div class="text-gray-900 dark:text-white">{{ $d->property_number }}</div>
                    </div>

                    <div>
                        <div class="text-gray-500 dark:text-gray-400">Serial #</div>
                        <div class="text-gray-900 dark:text-white">{{ $d->serial_number ?: '-' }}</div>
                    </div>

                    <div>
                        <div class="text-gray-500 dark:text-gray-400">Acquired</div>
                        <div class="text-gray-900 dark:text-white">
                            {{ $d->date_acquired ? $d->date_acquired->format('M d, Y') : '-' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-gray-500 dark:text-gray-400">Condition</div>
                        <div class="capitalize text-gray-900 dark:text-white">{{ $d->condition ?? 'serviceable' }}</div>
                    </div>

                    <div>
                        <div class="text-gray-500 dark:text-gray-400">Last Maintenance</div>
                        <div class="text-gray-900 dark:text-white">
                            {{ $d->last_maintenance_date ? $d->last_maintenance_date->format('M d, Y') : 'Not yet checked' }}
                        </div>
                    </div>

                    @if($isComputerDevice)
                        <div>
                            <div class="text-gray-500 dark:text-gray-400">Computer Name</div>
                            <div class="text-gray-900 dark:text-white">
                                {{ ($d->computer_name ?? data_get($d->specs, 'computer_name', '-')) ?: '-' }}
                            </div>
                        </div>

                        <div>
                            <div class="text-gray-500 dark:text-gray-400">MAC Address</div>
                            <div class="text-gray-900 dark:text-white">{{ $d->mac_address ?: '-' }}</div>
                        </div>

                        <div>
                            <div class="text-gray-500 dark:text-gray-400">Memory</div>
                            <div class="text-gray-900 dark:text-white">{{ data_get($d->specs, 'memory', '-') ?: '-' }}</div>
                        </div>

                        <div>
                            <div class="text-gray-500 dark:text-gray-400">Storage</div>
                            <div class="text-gray-900 dark:text-white">{{ data_get($d->specs, 'storage', '-') ?: '-' }}</div>
                        </div>

                        @if($isDesktop)
                            <div>
                                <div class="text-gray-500 dark:text-gray-400">Form Factor</div>
                                <div class="text-gray-900 dark:text-white">{{ data_get($d->specs, 'form_factor', '-') ?: '-' }}</div>
                            </div>
                        @endif
                    @endif
                </div>

                @if($d->maintenance_remarks)
                    <div class="mt-3 text-sm">
                        <div class="text-gray-500 dark:text-gray-400">Maintenance Remarks</div>
                        <div class="text-gray-900 dark:text-white">{{ $d->maintenance_remarks }}</div>
                    </div>
                @endif

                <div class="mt-4 flex flex-wrap gap-2">
                    <a
                        href="{{ route('admin.devices.show', $d) }}"
                        class="rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700 dark:bg-green-500 dark:hover:bg-green-600"
                    >
                        View
                    </a>

                    <a
                        href="{{ route('admin.devices.history', $d) }}"
                        class="rounded-lg bg-purple-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-purple-700 dark:bg-purple-500 dark:hover:bg-purple-600"
                    >
                        History
                    </a>

                    <a
                        href="{{ route('admin.devices.checklist.form', $d) }}"
                        class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                    >
                        Mark Checked
                    </a>

                    @if(($d->status ?? 'available') === 'available' && !$d->currentAssignment)
                        <button
                            type="button"
                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-600"
                            x-on:click="openIssue({
                                id: {{ $d->id }},
                                property_number: @js($d->property_number),
                                type: @js($d->type?->name ?? 'Equipment'),
                                issue_url: @js(route('admin.devices.issue', $d))
                            })"
                        >
                            Issue
                        </button>
                    @else
                        <span class="rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-300">
                            {{ ucfirst($d->status ?? 'issued') }}
                        </span>
                    @endif

                    <button
                        type="button"
                        class="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-black dark:bg-gray-600 dark:hover:bg-gray-500"
                        x-on:click="openEdit({
                            id: {{ $d->id }},
                            device_type_id: '{{ $d->device_type_id }}',
                            computer_name: @js($d->computer_name ?? data_get($d->specs, 'computer_name', '')),
                            property_number: @js($d->property_number),
                            serial_number: @js($d->serial_number ?? ''),
                            brand: @js($d->brand ?? ''),
                            model: @js($d->model ?? ''),
                            mac_address: @js($d->mac_address ?? ''),
                            unit_price: @js($d->unit_price ?? ''),
                            date_acquired: @js($d->date_acquired ? $d->date_acquired->format('Y-m-d') : ''),
                            last_maintenance_date: @js($d->last_maintenance_date ? $d->last_maintenance_date->format('Y-m-d') : ''),
                            maintenance_remarks: @js($d->maintenance_remarks ?? ''),
                            status: @js($d->status ?? 'available'),
                            condition: @js($d->condition ?? 'serviceable'),
                            os_version: @js($d->os_version ?? ''),
                            os_license: @js($d->os_license ?? ''),
                            ms_office_version: @js($d->ms_office_version ?? ''),
                            ms_office_license: @js($d->ms_office_license ?? ''),
                            specs: {
                                computer_name: @js(data_get($d->specs, 'computer_name', '')),
                                os: @js(data_get($d->specs, 'os', '')),
                                memory: @js(data_get($d->specs, 'memory', '')),
                                storage: @js(data_get($d->specs, 'storage', '')),
                                form_factor: @js(data_get($d->specs, 'form_factor', ''))
                            }
                        })"
                    >
                        Edit
                    </button>

                    @if(auth()->user()->isAdmin() || auth()->user()->isUnitHead())
                        <button
                            type="button"
                            class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600"
                            x-on:click="openDelete({{ $d->id }})"
                        >
                            Delete
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-gray-200 bg-white p-6 text-center text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                No equipment found.
            </div>
        @endforelse
    </div>

    {{-- Desktop table --}}
    <div class="hidden overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm md:block dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left dark:bg-gray-900/40">
                    <tr>
                        @if(auth()->user()->isAdmin() || auth()->user()->isUnitHead())
                            <th class="w-12 px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    class="h-5 w-5 rounded border-gray-300 text-red-600 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-700"
                                    x-on:change="toggleAllDevices($event.target.checked)"
                                    aria-label="Select all equipment on this page"
                                >
                            </th>
                        @endif
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Type</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Property #</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Serial #</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Acquired</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Last Maintenance</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Condition</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($devices as $d)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            @if(auth()->user()->isAdmin() || auth()->user()->isUnitHead())
                                <td class="px-4 py-3 align-top">
                                    <input
                                        type="checkbox"
                                        class="h-5 w-5 rounded border-gray-300 text-red-600 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-700"
                                        value="{{ $d->id }}"
                                        x-model="selectedDeviceIds"
                                        aria-label="Select equipment {{ $d->property_number }}"
                                    >
                                </td>
                            @endif
                            <td class="px-4 py-3 text-gray-900 dark:text-white">{{ $d->type?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-900 dark:text-white">{{ $d->property_number }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $d->serial_number ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                {{ $d->date_acquired ? $d->date_acquired->format('M d, Y') : '-' }}
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                @if($d->last_maintenance_date)
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ $d->last_maintenance_date->format('M d, Y') }}
                                    </div>
                                    @if($d->maintenance_remarks)
                                        <div class="max-w-xs truncate text-xs text-gray-500 dark:text-gray-400">
                                            {{ $d->maintenance_remarks }}
                                        </div>
                                    @endif
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">Not yet checked</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 capitalize text-gray-700 dark:text-gray-300">
                                {{ $d->condition ?? 'serviceable' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <a
                                        href="{{ route('admin.devices.show', $d) }}"
                                        class="rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700 dark:bg-green-500 dark:hover:bg-green-600"
                                    >
                                        View
                                    </a>

                                    <a
                                        href="{{ route('admin.devices.history', $d) }}"
                                        class="rounded-lg bg-purple-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-purple-700 dark:bg-purple-500 dark:hover:bg-purple-600"
                                    >
                                        History
                                    </a>

                                    <a
                                        href="{{ route('admin.devices.checklist.form', $d) }}"
                                        class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                                    >
                                        Mark Checked
                                    </a>

                                    @if(($d->status ?? 'available') === 'available' && !$d->currentAssignment)
                                        <button
                                            type="button"
                                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-600"
                                            x-on:click="openIssue({
                                                id: {{ $d->id }},
                                                property_number: @js($d->property_number),
                                                type: @js($d->type?->name ?? 'Equipment'),
                                                issue_url: @js(route('admin.devices.issue', $d))
                                            })"
                                        >
                                            Issue
                                        </button>
                                    @else
                                        <span class="rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-300">
                                            {{ ucfirst($d->status ?? 'issued') }}
                                        </span>
                                    @endif

                                    <button
                                        type="button"
                                        class="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-black dark:bg-gray-600 dark:hover:bg-gray-500"
                                        x-on:click="openEdit({
                                            id: {{ $d->id }},
                                            device_type_id: '{{ $d->device_type_id }}',
                                            computer_name: @js($d->computer_name ?? data_get($d->specs, 'computer_name', '')),
                                            property_number: @js($d->property_number),
                                            serial_number: @js($d->serial_number ?? ''),
                                            brand: @js($d->brand ?? ''),
                                            model: @js($d->model ?? ''),
                                            mac_address: @js($d->mac_address ?? ''),
                                            unit_price: @js($d->unit_price ?? ''),
                                            date_acquired: @js($d->date_acquired ? $d->date_acquired->format('Y-m-d') : ''),
                                            last_maintenance_date: @js($d->last_maintenance_date ? $d->last_maintenance_date->format('Y-m-d') : ''),
                                            maintenance_remarks: @js($d->maintenance_remarks ?? ''),
                                            status: @js($d->status ?? 'available'),
                                            condition: @js($d->condition ?? 'serviceable'),
                                            os_version: @js($d->os_version ?? ''),
                                            os_license: @js($d->os_license ?? ''),
                                            ms_office_version: @js($d->ms_office_version ?? ''),
                                            ms_office_license: @js($d->ms_office_license ?? ''),
                                            specs: {
                                                computer_name: @js(data_get($d->specs, 'computer_name', '')),
                                                os: @js(data_get($d->specs, 'os', '')),
                                                memory: @js(data_get($d->specs, 'memory', '')),
                                                storage: @js(data_get($d->specs, 'storage', '')),
                                                form_factor: @js(data_get($d->specs, 'form_factor', ''))
                                            }
                                        })"
                                    >
                                        Edit
                                    </button>

                                    @if(auth()->user()->isAdmin() || auth()->user()->isUnitHead())
                                        <button
                                            type="button"
                                            class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600"
                                            x-on:click="openDelete({{ $d->id }})"
                                        >
                                            Delete
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ (auth()->user()->isAdmin() || auth()->user()->isUnitHead()) ? 8 : 7 }}" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                No equipment found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3">
            {{ $devices->links() }}
        </div>
    </div>

    {{-- Shared computer name options --}}
    <datalist id="computer_name_options">
        @foreach($computerNames ?? [] as $computerName)
            @php
                $computerNameValue = is_object($computerName)
                    ? ($computerName->name ?? $computerName->computer_name ?? $computerName->title ?? '')
                    : $computerName;
            @endphp

            @if($computerNameValue)
                <option value="{{ $computerNameValue }}"></option>
            @endif
        @endforeach
    </datalist>

    {{-- Add modal --}}
    <x-modal show="addOpen" title="Add Equipment" max-width="max-w-4xl">
        <form method="POST" action="{{ route('admin.devices.store') }}" enctype="multipart/form-data" class="space-y-4" x-on:submit="cleanUnitPrices($event.target)">
            @csrf
            <input type="hidden" name="status" value="available">

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium dark:text-gray-300">Equipment Type</label>
                    <select
                        name="device_type_id"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        required
                        x-model="addTypeId"
                    >
                        @foreach($types as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium dark:text-gray-300">Property Number</label>
                    <input
                        name="property_number"
                        value="{{ old('property_number') }}"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        required
                        maxlength="50"
                        pattern="[A-Za-z0-9][A-Za-z0-9\-\/]*"
                        title="Letters, numbers, hyphens, and slashes only"
                        placeholder="e.g. PN-2026-0001"
                    >
                </div>

                <div>
                    <label class="text-sm font-medium dark:text-gray-300">Serial Number</label>
                    <input
                        name="serial_number"
                        value="{{ old('serial_number') }}"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        maxlength="100"
                        pattern="[A-Za-z0-9\-]*"
                        title="Letters, numbers, and hyphens only"
                        placeholder="Enter serial number"
                    >
                </div>

                <div x-show="isComputerType(addTypeId)" x-cloak>
                    <label class="text-sm font-medium dark:text-gray-300">Computer Name</label>
                    <input
                        list="computer_name_options"
                        name="computer_name"
                        x-model="addComputerName"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        maxlength="100"
                        placeholder="Select or type computer name"
                        :disabled="!isComputerType(addTypeId)"
                    >
                    <input
                        type="hidden"
                        name="specs[computer_name]"
                        x-model="addComputerName"
                        :disabled="!isComputerType(addTypeId)"
                    >
                </div>

                <div>
                    <label class="text-sm font-medium dark:text-gray-300">Brand</label>
                    <input
                        name="brand"
                        value="{{ old('brand') }}"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        maxlength="100"
                        pattern="[A-Za-zÑñ0-9][A-Za-zÑñ0-9.\-\s]*"
                        title="Letters and numbers only"
                        placeholder="e.g. HP, Dell, ASUS"
                    >
                </div>

                <div>
                    <label class="text-sm font-medium dark:text-gray-300">Model</label>
                    <input
                        name="model"
                        value="{{ old('model') }}"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        maxlength="100"
                        pattern="[A-Za-z0-9][A-Za-z0-9.\-\/\s]*"
                        title="Letters and numbers only"
                        placeholder="Example: Epson L3110, Acer Aspire"
                    >
                </div>

                <div x-show="isComputerType(addTypeId)" x-cloak>
                    <label class="text-sm font-medium dark:text-gray-300">MAC Address</label>
                    <input
                        name="mac_address"
                        value="{{ old('mac_address') }}"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        maxlength="17"
                        pattern="[0-9A-Fa-f]{2}(:[0-9A-Fa-f]{2}){5}"
                        title="Format: 00:1A:2B:3C:4D:5E"
                        placeholder="00:1A:2B:3C:4D:5E"
                        :disabled="!isComputerType(addTypeId)"
                    >
                </div>

                <div x-show="isComputerType(addTypeId)" x-cloak>
                    <label class="text-sm font-medium dark:text-gray-300">Memory</label>
                    <input
                        name="specs[memory]"
                        value="{{ old('specs.memory') }}"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        maxlength="50"
                        placeholder="Example: 8GB RAM"
                        :disabled="!isComputerType(addTypeId)"
                    >
                </div>

                <div x-show="isComputerType(addTypeId)" x-cloak>
                    <label class="text-sm font-medium dark:text-gray-300">Storage</label>
                    <input
                        name="specs[storage]"
                        value="{{ old('specs.storage') }}"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        maxlength="50"
                        placeholder="Example: 256GB SSD / 1TB HDD"
                        :disabled="!isComputerType(addTypeId)"
                    >
                </div>

                <div x-show="isDesktopType(addTypeId)" x-cloak>
                    <label class="text-sm font-medium dark:text-gray-300">Form Factor</label>
                    <select
                        name="specs[form_factor]"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        :disabled="!isDesktopType(addTypeId)"
                    >
                        <option value="">-- Select Form Factor --</option>
                        <option value="Tower Desktops" @selected(old('specs.form_factor') === 'Tower Desktops')>Tower Desktops</option>
                        <option value="Small Form Factor (SFF) Desktops" @selected(old('specs.form_factor') === 'Small Form Factor (SFF) Desktops')>Small Form Factor (SFF) Desktops</option>
                        <option value="All-in-One (AIO) Desktops" @selected(old('specs.form_factor') === 'All-in-One (AIO) Desktops')>All-in-One (AIO) Desktops</option>
                        <option value="Mini PCs" @selected(old('specs.form_factor') === 'Mini PCs')>Mini PCs</option>
                        <option value="Workstations" @selected(old('specs.form_factor') === 'Workstations')>Workstations</option>
                    </select>
                </div>

                <div x-show="isComputerType(addTypeId)" x-cloak>
                    <label class="text-sm font-medium dark:text-gray-300">OS Version</label>
                    <select
                        name="os_version"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        x-model="addOsVersion"
                        :disabled="!isComputerType(addTypeId)"
                    >
                        <option value="">-- Select OS --</option>
                        <option value="Windows 7">Windows 7</option>
                        <option value="Windows 8">Windows 8</option>
                        <option value="Windows 10">Windows 10</option>
                        <option value="Windows 11">Windows 11</option>
                    </select>
                </div>

                <div x-show="isComputerType(addTypeId) && addOsVersion" x-cloak>
                    <label class="text-sm font-medium dark:text-gray-300">OS License</label>
                    <select
                        name="os_license"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        :disabled="!isComputerType(addTypeId) || !addOsVersion"
                    >
                        <option value="">-- Select License --</option>
                        <option value="Cracked" @selected(old('os_license') === 'Cracked')>Cracked</option>
                        <option value="OEM Licensed" @selected(old('os_license') === 'OEM Licensed')>OEM Licensed</option>
                    </select>
                </div>

                <div x-show="isComputerType(addTypeId)" x-cloak>
                    <label class="text-sm font-medium dark:text-gray-300">MS Office Version</label>
                    <select
                        name="ms_office_version"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        x-model="addMsVersion"
                        :disabled="!isComputerType(addTypeId)"
                    >
                        <option value="">-- Select MS Office --</option>
                        <option value="Office 2007">Office 2007</option>
                        <option value="Office 2010">Office 2010</option>
                        <option value="Office 2013">Office 2013</option>
                        <option value="Office 2016">Office 2016</option>
                        <option value="Office 2019">Office 2019</option>
                        <option value="Office 2021">Office 2021</option>
                        <option value="Microsoft 365">Microsoft 365</option>
                    </select>
                </div>

                <div x-show="isComputerType(addTypeId) && addMsVersion" x-cloak>
                    <label class="text-sm font-medium dark:text-gray-300">MS Office License</label>
                    <select
                        name="ms_office_license"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        :disabled="!isComputerType(addTypeId) || !addMsVersion"
                    >
                        <option value="">-- Select License --</option>
                        <option value="Cracked" @selected(old('ms_office_license') === 'Cracked')>Cracked</option>
                        <option value="OEM Licensed" @selected(old('ms_office_license') === 'OEM Licensed')>OEM Licensed</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium dark:text-gray-300">Unit Price</label>
                    <input
                        name="unit_price"
                        value="{{ old('unit_price') }}"
                        type="text"
                        inputmode="decimal"
                        placeholder="e.g. 25,000.00"
                        class="unit-price-input mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        x-on:input="formatUnitPriceInput($event)"
                    >
                </div>

                <div>
                    <label class="text-sm font-medium dark:text-gray-300">Date Acquired</label>
                    <input
                        name="date_acquired"
                        value="{{ old('date_acquired') }}"
                        type="date"
                        max="{{ now()->format('Y-m-d') }}"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                </div>

                <div>
                    <label class="text-sm font-medium dark:text-gray-300">Condition</label>
                    <select name="condition" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="serviceable" @selected(old('condition', 'serviceable') === 'serviceable')>Serviceable</option>
                <option value="unserviceable" @selected(old('condition') === 'unserviceable')>Unserviceable</option>
                <option value="condemned" @selected(old('condition') === 'condemned')>Condemned</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium dark:text-gray-300">Last Maintenance Date</label>
                    <input
                        name="last_maintenance_date"
                        value="{{ old('last_maintenance_date') }}"
                        type="date"
                        max="{{ now()->format('Y-m-d') }}"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                </div>

                @include('admin.devices._photo-input', ['photoInputId' => 'add_equipment_photo'])
            </div>

            <div>
                <label class="text-sm font-medium dark:text-gray-300">Maintenance Remarks</label>
                <textarea
                    name="maintenance_remarks"
                    rows="3"
                    maxlength="1000"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    placeholder="Example: Initial check, cleaned, inspected"
                >{{ old('maintenance_remarks') }}</textarea>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600">
                    Save
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                    x-on:click="addOpen = false"
                >
                    Cancel
                </button>
            </div>
        </form>
    </x-modal>

    {{-- Edit modal --}}
    <x-modal show="editOpen" title="Edit Equipment" max-width="max-w-4xl">
        <form method="POST" :action="`{{ url('/admin/devices') }}/${editDevice.id}`" enctype="multipart/form-data" class="space-y-4" x-on:submit="cleanUnitPrices($event.target)">
            @csrf
            @method('PUT')
            <input type="hidden" name="status" x-model="editDevice.status">

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium dark:text-gray-300">Equipment Type</label>
                    <select
                        name="device_type_id"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        required
                        x-model="editDevice.device_type_id"
                    >
                        @foreach($types as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium dark:text-gray-300">Property Number</label>
                    <input
                        name="property_number"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        x-model="editDevice.property_number"
                        required
                        maxlength="50"
                        pattern="[A-Za-z0-9][A-Za-z0-9\-\/]*"
                        title="Letters, numbers, hyphens, and slashes only"
                    >
                </div>

                <div>
                    <label class="text-sm font-medium dark:text-gray-300">Serial Number</label>
                    <input
                        name="serial_number"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        x-model="editDevice.serial_number"
                        maxlength="100"
                        pattern="[A-Za-z0-9\-]*"
                        title="Letters, numbers, and hyphens only"
                    >
                </div>

                <div x-show="isComputerType(editDevice.device_type_id)" x-cloak>
                    <label class="text-sm font-medium dark:text-gray-300">Computer Name</label>
                    <input
                        list="computer_name_options"
                        name="computer_name"
                        x-model="editDevice.computer_name"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        maxlength="100"
                        placeholder="Select or type computer name"
                        :disabled="!isComputerType(editDevice.device_type_id)"
                    >
                    <input
                        type="hidden"
                        name="specs[computer_name]"
                        x-model="editDevice.computer_name"
                        :disabled="!isComputerType(editDevice.device_type_id)"
                    >
                </div>

                <div>
                    <label class="text-sm font-medium dark:text-gray-300">Brand</label>
                    <input
                        name="brand"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        x-model="editDevice.brand"
                        maxlength="100"
                        pattern="[A-Za-zÑñ0-9][A-Za-zÑñ0-9.\-\s]*"
                        title="Letters and numbers only"
                    >
                </div>

                <div>
                    <label class="text-sm font-medium dark:text-gray-300">Model</label>
                    <input
                        name="model"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        x-model="editDevice.model"
                        maxlength="100"
                        pattern="[A-Za-z0-9][A-Za-z0-9.\-\/\s]*"
                        title="Letters and numbers only"
                    >
                </div>

                <div x-show="isComputerType(editDevice.device_type_id)" x-cloak>
                    <label class="text-sm font-medium dark:text-gray-300">MAC Address</label>
                    <input
                        name="mac_address"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        x-model="editDevice.mac_address"
                        maxlength="17"
                        pattern="[0-9A-Fa-f]{2}(:[0-9A-Fa-f]{2}){5}"
                        title="Format: 00:1A:2B:3C:4D:5E"
                        :disabled="!isComputerType(editDevice.device_type_id)"
                    >
                </div>

                <div x-show="isComputerType(editDevice.device_type_id)" x-cloak>
                    <label class="text-sm font-medium dark:text-gray-300">Memory</label>
                    <input
                        name="specs[memory]"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        x-model="editDevice.specs.memory"
                        maxlength="50"
                        :disabled="!isComputerType(editDevice.device_type_id)"
                    >
                </div>

                <div x-show="isComputerType(editDevice.device_type_id)" x-cloak>
                    <label class="text-sm font-medium dark:text-gray-300">Storage</label>
                    <input
                        name="specs[storage]"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        x-model="editDevice.specs.storage"
                        maxlength="50"
                        :disabled="!isComputerType(editDevice.device_type_id)"
                    >
                </div>

                <div x-show="isDesktopType(editDevice.device_type_id)" x-cloak>
                    <label class="text-sm font-medium dark:text-gray-300">Form Factor</label>
                    <select
                        name="specs[form_factor]"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        x-model="editDevice.specs.form_factor"
                        :disabled="!isDesktopType(editDevice.device_type_id)"
                    >
                        <option value="">-- Select Form Factor --</option>
                        <option value="Tower Desktops">Tower Desktops</option>
                        <option value="Small Form Factor (SFF) Desktops">Small Form Factor (SFF) Desktops</option>
                        <option value="All-in-One (AIO) Desktops">All-in-One (AIO) Desktops</option>
                        <option value="Mini PCs">Mini PCs</option>
                        <option value="Workstations">Workstations</option>
                    </select>
                </div>

                <div x-show="isComputerType(editDevice.device_type_id)" x-cloak>
                    <label class="text-sm font-medium dark:text-gray-300">OS Version</label>
                    <select
                        name="os_version"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        x-model="editDevice.os_version"
                        :disabled="!isComputerType(editDevice.device_type_id)"
                    >
                        <option value="">-- Select OS --</option>
                        <option value="Windows 7">Windows 7</option>
                        <option value="Windows 8">Windows 8</option>
                        <option value="Windows 10">Windows 10</option>
                        <option value="Windows 11">Windows 11</option>
                    </select>
                </div>

                <div x-show="isComputerType(editDevice.device_type_id) && editDevice.os_version" x-cloak>
                    <label class="text-sm font-medium dark:text-gray-300">OS License</label>
                    <select
                        name="os_license"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        x-model="editDevice.os_license"
                        :disabled="!isComputerType(editDevice.device_type_id) || !editDevice.os_version"
                    >
                        <option value="">-- Select License --</option>
                        <option value="Cracked">Cracked</option>
                        <option value="OEM Licensed">OEM Licensed</option>
                    </select>
                </div>

                <div x-show="isComputerType(editDevice.device_type_id)" x-cloak>
                    <label class="text-sm font-medium dark:text-gray-300">MS Office Version</label>
                    <select
                        name="ms_office_version"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        x-model="editDevice.ms_office_version"
                        :disabled="!isComputerType(editDevice.device_type_id)"
                    >
                        <option value="">-- Select MS Office --</option>
                        <option value="Office 2007">Office 2007</option>
                        <option value="Office 2010">Office 2010</option>
                        <option value="Office 2013">Office 2013</option>
                        <option value="Office 2016">Office 2016</option>
                        <option value="Office 2019">Office 2019</option>
                        <option value="Office 2021">Office 2021</option>
                        <option value="Microsoft 365">Microsoft 365</option>
                    </select>
                </div>

                <div x-show="isComputerType(editDevice.device_type_id) && editDevice.ms_office_version" x-cloak>
                    <label class="text-sm font-medium dark:text-gray-300">MS Office License</label>
                    <select
                        name="ms_office_license"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        x-model="editDevice.ms_office_license"
                        :disabled="!isComputerType(editDevice.device_type_id) || !editDevice.ms_office_version"
                    >
                        <option value="">-- Select License --</option>
                        <option value="Cracked">Cracked</option>
                        <option value="OEM Licensed">OEM Licensed</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium dark:text-gray-300">Unit Price</label>
                    <input
                        name="unit_price"
                        type="text"
                        inputmode="decimal"
                        class="unit-price-input mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        x-model="editDevice.unit_price"
                        x-on:input="formatUnitPriceInput($event)"
                    >
                </div>

                <div>
                    <label class="text-sm font-medium dark:text-gray-300">Date Acquired</label>
                    <input
                        name="date_acquired"
                        type="date"
                        max="{{ now()->format('Y-m-d') }}"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        x-model="editDevice.date_acquired"
                    >
                </div>

                <div>
                    <label class="text-sm font-medium dark:text-gray-300">Condition</label>
                    <select
                        name="condition"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        x-model="editDevice.condition"
                    >
                        <option value="serviceable">Serviceable</option>
                        <option value="unserviceable">Unserviceable</option>
                        <option value="condemned">Condemned</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium dark:text-gray-300">Last Maintenance Date</label>
                    <input
                        name="last_maintenance_date"
                        type="date"
                        max="{{ now()->format('Y-m-d') }}"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        x-model="editDevice.last_maintenance_date"
                    >
                </div>

                @include('admin.devices._photo-input', ['photoInputId' => 'edit_equipment_photo'])
            </div>

            <div>
                <label class="text-sm font-medium dark:text-gray-300">Maintenance Remarks</label>
                <textarea
                    name="maintenance_remarks"
                    rows="3"
                    maxlength="1000"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    x-model="editDevice.maintenance_remarks"
                ></textarea>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600">
                    Save Changes
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                    x-on:click="editOpen = false"
                >
                    Cancel
                </button>
            </div>
        </form>
    </x-modal>

    {{-- Issue modal --}}
    <x-modal show="issueOpen" title="Issue Equipment">
        <form method="POST" :action="issueDevice.issue_url" class="space-y-4">
            @csrf

            <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-100">
                <div class="font-semibold" x-text="issueDevice.property_number || 'Selected equipment'"></div>
                <div class="mt-1 text-emerald-700 dark:text-emerald-200" x-text="issueDevice.type || 'Equipment'"></div>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Search Staff</label>
                <input
                    type="text"
                    x-ref="issueStaffSearch"
                    x-model="issueStaffQuery"
                    x-on:input="issueStaffId = ''"
                    placeholder="Type staff name, email, office, or location..."
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 dark:focus:ring-blue-900/40"
                    autocomplete="off"
                >
                <input type="hidden" name="staff_id" :value="issueStaffId">

                <div
                    x-show="!issueStaffId"
                    class="mt-2 max-h-56 overflow-y-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
                >
                    <template x-if="!issueStaffId && filteredStaffOptions().length === 0">
                        <div class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            No staff found.
                        </div>
                    </template>

                    <template x-for="staff in filteredStaffOptions()" :key="staff.id">
                        <button
                            type="button"
                            x-on:click="selectIssueStaff(staff)"
                            class="block w-full border-b border-gray-100 px-3 py-2 text-left text-sm transition last:border-b-0 hover:bg-blue-50 dark:border-gray-700 dark:hover:bg-gray-700"
                            :class="String(issueStaffId) === String(staff.id) ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'text-gray-700 dark:text-gray-200'"
                        >
                            <span class="font-semibold" x-text="staff.name"></span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400" x-text="staff.label"></span>
                            <span class="block text-xs text-gray-400 dark:text-gray-500" x-show="staff.email" x-text="staff.email"></span>
                        </button>
                    </template>
                </div>

                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400" x-show="selectedIssueStaff()">
                    Selected: <span class="font-medium" x-text="selectedIssueStaff()?.label"></span>
                </p>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Remarks</label>
                <textarea
                    name="remarks"
                    x-model="issueRemarks"
                    rows="3"
                    maxlength="1000"
                    placeholder="Optional issuance remarks..."
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 dark:focus:ring-blue-900/40"
                ></textarea>
            </div>

            <div class="flex flex-wrap justify-end gap-2 pt-2">
                <button
                    type="button"
                    class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                    x-on:click="issueOpen = false"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-emerald-500 dark:hover:bg-emerald-600"
                    :disabled="!issueStaffId"
                >
                    Issue Equipment
                </button>
            </div>
        </form>
    </x-modal>

    {{-- Import modal --}}
    @if(auth()->user()->isAdmin() || auth()->user()->isUnitHead())
        <x-modal show="importOpen" title="Import Complete Equipment Records">
            <form method="POST" action="{{ route('admin.devices.import') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">CSV or Excel file</label>
                    <input
                        type="file"
                        name="file"
                        accept=".csv,.txt,.xlsx,.xls"
                        required
                        class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                </div>

                <div class="rounded-lg bg-blue-50 px-3 py-3 text-xs leading-5 text-blue-800 dark:bg-blue-900/20 dark:text-blue-200">
                    One file covers the complete equipment specifications and optional issuance. End users are matched by staff email first, then by name, with optional office and location_code filters. Leave the staff fields blank when equipment is not yet issued.
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 pt-2">
                    <a
                        href="{{ route('admin.devices.importTemplate') }}"
                        data-no-spa="true"
                        class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
                    >
                        Download import template
                    </a>
                    <div class="flex gap-2">
                        <button type="button" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600" x-on:click="importOpen = false">Cancel</button>
                        <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-600">Import equipment</button>
                    </div>
                </div>
            </form>
        </x-modal>
    @endif

    {{-- Bulk delete confirmation --}}
    @if(auth()->user()->isAdmin() || auth()->user()->isUnitHead())
        <x-modal show="bulkDeleteOpen" title="Delete Selected Equipment">
            <div class="space-y-4">
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Are you sure you want to delete <strong x-text="selectedDeviceIds.length"></strong> selected equipment record(s)? Their issuance and maintenance history will also be deleted.
                </p>

                <form method="POST" action="{{ route('admin.devices.bulkDestroy') }}" class="flex justify-end gap-2" x-on:submit="if (!selectedDeviceIds.length) { $event.preventDefault(); bulkDeleteOpen = false; }">
                    @csrf
                    @method('DELETE')
                    <template x-for="id in selectedDeviceIds" :key="id">
                        <input type="hidden" name="device_ids[]" :value="id">
                    </template>
                    <button type="button" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600" x-on:click="bulkDeleteOpen = false">Cancel</button>
                    <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600">Yes, delete selected</button>
                </form>
            </div>
        </x-modal>
    @endif

    {{-- Delete modal --}}
    <x-modal show="deleteOpen" title="Delete Equipment">
        <div class="space-y-3">
            <div class="text-sm text-gray-700 dark:text-gray-300">
                Are you sure you want to delete this equipment? Its issuance and maintenance history will also be deleted.
            </div>

            <form
                method="POST"
                :action="`{{ url('/admin/devices') }}/${deleteDeviceId}`"
                x-on:submit="if (!deleteDeviceId) $event.preventDefault()"
                class="flex gap-2"
            >
                @csrf
                @method('DELETE')

                <button
                    type="submit"
                    x-ref="confirmDeleteBtn"
                    class="rounded-lg bg-red-600 px-4 py-2 text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600"
                >
                    Confirm
                </button>

                <button
                    type="button"
                    class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                    x-on:click="deleteOpen = false"
                >
                    Cancel
                </button>
            </form>
        </div>
    </x-modal>
</div>
@endsection
