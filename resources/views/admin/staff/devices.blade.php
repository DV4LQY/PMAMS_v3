@extends('admin.layouts.app')

@section('title', 'Staff Equipment')
@section('page_title', 'Staff Equipment')

@section('content')
@php
    $assignments = $assignments ?? ($issued ?? collect());
    $availableDevicesCount = (int) ($availableDevicesCount ?? 0);
    $transferBag = $errors->getBag('transfer');
    $transferLocations = $transferLocations ?? collect();

    $staffName = trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? ''));
    $staffName = $staffName !== '' ? $staffName : ($staff->name ?? 'Staff');

    $office = $staff->office ?? null;
    $location = $office?->location ?? $office?->college;
    $transferReturnPath = parse_url(route('admin.staff.devices.index', $staff), PHP_URL_PATH)
        ?: route('admin.staff.devices.index', $staff);
    $transferIsOfficeHead = filter_var(
        old('transfer_is_office_head', $staff->is_office_head),
        FILTER_VALIDATE_BOOLEAN
    );
    $transferPreserveAssignments = filter_var(
        old('preserve_assignments', false),
        FILTER_VALIDATE_BOOLEAN
    );

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

<div
    x-data="{
        deviceLookupUrl: @js(route('admin.devices.lookup.available')),
        deviceQuery: '',
        deviceId: '',
        deviceResults: [],
        deviceSelected: null,
        deviceLoading: false,
        deviceHasSearched: false,
        deviceTimer: null,
        deviceAbort: null,
        transferOpen: {{ $transferBag->any() ? 'true' : 'false' }},
        transferLocations: @js($transferLocations->map(fn ($destinationLocation) => [
            'id' => $destinationLocation->id,
            'name' => $destinationLocation->name,
            'code' => $destinationLocation->code,
            'offices' => $destinationLocation->offices->map(fn ($destinationOffice) => [
                'id' => $destinationOffice->id,
                'name' => $destinationOffice->name,
            ])->values(),
        ])->values()),
        transferStaff: {
            id: @js((int) old('transfer_staff_id', $staff->id)),
            name: @js(old('transfer_staff_name', $staffName)),
            activeAssignments: @js((int) old('transfer_active_assignments', $assignments->count())),
            isOfficeHead: {{ $transferIsOfficeHead ? 'true' : 'false' }},
        },
        transferLocationId: @js((string) old('destination_location_id', '')),
        transferOfficeId: @js((string) old('destination_office_id', '')),
        preserveAssignments: {{ $transferPreserveAssignments ? 'true' : 'false' }},

        queueDeviceLookup() {
            clearTimeout(this.deviceTimer);
            this.deviceTimer = setTimeout(() => this.fetchAvailableDevices(), 250);
        },

        async fetchAvailableDevices() {
            const query = this.deviceQuery.trim();

            if (this.deviceAbort) {
                this.deviceAbort.abort();
            }

            this.deviceAbort = new AbortController();
            this.deviceLoading = true;
            this.deviceHasSearched = query !== '';

            try {
                const url = new URL(this.deviceLookupUrl, window.location.origin);
                url.searchParams.set('q', query);

                const response = await fetch(url, {
                    headers: { 'Accept': 'application/json' },
                    signal: this.deviceAbort.signal,
                });

                if (!response.ok) {
                    throw new Error('Unable to search available equipment.');
                }

                const data = await response.json();
                this.deviceResults = Array.isArray(data.results) ? data.results : [];
            } catch (error) {
                if (error.name !== 'AbortError') {
                    this.deviceResults = [];
                }
            } finally {
                this.deviceLoading = false;
            }
        },

        selectDevice(device) {
            this.deviceId = device.id;
            this.deviceSelected = device;
            this.deviceQuery = device.label;
            this.deviceResults = [];
        },

        officesForTransfer() {
            const location = this.transferLocations.find((item) => String(item.id) === String(this.transferLocationId));

            return location?.offices || [];
        },

        openTransfer() {
            this.transferStaff = {
                id: {{ (int) $staff->id }},
                name: @js($staffName),
                activeAssignments: {{ (int) $assignments->count() }},
                isOfficeHead: {{ $staff->is_office_head ? 'true' : 'false' }},
            };
            this.transferLocationId = '';
            this.transferOfficeId = '';
            this.preserveAssignments = false;
            this.transferOpen = true;
        },

        closeTransfer() {
            this.transferOpen = false;
            this.transferLocationId = '';
            this.transferOfficeId = '';
            this.preserveAssignments = false;
        }
    }"
    x-init="fetchAvailableDevices()"
    class="space-y-5"
