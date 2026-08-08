@extends('admin.layouts.app')

@section('title', 'Locations')
@section('page_title', 'Locations')

@section('content')
@php
    $addBag = $errors->getBag('add');
    $editBag = $errors->getBag('edit');

    $oldNames = old('names', []);
    $oldCodes = old('codes', []);
    $bulkSeedCount = $oldNames ? max(1, min(3, count($oldNames))) : 2;

    $bulkRowsSeed = [];
    for ($i = 0; $i < $bulkSeedCount; $i++) {
        $bulkRowsSeed[] = [
            'name' => $oldNames[$i] ?? '',
            'code' => $oldCodes[$i] ?? '',
            'nameError' => $addBag->first("names.$i"),
            'codeError' => $addBag->first("codes.$i"),
        ];
    }
@endphp
<script>
function registerLocationManager() {
    if (!window.Alpine) return;

    Alpine.data('locationManager', () => ({
        addOpen: {{ $addBag->any() ? 'true' : 'false' }},
        editOpen: {{ $editBag->any() ? 'true' : 'false' }},
        deleteOpen: false,
        issueOpen: false,
        bulkEnabled: {{ old('names') !== null ? 'true' : 'false' }},

        addSingle: {
            name: @js(old('name', '')),
            code: @js(old('code', '')),
            nameError: @js($addBag->first('name')),
            codeError: @js($addBag->first('code'))
        },

        bulkRows: @json($bulkRowsSeed),

        editLocation: {
            id: @js(old('editing_id') !== null ? (int) old('editing_id') : null),
            name: @js(old('name', '')),
            code: @js(old('code', '')),
            nameError: @js($editBag->first('name')),
            codeError: @js($editBag->first('code'))
        },
        deleteLocationId: null,

        issueLocation: { id: null, name: '', code: '' },
        issueOffices: [],
        issueOfficeId: '',
        issueStaffQuery: '',
        issueStaffResults: [],
        issueStaffLoading: false,
        issueStaffSearched: false,
        issueStaffId: '',
        issueStaffSelected: null,
        issueDeviceQuery: '',
        issueDeviceResults: [],
        issueDeviceLoading: false,
        issueDeviceSearched: false,
        issueDeviceId: '',
        issueDeviceSelected: null,
        issueRemarks: '',

        openAdd() {
            this.addOpen = true;
            this.bulkEnabled = false;
            this.addSingle = { name: '', code: '', nameError: '', codeError: '' };
            this.bulkRows = [
                { name: '', code: '', nameError: '', codeError: '' },
                { name: '', code: '', nameError: '', codeError: '' },
            ];
        },

        addBulkRow() {
            if (this.bulkRows.length < 3) {
                this.bulkRows.push({ name: '', code: '', nameError: '', codeError: '' });
            }
        },

        removeBulkRow() {
            if (this.bulkRows.length > 1) {
                this.bulkRows.pop();
            }
        },

        openEdit(location) {
            this.editLocation = {
                id: location.id,
                name: location.name,
                code: location.code,
                nameError: '',
                codeError: ''
            };
            this.editOpen = true;
        },

        openDelete(id) {
            this.deleteLocationId = id;
            this.deleteOpen = true;
            this.$nextTick(() => this.$refs.confirmDeleteBtn && this.$refs.confirmDeleteBtn.focus());
        },

        openIssue(location) {
            this.issueLocation = {
                id: location.id,
                name: location.name,
                code: location.code || ''
            };
            this.issueOffices = location.offices || [];
            this.issueOfficeId = '';
            this.issueStaffQuery = '';
            this.issueStaffResults = [];
            this.issueStaffSearched = false;
            this.issueStaffId = '';
            this.issueStaffSelected = null;
            this.issueDeviceQuery = '';
            this.issueDeviceResults = [];
            this.issueDeviceSearched = false;
            this.issueDeviceId = '';
            this.issueDeviceSelected = null;
            this.issueRemarks = '';
            this.issueOpen = true;
        },

        resetIssueStaff() {
            this.issueStaffQuery = '';
            this.issueStaffResults = [];
            this.issueStaffSearched = false;
            this.issueStaffId = '';
            this.issueStaffSelected = null;
        },

        async searchIssueStaff() {
            this.issueStaffId = '';
            this.issueStaffSelected = null;
            this.issueStaffResults = [];
            this.issueStaffSearched = false;

            if (!this.issueOfficeId || this.issueStaffQuery.trim().length < 2) return;

            this.issueStaffLoading = true;
            try {
                const params = new URLSearchParams({
                    q: this.issueStaffQuery.trim(),
                    office_id: String(this.issueOfficeId),
                    location_id: String(this.issueLocation.id)
                });
                const response = await fetch(`{{ route('admin.devices.lookup.staff') }}?${params.toString()}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) throw new Error('Staff lookup failed');
                const payload = await response.json();
                this.issueStaffResults = payload.results || [];
            } catch (error) {
                this.issueStaffResults = [];
            } finally {
                this.issueStaffLoading = false;
                this.issueStaffSearched = true;
            }
        },

        selectIssueStaff(staff) {
            this.issueStaffId = staff.id;
            this.issueStaffSelected = staff;
            this.issueStaffQuery = staff.label;
            this.issueStaffResults = [];
        },

        async searchIssueDevices() {
            this.issueDeviceId = '';
            this.issueDeviceSelected = null;
            this.issueDeviceResults = [];
            this.issueDeviceSearched = false;

            if (this.issueDeviceQuery.trim().length < 2) return;

            this.issueDeviceLoading = true;
            try {
                const params = new URLSearchParams({ q: this.issueDeviceQuery.trim() });
                const response = await fetch(`{{ route('admin.devices.lookup.available') }}?${params.toString()}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) throw new Error('Equipment lookup failed');
                const payload = await response.json();
                this.issueDeviceResults = payload.results || [];
            } catch (error) {
                this.issueDeviceResults = [];
            } finally {
                this.issueDeviceLoading = false;
                this.issueDeviceSearched = true;
            }
        },

        selectIssueDevice(device) {
            this.issueDeviceId = device.id;
            this.issueDeviceSelected = device;
            this.issueDeviceQuery = device.label;
            this.issueDeviceResults = [];
        }
    }));

    initializeLocationManagerTree();
}

function initializeLocationManagerTree() {
    if (!window.Alpine) return;

    document.querySelectorAll('[x-data="locationManager"]').forEach((element) => {
        const data = element._x_dataStack?.[0];

        // Livewire navigation can leave a new root with an incomplete Alpine
        // stack if the page script and Alpine initialization race each other.
        // Rebuild only that root in this case; an already working tree is left
        // untouched so click handlers are never registered twice.
        if (data && typeof data.openAdd === 'function') return;

        if (data && typeof window.Alpine.destroyTree === 'function') {
            window.Alpine.destroyTree(element);
        }

        window.Alpine.initTree(element);
    });
}

document.addEventListener('alpine:init', registerLocationManager);
registerLocationManager();
document.addEventListener('livewire:navigated', () => {
    registerLocationManager();
    window.setTimeout(initializeLocationManagerTree, 0);
});
</script>
<div
    x-data="locationManager"
    x-init="if (new URLSearchParams(window.location.search).get('action') === 'add') $nextTick(() => openAdd())"
    class="space-y-5"
>


    {{-- Top section --}}
    <div class="flex items-start justify-between gap-3">
        @if((auth()->user()->isAdmin() || auth()->user()->isCustodian()) && auth()->user()->canAction('locations', 'add'))
            <button
                type="button"
                class="shrink-0 inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                @click="openAdd()"
            >
                + Add Location
            </button>
        @endif
    </div>

    @if((auth()->user()->isAdmin() || auth()->user()->isCustodian()) && auth()->user()->canAction('locations', 'delete'))
        <form id="location-bulk-delete-form"
              method="POST"
              action="{{ route('admin.locations.bulkDestroy') }}"
              data-location-total="{{ $locations->total() }}"
              class="space-y-3"
              onsubmit="return submitLocationBulkDelete(event)">
            @csrf
            <input type="hidden" name="select_all" id="location-delete-select-all" value="0">

            <div class="flex items-center justify-between rounded-xl bg-transparent px-4 py-3 text-sm shadow-none">
                <label class="inline-flex items-center gap-2 font-medium text-gray-700 dark:text-gray-200">
                    <input type="checkbox"
                           data-location-all-master
                           onchange="toggleAllMatchingLocations(this)"
                           class="h-5 w-5  border-gray-300 text-red-600 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-700">
                    Select all locations matching the current filters
                </label>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($locations->total()) }} matching</span>
            </div>

            <div id="location-bulk-actions"
                 hidden
                 class="flex flex-col gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 sm:flex-row sm:items-center sm:justify-between dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200">
                <div>
                    <span id="location-selection-count">0 locations selected.</span>
                    <span class="block text-xs text-red-700 dark:text-red-300">Locations with offices, assignments, or active PM Plans are skipped.</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="rounded-lg bg-red-600 px-3 py-2 font-medium text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600">Delete selected</button>
                    <button type="button" class="rounded-lg bg-white px-3 py-2 font-medium text-red-700 hover:bg-red-100 dark:bg-gray-800 dark:text-red-300 dark:hover:bg-gray-700" onclick="clearLocationSelection()">Clear selection</button>
                </div>
            </div>
        </form>
    @endif

    {{-- Mobile cards --}}
    <div class="grid grid-cols-1 gap-3 md:hidden">
        @forelse ($locations as $c)
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        @if((auth()->user()->isAdmin() || auth()->user()->isCustodian()) && auth()->user()->canAction('locations', 'delete'))
                            <label class="inline-flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <input type="checkbox" value="{{ $c->id }}" data-location-checkbox onchange="syncLocationSelection()" aria-label="Select location {{ $c->name }}" class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                Select
                            </label>
                        @endif
                        <a
                            class="font-semibold text-blue-700 hover:underline dark:text-blue-400"
                            href="{{ route('admin.offices.index', $c, false) }}"
                        >
                            {{ $c->name }}
                        </a>
                    </div>

                    <div class="text-sm">
                        <div class="text-gray-500 dark:text-gray-400">Code</div>
                        <div class="text-gray-900 dark:text-white">{{ $c->code ?: '-' }}</div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-center text-xs sm:grid-cols-4">
                        <div class="rounded-lg bg-gray-50 px-2 py-2 dark:bg-gray-700/60">
                            <div class="font-semibold text-gray-900 dark:text-white">{{ $c->offices_count }}</div>
                            <div class="text-gray-500 dark:text-gray-400">Offices</div>
                        </div>
                        <div class="rounded-lg bg-gray-50 px-2 py-2 dark:bg-gray-700/60">
                            <div class="font-semibold text-gray-900 dark:text-white">{{ $locationStats[$c->id]['assigned'] ?? 0 }}</div>
                            <div class="text-gray-500 dark:text-gray-400">Equipment</div>
                        </div>
                        <div class="rounded-lg bg-gray-50 px-2 py-2 dark:bg-gray-700/60">
                            <div class="font-semibold text-gray-900 dark:text-white">{{ $locationStats[$c->id]['issued_to_users'] ?? 0 }}</div>
                            <div class="text-gray-500 dark:text-gray-400">Issued users</div>
                        </div>
                        <div class="rounded-lg bg-gray-50 px-2 py-2 dark:bg-gray-700/60">
                            <div class="font-semibold text-gray-900 dark:text-white">{{ $locationStats[$c->id]['linked_peripherals'] ?? 0 }}</div>
                            <div class="text-gray-500 dark:text-gray-400">Linked Peripherals</div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-1">
                        <x-action-icon tag="a" href="{{ route('admin.devices.index', ['location' => $c->id]) }}" icon="monitor" variant="blue" label="View equipment" />

                        @if((auth()->user()->isAdmin() || auth()->user()->isCustodian()) && auth()->user()->canAction('issuance', 'add'))
                            <button
                                type="button"
                                aria-label="Issue equipment"
                                class="action-icon-button group relative inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-green-600 text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 focus:ring-offset-white dark:bg-green-500 dark:hover:bg-green-600 dark:focus:ring-offset-gray-900"
                                @click="openIssue({
                                    id: {{ $c->id }},
                                    name: @js($c->name),
                                    code: @js($c->code ?? ''),
                                    offices: @js($c->offices->map(fn ($office) => ['id' => $office->id, 'name' => $office->name])->values())
                                })">
                                <x-action-icon-symbol icon="issue" />
                                <span class="sr-only">Issue equipment</span>
                                <span class="pointer-events-none absolute bottom-full left-1/2 z-[70] mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[11px] font-medium text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100 group-focus:opacity-100 dark:bg-gray-100 dark:text-gray-900" role="tooltip">Issue equipment</span>
                            </button>
                        @endif

                        @if((auth()->user()->isAdmin() || auth()->user()->isCustodian()) && (auth()->user()->canAction('locations', 'edit') || auth()->user()->canAction('locations', 'delete')))
                            @if(auth()->user()->canAction('locations', 'edit'))
                            <button
                                type="button"
                                aria-label="Edit location"
                                class="action-icon-button group relative inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-900 text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-black focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 focus:ring-offset-white dark:bg-gray-700 dark:hover:bg-gray-600 dark:focus:ring-offset-gray-900"
                                @click="openEdit({
                                    id: {{ $c->id }},
                                    name: @js($c->name),
                                    code: @js($c->code ?? '')
                                })">
                                <x-action-icon-symbol icon="edit" />
                                <span class="sr-only">Edit location</span>
                                <span class="pointer-events-none absolute bottom-full left-1/2 z-[70] mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[11px] font-medium text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100 group-focus:opacity-100 dark:bg-gray-100 dark:text-gray-900" role="tooltip">Edit location</span>
                            </button>
                            @endif

                            @if(auth()->user()->canAction('locations', 'delete'))
                            <button
                                type="button"
                                aria-label="Delete location"
                                class="action-icon-button group relative inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-600 text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-white dark:bg-red-500 dark:hover:bg-red-600 dark:focus:ring-offset-gray-900"
                                @click="openDelete({{ $c->id }})"
                            >
                                <x-action-icon-symbol icon="trash" />
                                <span class="sr-only">Delete location</span>
                                <span class="pointer-events-none absolute bottom-full left-1/2 z-[70] mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[11px] font-medium text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100 group-focus:opacity-100 dark:bg-gray-100 dark:text-gray-900" role="tooltip">Delete location</span>
                            </button>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-gray-200 bg-white p-6 text-center text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                No locations found.
            </div>
        @endforelse
    </div>

    {{-- Desktop table --}}
    <div class="hidden md:block overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left dark:bg-gray-900/40">
                    <tr>
                        @if((auth()->user()->isAdmin() || auth()->user()->isCustodian()) && auth()->user()->canAction('locations', 'delete'))
                            <th class="w-12 px-4 py-3 text-center">
                            </th>
                        @endif
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Name</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Code</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Offices</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Equipment</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Issued Users</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Linked Peripherals</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($locations as $c)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                        @if((auth()->user()->isAdmin() || auth()->user()->isCustodian()) && auth()->user()->canAction('locations', 'delete'))
                                <td class="px-4 py-3 text-center">
                                    <input type="checkbox" value="{{ $c->id }}" data-location-checkbox onchange="syncLocationSelection()" aria-label="Select location {{ $c->name }}" class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                </td>
                            @endif
                            <td class="px-4 py-3">
                                <a
                                    class="font-medium text-blue-700 hover:underline dark:text-blue-400"
                                    href="{{ route('admin.offices.index', $c, false) }}"
                                >
                                    {{ $c->name }}
                                </a>
                            </td>

                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $c->code ?: '-' }}</td>

                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $c->offices_count }}</td>

                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $locationStats[$c->id]['assigned'] ?? 0 }}</td>

                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $locationStats[$c->id]['issued_to_users'] ?? 0 }}</td>

                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $locationStats[$c->id]['linked_peripherals'] ?? 0 }}</td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <x-action-icon tag="a" href="{{ route('admin.devices.index', ['location' => $c->id]) }}" icon="monitor" variant="blue" label="View equipment" />

                                    @if((auth()->user()->isAdmin() || auth()->user()->isCustodian()) && auth()->user()->canAction('issuance', 'add'))
                                        <button
                                            type="button"
                                            aria-label="Issue equipment"
                                            class="action-icon-button group relative inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-green-600 text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 focus:ring-offset-white dark:bg-green-500 dark:hover:bg-green-600 dark:focus:ring-offset-gray-900"
                                            @click="openIssue({
                                                id: {{ $c->id }},
                                                name: @js($c->name),
                                                code: @js($c->code ?? ''),
                                                offices: @js($c->offices->map(fn ($office) => ['id' => $office->id, 'name' => $office->name])->values())
                                            })">
                                            <x-action-icon-symbol icon="issue" />
                                            <span class="sr-only">Issue equipment</span>
                                            <span class="pointer-events-none absolute bottom-full left-1/2 z-[70] mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[11px] font-medium text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100 group-focus:opacity-100 dark:bg-gray-100 dark:text-gray-900" role="tooltip">Issue equipment</span>
                                        </button>
                                    @endif

                                    @if((auth()->user()->isAdmin() || auth()->user()->isCustodian()) && (auth()->user()->canAction('locations', 'edit') || auth()->user()->canAction('locations', 'delete')))
                                        @if(auth()->user()->canAction('locations', 'edit'))
                                        <button
                                            type="button"
                                            aria-label="Edit location"
                                            class="action-icon-button group relative inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-900 text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-black focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 focus:ring-offset-white dark:bg-gray-700 dark:hover:bg-gray-600 dark:focus:ring-offset-gray-900"
                                            @click="openEdit({
                                                id: {{ $c->id }},
                                                name: @js($c->name),
                                                code: @js($c->code ?? '')
                                            })">
                                            <x-action-icon-symbol icon="edit" />
                                            <span class="sr-only">Edit location</span>
                                            <span class="pointer-events-none absolute bottom-full left-1/2 z-[70] mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[11px] font-medium text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100 group-focus:opacity-100 dark:bg-gray-100 dark:text-gray-900" role="tooltip">Edit location</span>
                                        </button>
                                        @endif

                                        @if(auth()->user()->canAction('locations', 'delete'))
                                        <button
                                            type="button"
                                            aria-label="Delete location"
                                            class="action-icon-button group relative inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-600 text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-white dark:bg-red-500 dark:hover:bg-red-600 dark:focus:ring-offset-gray-900"
                                            @click="openDelete({{ $c->id }})"
                                        >
                                            <x-action-icon-symbol icon="trash" />
                                            <span class="sr-only">Delete location</span>
                                            <span class="pointer-events-none absolute bottom-full left-1/2 z-[70] mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[11px] font-medium text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100 group-focus:opacity-100 dark:bg-gray-100 dark:text-gray-900" role="tooltip">Delete location</span>
                                        </button>
                                        @endif
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">View only</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ (auth()->user()->isAdmin() || auth()->user()->isCustodian()) && auth()->user()->canAction('locations', 'delete') ? 8 : 7 }}" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                No locations found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>
        {{ $locations->links() }}
    </div>

    {{-- Location-scoped issuance modal --}}
    <x-modal show="issueOpen" title="Issue Equipment" maxWidth="max-w-2xl">
        <form
            method="POST"
            action="{{ route('admin.devices.issue', '__DEVICE__') }}"
            x-bind:action="'{{ route('admin.devices.issue', '__DEVICE__') }}'.replace('__DEVICE__', issueDeviceId)"
            class="space-y-4"
            @submit="if (!issueOfficeId || !issueStaffId || !issueDeviceId || !issueRemarks.trim()) $event.preventDefault()"
        >
            @csrf

            <input type="hidden" name="location_id" :value="issueLocation.id">
            <input type="hidden" name="staff_id" :value="issueStaffId">

            <div class="rounded-xl border border-blue-200 bg-blue-50 px-3 py-3 text-sm text-blue-900 dark:border-blue-900/60 dark:bg-blue-900/20 dark:text-blue-100">
                <div class="font-semibold">Location: <span x-text="issueLocation.code ? `${issueLocation.code} - ${issueLocation.name}` : issueLocation.name"></span></div>
                <div class="mt-1 text-xs">The location is fixed from the selected location. The end user’s office must belong to this location.</div>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Office <span class="text-red-600">*</span></label>
                <select
                    x-model="issueOfficeId"
                    @change="resetIssueStaff()"
                    class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    required
                >
                    <option value="">-- Select office --</option>
                    <template x-for="office in issueOffices" :key="office.id">
                        <option :value="office.id" x-text="office.name"></option>
                    </template>
                </select>
                <p x-show="issueOffices.length === 0" class="mt-1 text-xs text-amber-700 dark:text-amber-300">No offices are registered in this location yet.</p>
            </div>

            <div x-show="issueOfficeId" x-cloak>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">End user <span class="text-red-600">*</span></label>
                <input
                    type="search"
                    x-model="issueStaffQuery"
                    @input.debounce.300ms="searchIssueStaff()"
                    class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    placeholder="Type at least 2 characters: name or email"
                    autocomplete="off"
                >
                <div class="mt-2 max-h-48 overflow-y-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <div x-show="issueStaffLoading" class="px-3 py-3 text-center text-sm text-gray-500 dark:text-gray-400">Searching end users...</div>
                    <div x-show="!issueStaffLoading && !issueStaffSearched" class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">Search active end users in the selected office.</div>
                    <div x-show="!issueStaffLoading && issueStaffSearched && issueStaffResults.length === 0" class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">No active end user found.</div>
                    <template x-for="staff in issueStaffResults" :key="staff.id">
                        <button
                            type="button"
                            class="block w-full border-b border-gray-100 px-3 py-2 text-left text-sm text-gray-700 hover:bg-blue-50 last:border-b-0 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-700"
                            @click="selectIssueStaff(staff)"
                        >
                            <span class="block font-medium" x-text="staff.label"></span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400" x-text="[staff.position, staff.email].filter(Boolean).join(' · ')"></span>
                        </button>
                    </template>
                </div>
                <p x-show="issueStaffSelected" class="mt-1 text-xs text-green-700 dark:text-green-300">Selected: <span x-text="issueStaffSelected?.label"></span></p>
            </div>

            <div x-show="issueStaffId" x-cloak>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Available equipment <span class="text-red-600">*</span></label>
                <input
                    type="search"
                    x-model="issueDeviceQuery"
                    @input.debounce.300ms="searchIssueDevices()"
                    class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    placeholder="Search property number, serial, type, or brand"
                    autocomplete="off"
                >
                <div class="mt-2 max-h-48 overflow-y-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <div x-show="issueDeviceLoading" class="px-3 py-3 text-center text-sm text-gray-500 dark:text-gray-400">Searching available equipment...</div>
                    <div x-show="!issueDeviceLoading && !issueDeviceSearched" class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">Search available equipment to issue.</div>
                    <div x-show="!issueDeviceLoading && issueDeviceSearched && issueDeviceResults.length === 0" class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">No available equipment found.</div>
                    <template x-for="device in issueDeviceResults" :key="device.id">
                        <button
                            type="button"
                            class="block w-full border-b border-gray-100 px-3 py-2 text-left text-sm text-gray-700 hover:bg-blue-50 last:border-b-0 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-700"
                            @click="selectIssueDevice(device)"
                        >
                            <span class="block font-medium" x-text="device.label"></span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400" x-text="device.condition || 'Condition not set'"></span>
                        </button>
                    </template>
                </div>
                <p x-show="issueDeviceSelected" class="mt-1 text-xs text-green-700 dark:text-green-300">Selected: <span x-text="issueDeviceSelected?.label"></span></p>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Issuance remarks <span class="text-red-600">*</span></label>
                <textarea
                    name="remarks"
                    x-model="issueRemarks"
                    rows="3"
                    maxlength="1000"
                    required
                    class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    placeholder="Reason or purpose for issuing this equipment"
                ></textarea>
            </div>

            <div class="flex flex-wrap gap-2 pt-1">
                <button
                    type="submit"
                    class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-green-500 dark:hover:bg-green-600"
                    :disabled="!issueOfficeId || !issueStaffId || !issueDeviceId || !issueRemarks.trim()"
                >
                    Issue Equipment
                </button>
                <button type="button" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600" @click="issueOpen = false">
                    Cancel
                </button>
            </div>
        </form>
    </x-modal>

    @if((auth()->user()->isAdmin() || auth()->user()->isCustodian()) && auth()->user()->canAction('locations', 'add'))
    {{-- Add modal --}}
    <x-modal show="addOpen" title="Add Location">
        <form method="POST" action="{{ route('admin.locations.store') }}" class="space-y-3">
            @csrf

            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Add multiple locations</span>
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium border"
                    :class="bulkEnabled ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600'"
                    @click="bulkEnabled = !bulkEnabled"
                >
                    <span x-text="bulkEnabled ? 'Bulk: On' : 'Bulk: Off'"></span>
                </button>
            </div>

            <div class="space-y-3">
                <!-- Bulk controls -->
                <div x-show="bulkEnabled" class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-lg bg-gray-100 px-3 py-1.5 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        @click="removeBulkRow()"
                    >-
                    </button>

                    <input type="hidden" name="count" :value="bulkRows.length">

                    <div class="text-sm text-gray-700 dark:text-gray-300">
                        Records: <span class="font-semibold" x-text="bulkRows.length"></span>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg bg-gray-100 px-3 py-1.5 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        @click="addBulkRow()"
                    >+
                    </button>
                </div>

                <!-- Bulk form -->
                <template x-if="bulkEnabled">
                    <div class="space-y-5">
                        <template x-for="(row, idx) in bulkRows" :key="idx">
                            <div class="space-y-3" :class="idx > 0 ? 'pt-4 border-t border-gray-200 dark:border-gray-700' : ''">
                                <div>
                                    <label class="text-sm font-medium dark:text-gray-300">Location Name <span class="text-red-600">*</span></label>
                                    <input
                                        :name="`names[${idx}]`"
                                        x-model="row.name"
                                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        required
                                        maxlength="150"
                                        pattern="[A-Za-zÑñ0-9][A-Za-zÑñ0-9.,&'\-\(\)\s]*"
                                        title="Letters, numbers, and basic punctuation only"
                                        placeholder="e.g. College of Science"
                                    >
                                    <div class="mt-1 text-sm text-red-600 dark:text-red-400" x-show="row.nameError" x-text="row.nameError"></div>
                                </div>

                                <div>
                                    <label class="text-sm font-medium dark:text-gray-300">Code <span class="text-red-600">*</span></label>
                                    <input
                                        :name="`codes[${idx}]`"
                                        x-model="row.code"
                                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        required
                                        maxlength="20"
                                        pattern="[A-Za-z0-9\-]+"
                                        title="Letters, numbers, and hyphens only (no spaces)"
                                        placeholder="e.g. COS"
                                        @input="row.code = row.code.toUpperCase().replace(/[^A-Z0-9\-]/g, '')"
                                    >
                                    <div class="mt-1 text-sm text-red-600 dark:text-red-400" x-show="row.codeError" x-text="row.codeError"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Single form -->
                <template x-if="!bulkEnabled">
                    <div class="space-y-3">
                        <div>
                            <label class="text-sm font-medium dark:text-gray-300">Location Name <span class="text-red-600">*</span></label>
                            <input
                                name="name"
                                x-model="addSingle.name"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                required
                                maxlength="150"
                                pattern="[A-Za-zÑñ0-9][A-Za-zÑñ0-9.,&'\-\(\)\s]*"
                                title="Letters, numbers, and basic punctuation only"
                                placeholder="e.g. College of Science"
                            >
                            <div class="mt-1 text-sm text-red-600 dark:text-red-400" x-show="addSingle.nameError" x-text="addSingle.nameError"></div>
                        </div>

                        <div>
                            <label class="text-sm font-medium dark:text-gray-300">Code <span class="text-red-600">*</span></label>
                            <input
                                name="code"
                                x-model="addSingle.code"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                required
                                maxlength="20"
                                pattern="[A-Za-z0-9\-]+"
                                title="Letters, numbers, and hyphens only (no spaces)"
                                placeholder="e.g. COS"
                                @input="addSingle.code = addSingle.code.toUpperCase().replace(/[^A-Z0-9\-]/g, '')"
                            >
                            <div class="mt-1 text-sm text-red-600 dark:text-red-400" x-show="addSingle.codeError" x-text="addSingle.codeError"></div>
                        </div>
                    </div>
                </template>
            </div>


            <div class="flex gap-2 pt-2">
                <button class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600">Save</button>
                <button type="button" class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600" @click="addOpen = false">
                    Cancel
                </button>
            </div>


        </form>
    </x-modal>
    @endif


    @if((auth()->user()->isAdmin() || auth()->user()->isCustodian()) && auth()->user()->canAction('locations', 'edit'))
    {{-- Edit modal --}}
    <x-modal show="editOpen" title="Edit Location" >
        <form
            method="POST"
            action="{{ route('admin.locations.update', '__ID__') }}"
            x-bind:action="'{{ route('admin.locations.update', '__ID__') }}'.replace('__ID__', editLocation.id)"
            class="space-y-3"
        >
            @csrf
            @method('PUT')

            <input type="hidden" name="editing_id" :value="editLocation.id">

            <div>
                <label class="text-sm font-medium dark:text-gray-300">Location Name <span class="text-red-600">*</span></label>
                <input
                    name="name"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    x-model="editLocation.name"
                    required
                    maxlength="150"
                    pattern="[A-Za-zÑñ0-9][A-Za-zÑñ0-9.,&'\-\(\)\s]*"
                    title="Letters, numbers, and basic punctuation only"
                >
                <div class="mt-1 text-sm text-red-600 dark:text-red-400" x-show="editLocation.nameError" x-text="editLocation.nameError"></div>
            </div>

            <div>
                <label class="text-sm font-medium dark:text-gray-300">Code <span class="text-red-600">*</span></label>
                <input
                    name="code"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    x-model="editLocation.code"
                    required
                    maxlength="20"
                    pattern="[A-Za-z0-9\-]+"
                    title="Letters, numbers, and hyphens only (no spaces)"
                    @input="editLocation.code = editLocation.code.toUpperCase().replace(/[^A-Z0-9\-]/g, '')"
                >
                <div class="mt-1 text-sm text-red-600 dark:text-red-400" x-show="editLocation.codeError" x-text="editLocation.codeError"></div>
            </div>

            <div class="flex gap-2 pt-2">
                <button class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600">Update</button>
                <button type="button" class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600" @click="editOpen = false">
                    Cancel
                </button>
            </div>
        </form>
    </x-modal>
    @endif

    @if((auth()->user()->isAdmin() || auth()->user()->isCustodian()) && auth()->user()->canAction('locations', 'delete'))
    {{-- Delete modal --}}
    <x-modal show="deleteOpen" title="Delete Location">
        <div class="space-y-3">
            <div class="text-sm text-gray-700 dark:text-gray-300">
                Are you sure you want to move this location to the recycle bin? It can be restored by a Super Admin.
            </div>



            <form
                method="POST"
                :action="`{{ route('admin.locations.destroy', ['location' => '__ID__']) }}`.replace('__ID__', deleteLocationId)"
                @submit="if (!deleteLocationId) $event.preventDefault()"
                class="flex gap-2"
            >

                @csrf
                @method('DELETE')

                <button type="submit" x-ref="confirmDeleteBtn" class="rounded-lg bg-red-600 px-4 py-2 text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600">Move to Recycle Bin</button>

                <button type="button" class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600" @click="deleteOpen = false">
                    Cancel
                </button>
            </form>
        </div>
    </x-modal>
    @endif