>
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
                    class="staff-navigation-button inline-flex h-10 w-28 items-center justify-center whitespace-nowrap rounded-xl px-3 text-sm font-semibold leading-5 text-gray-700 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                >
                    Back to Staff
                </a>
            @endif

            @if($office && auth()->user()?->canAction('staff', 'edit'))
                <button
                    type="button"
                    x-on:click="openTransfer()"
                    class="staff-navigation-button inline-flex h-10 w-28 items-center justify-center whitespace-nowrap rounded-xl bg-amber-500 px-3 text-sm font-semibold leading-5 text-white shadow-sm hover:bg-amber-600 dark:bg-amber-600 dark:hover:bg-amber-500"
                    aria-label="Transfer {{ $staffName }} to another office"
                >
                    Transfer
                </button>
            @endif

            <a
                href="{{ route('admin.devices.index') }}"
                class="staff-navigation-button inline-flex h-10 w-28 items-center justify-center whitespace-nowrap rounded-xl px-3 text-sm font-semibold leading-5 text-white bg-blue-600 shadow-sm hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
            >
                Equipment
            </a>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('error'))
        <div class="notification rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-400">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="notification rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-400">
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
                    <label for="device_search" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Available Equipment
                    </label>

                    <input
                        id="device_search"
                        type="text"
                        data-pmams-inline-search
                        x-model="deviceQuery"
                        x-on:input="deviceId = ''; deviceSelected = null; queueDeviceLookup()"
                        x-on:focus="fetchAvailableDevices()"
                        placeholder="Search property #, serial #, equipment type, brand, or model..."
                        aria-label="Search available equipment"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:ring-blue-900/30"
                        autocomplete="off"
                    >
                    <input type="hidden" name="device_id" :value="deviceId">

                    <div
                        x-show="!deviceId"
                        class="mt-2 max-h-56 overflow-y-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
                    >
                        <template x-if="deviceLoading">
                            <div class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                Searching available equipment...
                            </div>
                        </template>

                        <template x-if="!deviceLoading && !deviceHasSearched && deviceResults.length === 0">
                            <div class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                Type to search available equipment.
                            </div>
                        </template>

                        <template x-for="device in deviceResults" :key="device.id">
                            <button
                                type="button"
                                x-on:click="selectDevice(device)"
                                class="block w-full border-b border-gray-100 px-3 py-2 text-left text-sm transition last:border-b-0 hover:bg-blue-50 dark:border-gray-700 dark:hover:bg-gray-700"
                            >
                                <span class="font-semibold text-gray-900 dark:text-white" x-text="device.property_number"></span>
                                <span class="ml-1 text-xs text-gray-500 dark:text-gray-400" x-text="device.name"></span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400" x-text="device.label"></span>
                            </button>
                        </template>

                        <div
                            x-show="!deviceLoading && deviceHasSearched && deviceResults.length === 0"
                            class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400"
                        >
                            No available equipment found.
                        </div>
                    </div>

                    <div
                        x-show="deviceSelected"
                        class="mt-2 rounded-lg bg-blue-50 px-3 py-2 text-sm text-blue-900 dark:bg-blue-900/20 dark:text-blue-100"
                    >
                        Selected: <span class="font-medium" x-text="deviceSelected?.label"></span>
                    </div>
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
                        :disabled="!deviceId || {{ $availableDevicesCount }} === 0"
                    >
                        Issue Equipment
                    </button>
                </div>
            </form>

            @if($availableDevicesCount === 0)
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
                                        <x-action-icon
                                            tag="a"
                                            href="{{ route('admin.devices.show', $device) }}"
                                            icon="eye"
                                            variant="green"
                                            label="View equipment"
                                            class="h-10 w-10"
                                        />
                                    @endif

                                    <form method="POST" action="{{ route('admin.staff.devices.return', [$staff, $assignment]) }}">
                                        @csrf

                                        <x-action-icon
                                            type="submit"
                                            icon="restore"
                                            variant="red"
                                            label="Return equipment"
                                            class="h-10 w-10"
                                            onclick="return confirm('Return this equipment from {{ $staffName }}?')"
                                        />
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

    {{-- Transfer staff modal --}}
    @if($office && auth()->user()?->canAction('staff', 'edit'))
        <x-modal
            id="transfer-staff-devices-modal"
            show="transferOpen"
            title="Transfer staff to another office"
            x-on:pmams-modal-close.window="if ($event.detail.id === 'transfer-staff-devices-modal') closeTransfer()"
        >
            <form
                method="POST"
                action="{{ route('admin.staff.transfer', ['office' => $office->id, 'staff' => $staff->id]) }}"
                @submit="if (!transferLocationId || !transferOfficeId || (transferStaff.activeAssignments > 0 && !preserveAssignments)) $event.preventDefault()"
                class="space-y-4"
            >
                @csrf
                <input type="hidden" name="transfer_staff_id" value="{{ $staff->id }}">
                <input type="hidden" name="transfer_staff_name" value="{{ $staffName }}">
                <input type="hidden" name="transfer_active_assignments" value="{{ $assignments->count() }}">
                <input type="hidden" name="transfer_is_office_head" value="{{ $staff->is_office_head ? 1 : 0 }}">
                <input type="hidden" name="return_to" value="{{ $transferReturnPath }}">

                @if($transferBag->any())
                    <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300" role="alert">
                        <ul class="list-disc space-y-1 pl-5">
                            @foreach($transferBag->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:bg-gray-700/50 dark:text-gray-200">
                    <div class="font-semibold">{{ $staffName }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Current location/office: {{ $location?->name ?? 'Unassigned' }} / {{ $office->name }}
                    </div>
                </div>

                <div>
                    <label for="staff-devices-transfer-location" class="text-sm font-medium text-gray-700 dark:text-gray-200">Destination location <span class="text-red-500">*</span></label>
                    <select
                        id="staff-devices-transfer-location"
                        name="destination_location_id"
                        x-model="transferLocationId"
                        @change="if (!officesForTransfer().some((item) => String(item.id) === String(transferOfficeId))) transferOfficeId = ''"
                        class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        required
                    >
                        <option value="">Select destination location</option>
                        <template x-for="destinationLocation in transferLocations" :key="destinationLocation.id">
                            <option :value="destinationLocation.id" x-text="destinationLocation.code ? `${destinationLocation.code} - ${destinationLocation.name}` : destinationLocation.name"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label for="staff-devices-transfer-office" class="text-sm font-medium text-gray-700 dark:text-gray-200">Destination office <span class="text-red-500">*</span></label>
                    <select
                        id="staff-devices-transfer-office"
                        name="destination_office_id"
                        x-model="transferOfficeId"
                        class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:disabled:bg-gray-700"
                        :disabled="!transferLocationId"
                        required
                    >
                        <option value="" x-text="transferLocationId ? 'Select destination office' : 'Select a location first'"></option>
                        <template x-for="destinationOffice in officesForTransfer()" :key="destinationOffice.id">
                            <option :value="destinationOffice.id" x-text="destinationOffice.name"></option>
                        </template>
                    </select>
                </div>

                <div
                    x-show="transferStaff.activeAssignments > 0"
                    x-cloak
                    class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-200"
                >
                    <p>
                        This staff member has <strong x-text="transferStaff.activeAssignments"></strong> active equipment assignment(s).
                        Transferring will automatically return the equipment and mark it Available. The assignment history will remain recorded against the original office/location.
                    </p>
                    <label class="mt-2 flex items-start gap-2">
                        <input
                            type="checkbox"
                            name="preserve_assignments"
                            value="1"
                            x-model="preserveAssignments"
                            :required="transferStaff.activeAssignments > 0"
                            class="mt-0.5 h-4 w-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                        >
                        <span>I understand that active equipment will be returned and made available; historical assignment records will not be rewritten.</span>
                    </label>
                </div>

                <div
                    x-show="transferStaff.isOfficeHead"
                    x-cloak
                    class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm text-indigo-800 dark:border-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-200"
                >
                    The current office-head designation will be cleared during the transfer. Assign the staff member as the destination office representative separately if needed.
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="rounded-lg bg-amber-500 px-4 py-2 font-semibold text-white hover:bg-amber-600 dark:bg-amber-600 dark:hover:bg-amber-500">Transfer staff</button>
                    <button type="button" class="rounded-lg bg-gray-100 px-4 py-2 font-semibold text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600" @click="closeTransfer()">Cancel</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
@endsection