</div>
@endsection

@push('scripts')
<script>
    function locationSelectionBoxes() {
        // The page renders both mobile cards and the desktop table. Only use
        // the currently visible copy so a row is never submitted twice.
        return Array.from(document.querySelectorAll('[data-location-checkbox]'))
            .filter((box) => box.offsetParent !== null);
    }

    function locationAllMatchingSelected() {
        return document.getElementById('location-delete-select-all')?.value === '1';
    }

    function syncLocationSelection(options = {}) {
        const boxes = locationSelectionBoxes();
        const selected = boxes.filter((box) => box.checked).length;
        const selectAllInput = document.getElementById('location-delete-select-all');
        let allMatching = locationAllMatchingSelected();

        if (allMatching && !options.preserveAllMatching && selected < boxes.length) {
            allMatching = false;
            if (selectAllInput) selectAllInput.value = '0';
        }

        const count = document.getElementById('location-selection-count');
        const actions = document.getElementById('location-bulk-actions');
        const form = document.getElementById('location-bulk-delete-form');
        const total = Number(form?.dataset.locationTotal || 0);

        if (count) {
            count.textContent = allMatching
                ? `${total} locations selected across all matching pages.`
                : `${selected} location${selected === 1 ? '' : 's'} selected on this page.`;
        }

        if (actions) actions.hidden = !(allMatching || selected > 0);

        document.querySelectorAll('[data-location-all-master]').forEach((master) => {
            master.checked = allMatching;
        });

        document.querySelectorAll('[data-location-page-master]').forEach((master) => {
            master.checked = boxes.length > 0 && selected === boxes.length;
            master.indeterminate = selected > 0 && selected < boxes.length;
        });
    }

    function toggleAllMatchingLocations(master) {
        const selectAllInput = document.getElementById('location-delete-select-all');
        if (selectAllInput) selectAllInput.value = master.checked ? '1' : '0';
        locationSelectionBoxes().forEach((box) => { box.checked = master.checked; });
        syncLocationSelection({ preserveAllMatching: true });
    }

    function toggleLocationPageSelection(master) {
        const selectAllInput = document.getElementById('location-delete-select-all');
        if (selectAllInput) selectAllInput.value = '0';
        locationSelectionBoxes().forEach((box) => { box.checked = master.checked; });
        syncLocationSelection();
    }

    function clearLocationSelection() {
        const selectAllInput = document.getElementById('location-delete-select-all');
        if (selectAllInput) selectAllInput.value = '0';
        locationSelectionBoxes().forEach((box) => { box.checked = false; });
        document.querySelectorAll('[data-location-all-master], [data-location-page-master]').forEach((master) => {
            master.checked = false;
            master.indeterminate = false;
        });
        syncLocationSelection();
    }

    function prepareLocationBulkForm(selectAll) {
        const form = document.getElementById('location-bulk-delete-form');
        if (!form) return null;
        form.querySelectorAll('input[name="location_ids[]"]').forEach((input) => input.remove());
        form.querySelector('#location-delete-select-all').value = selectAll ? '1' : '0';
        if (!selectAll) {
            locationSelectionBoxes().filter((box) => box.checked).forEach((box) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'location_ids[]';
                input.value = box.value;
                form.appendChild(input);
            });
        }
        return form;
    }

    function submitLocationBulkDelete(event) {
        event.preventDefault();
        const selected = locationSelectionBoxes().filter((box) => box.checked);
        const selectAll = locationAllMatchingSelected();
        const formElement = document.getElementById('location-bulk-delete-form');
        const total = Number(formElement?.dataset.locationTotal || 0);

        if (!selectAll && !selected.length) {
            window.alert('Select at least one location first.');
            return false;
        }

        const selectedCount = selectAll ? total : selected.length;
        if (!window.confirm(`Move ${selectedCount} selected location(s) to the recycle bin? Locations still in use will be skipped.`)) return false;
        const form = prepareLocationBulkForm(selectAll);
        if (form) form.submit();
        return false;
    }

    function initializeLocationBulkSelection() {
        window.setTimeout(syncLocationSelection, 0);
    }

    document.addEventListener('DOMContentLoaded', initializeLocationBulkSelection);
    document.addEventListener('livewire:navigated', initializeLocationBulkSelection);
</script>
@endpush
