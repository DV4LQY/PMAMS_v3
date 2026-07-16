@extends('admin.layouts.app')

@section('title', 'Equipment Details')
@section('page_title', 'Equipment Details')
@section('breadcrumbs')
    <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Dashboard</a>
    <span>/</span>
    <a href="{{ route('admin.devices.index') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Equipment</a>
    <span>/</span>
    <span class="font-medium text-gray-800 dark:text-gray-100">Equipment Details</span>
@endsection

@section('content')
@php
    $deviceTypeName = strtolower($device->type?->name ?? '');
    $isComputerType = in_array($deviceTypeName, ['desktop', 'laptop']);
    $isDesktopType = $deviceTypeName === 'desktop';
    $deviceUrl = route('admin.devices.show', $device);
@endphp

<script>
    window.__deviceShowData = {
        selectedTypeId: @json(old('device_type_id', $device->device_type_id)),
        typeNames: @json($types->pluck('name', 'id'))
    };

    function deviceEditor() {
        return {
            editOpen: false,
            relocateOpen: false,
            relocateStaffQuery: '',
            relocateStaffId: '',
            relocateRemarks: '',
            staffOptions: @js($staffOptions ?? []),
            selectedTypeId: window.__deviceShowData.selectedTypeId,
            typeNames: window.__deviceShowData.typeNames,

            getTypeName(typeId) {
                return (this.typeNames[typeId] || '').toLowerCase();
            },

            isComputerType(typeId = null) {
                let selected = typeId ?? this.selectedTypeId;
                let name = this.getTypeName(selected);
                return name === 'desktop' || name === 'laptop';
            },

            isDesktopType(typeId = null) {
                let selected = typeId ?? this.selectedTypeId;
                return this.getTypeName(selected) === 'desktop';
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
                form.querySelectorAll('.unit-price-input').forEach(function (input) {
                    input.value = String(input.value ?? '').replace(/,/g, '');
                });
            },

            filteredRelocationStaff() {
                const query = this.relocateStaffQuery.trim().toLowerCase();
                return this.staffOptions.filter((staff) => !query || staff.search.includes(query)).slice(0, 10);
            },

            selectRelocationStaff(staff) {
                this.relocateStaffId = staff.id;
                this.relocateStaffQuery = staff.label;
            },

            openRelocation() {
                this.relocateStaffQuery = '';
                this.relocateStaffId = '';
                this.relocateRemarks = '';
                this.relocateOpen = true;
                this.$nextTick(() => this.$refs.relocateStaffSearch?.focus());
            }
        };
    }

    let deviceCameraStream = null;

    function renderEmptyDevicePhotoPreview() {
        const preview = document.getElementById('device-photo-preview');

        preview.innerHTML = '';

        const emptyState = document.createElement('div');
        emptyState.className = 'flex h-full items-center justify-center px-4 text-center text-sm text-gray-500 dark:text-gray-400';
        emptyState.textContent = 'No equipment photo uploaded.';
        preview.appendChild(emptyState);
    }

    function setDevicePhotoBusy(isBusy) {
        const button = document.getElementById('device-take-photo-button');
        const captureButton = document.getElementById('device-capture-photo-button');
        const clearButton = document.getElementById('device-clear-photo-button');

        [button, captureButton, clearButton].filter(Boolean).forEach((control) => {
            control.disabled = isBusy;
            control.classList.toggle('opacity-60', isBusy);
            control.classList.toggle('cursor-wait', isBusy);
        });
    }

    function closeDeviceCamera() {
        const panel = document.getElementById('device-camera-panel');
        const video = document.getElementById('device-camera-video');

        if (deviceCameraStream) {
            deviceCameraStream.getTracks().forEach((track) => track.stop());
            deviceCameraStream = null;
        }

        if (video) {
            video.srcObject = null;
        }

        panel?.classList.add('hidden');
        panel?.classList.remove('flex');
    }

    async function openDeviceCamera() {
        const panel = document.getElementById('device-camera-panel');
        const video = document.getElementById('device-camera-video');
        const status = document.getElementById('device-photo-status');

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            status.textContent = 'Camera access is not available in this browser.';
            return;
        }

        status.textContent = 'Opening camera...';
        setDevicePhotoBusy(true);

        try {
            deviceCameraStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: 'environment' },
                    width: { ideal: 1280 },
                    height: { ideal: 1280 }
                },
                audio: false
            });

            video.srcObject = deviceCameraStream;
            await video.play();

            panel.classList.remove('hidden');
            panel.classList.add('flex');
            status.textContent = 'Camera ready.';
        } catch (error) {
            status.textContent = window.isSecureContext
                ? 'Camera permission was blocked or no camera was found.'
                : 'Camera requires HTTPS or localhost.';
        } finally {
            setDevicePhotoBusy(false);
        }
    }

    async function captureDevicePhoto() {
        const form = document.getElementById('device-photo-form');
        const video = document.getElementById('device-camera-video');
        const canvas = document.getElementById('device-camera-canvas');
        const preview = document.getElementById('device-photo-preview');
        const status = document.getElementById('device-photo-status');

        if (!deviceCameraStream || !video.videoWidth || !video.videoHeight) {
            status.textContent = 'Camera is not ready yet.';
            return;
        }

        setDevicePhotoBusy(true);
        status.textContent = 'Saving photo...';

        try {
            const size = Math.min(video.videoWidth, video.videoHeight);
            const sourceX = (video.videoWidth - size) / 2;
            const sourceY = (video.videoHeight - size) / 2;

            canvas.width = 1280;
            canvas.height = 1280;
            canvas.getContext('2d').drawImage(video, sourceX, sourceY, size, size, 0, 0, canvas.width, canvas.height);

            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9));

            if (!blob || blob.size > 10 * 1024 * 1024) {
                throw new Error('The captured photo is larger than 10 MB.');
            }

            const formData = new FormData(form);
            formData.append('equipment_photo', blob, 'equipment-photo.jpg');

            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) throw new Error('Photo upload failed.');

            const result = await response.json();
            preview.innerHTML = '';

            const image = document.createElement('img');
            image.src = result.photo_url + '?v=' + Date.now();
            image.alt = 'Photo of equipment';
            image.className = 'h-full w-full object-cover';
            preview.appendChild(image);
            status.textContent = result.message;
            const clearButton = document.getElementById('device-clear-photo-button');
            clearButton?.classList.remove('hidden');
            clearButton?.classList.add('inline-flex');
            closeDeviceCamera();
        } catch (error) {
            status.textContent = error.message || 'Photo upload failed. Please try again.';
        } finally {
            setDevicePhotoBusy(false);
        }
    }

    async function clearDevicePhoto() {
        if (!confirm('Delete this equipment photo?')) return;

        const form = document.getElementById('device-photo-delete-form');
        const status = document.getElementById('device-photo-status');
        const clearButton = document.getElementById('device-clear-photo-button');

        setDevicePhotoBusy(true);
        status.textContent = 'Clearing photo...';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) throw new Error('Photo delete failed.');

            const result = await response.json();
            renderEmptyDevicePhotoPreview();
            clearButton?.classList.add('hidden');
            clearButton?.classList.remove('inline-flex');
            status.textContent = result.message;
        } catch (error) {
            status.textContent = error.message || 'Photo delete failed. Please try again.';
        } finally {
            setDevicePhotoBusy(false);
        }
    }
</script>

<div
    x-data="deviceEditor()"
    class="grid grid-cols-1 gap-6 lg:grid-cols-3"
>
    <div class="lg:col-span-2">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ $device->property_number }}
                    </h1>

                    <p class="mt-1 text-gray-500 dark:text-gray-400">
                        {{ $device->type?->name ?? 'Equipment' }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a
                        href="{{ route('admin.devices.history', $device) }}"
                        class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700"
                    >
                        History
                    </a>

                    @if($device->currentAssignment && $device->currentAssignment->staff)
                        <button type="button" x-on:click="openRelocation()" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                            Relocate
                        </button>
                    @endif

                    <a
                        href="{{ route('admin.devices.checklist.form', $device) }}"
                        class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 dark:bg-green-500 dark:hover:bg-green-600"
                    >
                        Mark as Checked
                    </a>

                    <button
                        id="open-edit-device-modal"
                        type="button"
                        x-on:click="editOpen = true"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                    >
                        Edit
                    </button>

                    @if(auth()->user()->isAdmin())
                        <form
                            method="POST"
                            action="{{ route('admin.devices.destroy', $device) }}"
                            onsubmit="return confirm('Delete this equipment?')"
                        >
                            @csrf
                            @method('DELETE')

                            <button
                                type="submit"
                                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                            >
                                Delete
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="mt-8 grid grid-cols-1 items-start gap-8 lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)]">
                <div>
                    <h2 class="font-semibold text-gray-900 dark:text-white">Equipment Photo</h2>
                    <div id="device-photo-preview" class="mt-3 aspect-square w-full overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900">
                        @if($device->photo_path)
                            <img
                                id="device-photo-image"
                                src="{{ asset('storage/' . $device->photo_path) }}"
                                alt="Photo of {{ $device->property_number }}"
                                class="h-full w-full object-cover"
                            >
                        @else
                            <div class="flex h-full items-center justify-center px-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                No equipment photo uploaded.
                            </div>
                        @endif
                    </div>
                    <form
                        id="device-photo-form"
                        method="POST"
                        action="{{ route('admin.devices.photo', $device) }}"
                        enctype="multipart/form-data"
                        class="hidden"
                    >
                        @csrf
                        @method('PATCH')
                    </form>
                    <form
                        id="device-photo-delete-form"
                        method="POST"
                        action="{{ route('admin.devices.photo.destroy', $device) }}"
                        class="hidden"
                    >
                        @csrf
                        @method('DELETE')
                    </form>
                    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <button
                            id="device-take-photo-button"
                            type="button"
                            onclick="openDeviceCamera()"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:bg-blue-500 dark:hover:bg-blue-600 dark:focus:ring-offset-gray-800"
                        >
                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.5A2.5 2.5 0 0 1 5.5 6H7l1.2-1.8A2 2 0 0 1 9.9 3.3h4.2a2 2 0 0 1 1.7.9L17 6h1.5A2.5 2.5 0 0 1 21 8.5v8A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5v-8Z" />
                                <circle cx="12" cy="12.5" r="3.5" />
                            </svg>
                            Take Photo
                        </button>
                        <button
                            id="device-clear-photo-button"
                            type="button"
                            onclick="clearDevicePhoto()"
                            class="{{ $device->photo_path ? 'inline-flex' : 'hidden' }} w-full items-center justify-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:bg-red-500 dark:hover:bg-red-600 dark:focus:ring-offset-gray-800"
                        >
                            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 6V4h8v2" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l1 14h10l1-14" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 11v5M14 11v5" />
                            </svg>
                            Clear Photo
                        </button>
                    </div>
                    <p id="device-photo-status" class="mt-2 text-xs text-gray-500 dark:text-gray-400" aria-live="polite"></p>
                </div>

                <div>
                    <h2 class="font-semibold text-gray-900 dark:text-white">Equipment Specifications</h2>
                    <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <div class="text-sm text-gray-500">Equipment Type</div>
                    <div class="font-medium text-gray-900">
                        {{ $device->type?->name ?? '-' }}
                    </div>
                </div>

                <div>
                    <div class="text-sm text-gray-500">Property Number</div>
                    <div class="font-medium text-gray-900">
                        {{ $device->property_number }}
                    </div>
                </div>

                <div>
                    <div class="text-sm text-gray-500">Serial Number</div>
                    <div class="font-medium text-gray-900">
                        {{ $device->serial_number ?: '-' }}
                    </div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Computer Name</div>
                    <div class="font-medium text-gray-900 dark:text-white">
                        {{ $device->computer_name ?: '-' }}
                    </div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Brand</div>
                    <div class="font-medium text-gray-900 dark:text-white">
                        {{ $device->brand ?: '-' }}
                    </div>
                </div>

                <div>
                    <div class="text-sm text-gray-500">Model</div>
                    <div class="font-medium text-gray-900">
                        {{ $device->model ?: '-' }}
                    </div>
                </div>

                @if($isComputerType)
                    <div>
                        <div class="text-sm text-gray-500">MAC Address</div>
                        <div class="font-medium text-gray-900">
                            {{ $device->mac_address ?: '-' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-500">Memory</div>
                        <div class="font-medium text-gray-900">
                            {{ data_get($device->specs, 'memory', '-') ?: '-' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-500">Storage</div>
                        <div class="font-medium text-gray-900">
                            {{ data_get($device->specs, 'storage', '-') ?: '-' }}
                        </div>
                    </div>

                    @if($isDesktopType)
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Form Factor</div>
                            <div class="font-medium text-gray-900 dark:text-white">
                                {{ data_get($device->specs, 'form_factor', '-') ?: '-' }}
                            </div>
                        </div>
                    @endif

                    <div>
                        <div class="text-sm text-gray-500">OS Version</div>
                        <div class="font-medium text-gray-900">{{ $device->os_version ?: '-' }}</div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-500">OS License</div>
                        <div class="font-medium text-gray-900">{{ $device->os_license ?: '-' }}</div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-500">MS Office Version</div>
                        <div class="font-medium text-gray-900">{{ $device->ms_office_version ?: '-' }}</div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-500">MS Office License</div>
                        <div class="font-medium text-gray-900">{{ $device->ms_office_license ?: '-' }}</div>
                    </div>
                @endif

                <div>
                    <div class="text-sm text-gray-500">Unit Price</div>
                    <div class="font-medium text-gray-900">
                        {{ $device->unit_price ? number_format($device->unit_price, 2) : '-' }}
                    </div>
                </div>

                <div>
                    <div class="text-sm text-gray-500">Date Acquired</div>
                    <div class="font-medium text-gray-900">
                        {{ $device->date_acquired ? $device->date_acquired->format('Y-m-d') : '-' }}
                    </div>
                </div>

                <div>
                    <div class="text-sm text-gray-500">Condition</div>
                    <div class="font-medium text-gray-900 capitalize">
                        {{ $device->condition ?? 'serviceable' }}
                    </div>
                </div>

                <div>
                    <div class="text-sm text-gray-500">Last Maintenance</div>
                    <div class="font-medium text-gray-900">
                        {{ $device->last_maintenance_date ? $device->last_maintenance_date->format('M d, Y') : 'Not yet checked' }}
                    </div>
                </div>
                    </div>
                </div>
            </div>

            @if($device->maintenance_remarks)
                <div class="mt-8 border-t border-gray-200 pt-6">
                    <h2 class="font-semibold text-gray-900">
                        Maintenance Remarks
                    </h2>

                    <p class="mt-3 text-gray-700">
                        {{ $device->maintenance_remarks }}
                    </p>
                </div>
            @endif

            <div class="mt-8 border-t border-gray-200 pt-6">
                <h2 class="font-semibold text-gray-900">
                    Current Assignment
                </h2>

                @if($device->currentAssignment && $device->currentAssignment->staff)
                    <div class="mt-3 rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <div class="font-medium text-gray-900">
                            {{ $device->currentAssignment->staff->last_name }},
                            {{ $device->currentAssignment->staff->first_name }}
                        </div>

                        <div class="mt-1 text-sm text-gray-500">
                            {{ $device->currentAssignment->staff->office?->name ?? 'No office' }}

                            @php
                                $assignmentLocation = $device->currentAssignment->staff->office?->location
                                    ?? $device->currentAssignment->staff->office?->college;
                            @endphp

                            @if($assignmentLocation)
                                /
                                {{ $assignmentLocation->name }}
                            @endif
                        </div>

                        <div class="mt-1 text-sm text-gray-500">
                            Issued:
                            {{ $device->currentAssignment->issued_at ? $device->currentAssignment->issued_at->format('M d, Y h:i A') : '-' }}
                        </div>
                    </div>
                @else
                    <p class="mt-3 text-gray-700">
                        This equipment is not currently issued.
                    </p>
                @endif
            </div>

            @if($device->notes)
                <div class="mt-8 border-t border-gray-200 pt-6">
                    <h2 class="font-semibold text-gray-900">
                        Notes
                    </h2>

                    <p class="mt-3 text-gray-700">
                        {{ $device->notes }}
                    </p>
                </div>
            @endif
        </div>
    </div>

    {{-- RELOCATION MODAL --}}
    <div x-show="relocateOpen" x-cloak @keydown.escape.window="relocateOpen = false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
        <div x-show="relocateOpen" @click.away="relocateOpen = false" class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-gray-800">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Relocate Equipment</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Select the new end user. Their office and location will be recorded automatically.</p>
                </div>
                <button type="button" x-on:click="relocateOpen = false" class="rounded-lg px-3 py-1 text-xl text-gray-500 hover:bg-gray-100">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.devices.relocate', $device) }}" class="space-y-4 px-6 py-5">
                @csrf
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Search end user</label>
                    <input type="text" x-ref="relocateStaffSearch" x-model="relocateStaffQuery" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="Name, position, office, or location">
                    <input type="hidden" name="staff_id" x-model="relocateStaffId">
                    <div class="mt-2 max-h-48 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700" x-show="!relocateStaffId">
                        <template x-for="staff in filteredRelocationStaff()" :key="staff.id">
                            <button type="button" x-on:click="selectRelocationStaff(staff)" class="block w-full px-3 py-2 text-left text-sm hover:bg-amber-50 dark:hover:bg-gray-700">
                                <span class="font-medium text-gray-900 dark:text-white" x-text="staff.name"></span>
                                <span class="block text-xs text-gray-500" x-text="staff.label"></span>
                            </button>
                        </template>
                        <div x-show="filteredRelocationStaff().length === 0" class="px-3 py-3 text-sm text-gray-500">No end user found.</div>
                    </div>
                    <div x-show="relocateStaffId" class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-900/20 dark:text-amber-100">Selected: <span class="font-medium" x-text="relocateStaffQuery"></span></div>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Relocation remarks</label>
                    <textarea name="remarks" x-model="relocateRemarks" rows="3" maxlength="1000" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="Reason or details of the relocation"></textarea>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                    <button type="button" x-on:click="relocateOpen = false" class="rounded-lg bg-gray-100 px-4 py-2 text-sm text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200">Cancel</button>
                    <button type="submit" :disabled="!relocateStaffId" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50">Save Relocation</button>
                </div>
            </form>
        </div>
    </div>

    {{-- EDIT MODAL --}}
    <div
        id="edit-device-modal"
        x-show="editOpen"
        x-cloak
        @keydown.escape.window="editOpen = false"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
    >
        <div
            x-show="editOpen"
            x-transition
            @click.away="editOpen = false"
            class="w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-gray-800"
        >
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Edit Equipment</h2>
                <button
                    type="button"
                    x-on:click="editOpen = false"
                    class="rounded-lg px-3 py-1 text-xl text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                >
                    &times;
                </button>
            </div>

            <form method="POST" action="{{ route('admin.devices.update', $device) }}" enctype="multipart/form-data" class="space-y-4" x-on:submit="cleanUnitPrices($event.target)">
                @csrf
                @method('PUT')

                <input type="hidden" name="status" value="{{ $device->status ?? 'available' }}">

                <div class="max-h-[75vh] overflow-y-auto px-6 py-5">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium dark:text-gray-300">Equipment Type</label>
                            <select
                                name="device_type_id"
                                x-model="selectedTypeId"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                required
                            >
                                @foreach($types as $type)
                                    <option value="{{ $type->id }}">
                                        {{ $type->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-medium dark:text-gray-300">Property Number</label>
                            <input
                                name="property_number"
                                value="{{ old('property_number', $device->property_number) }}"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
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
                                value="{{ old('serial_number', $device->serial_number) }}"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                maxlength="100"
                                pattern="[A-Za-z0-9\-]*"
                                title="Letters, numbers, and hyphens only"
                                placeholder="Enter serial number"
                            >
                        </div>

                        <div>
                            <label class="text-sm font-medium dark:text-gray-300">Computer Name</label>
                            <input
                                name="computer_name"
                                value="{{ old('computer_name', $device->computer_name) }}"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                maxlength="100"
                                pattern="[A-Za-z0-9][A-Za-z0-9\-\s]*"
                                title="Letters, numbers, hyphens, and spaces only"
                                placeholder="Enter computer name"
                            >
                        </div>

                        <div>
                            <label class="text-sm font-medium dark:text-gray-300">Brand</label>
                            <input
                                name="brand"
                                value="{{ old('brand', $device->brand) }}"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                maxlength="100"
                                pattern="[A-Za-zÑñ0-9][A-Za-zÑñ0-9.\-\s]*"
                                title="Letters and numbers only"
                            >
                        </div>

                        <div>
                            <label class="text-sm font-medium dark:text-gray-300">Model</label>
                            <input
                                name="model"
                                value="{{ old('model', $device->model) }}"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                maxlength="100"
                                pattern="[A-Za-z0-9][A-Za-z0-9.\-\/\s]*"
                                title="Letters and numbers only"
                            >
                        </div>

                        <div x-show="isComputerType()" x-cloak>
                            <label class="text-sm font-medium dark:text-gray-300">MAC Address</label>
                            <input
                                name="mac_address"
                                value="{{ old('mac_address', $device->mac_address) }}"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                maxlength="17"
                                pattern="[0-9A-Fa-f]{2}(:[0-9A-Fa-f]{2}){5}"
                                title="Format: 00:1A:2B:3C:4D:5E"
                                placeholder="00:1A:2B:3C:4D:5E"
                                :disabled="!isComputerType()"
                            >
                        </div>

                        <div x-show="isComputerType()" x-cloak>
                            <label class="text-sm font-medium dark:text-gray-300">Memory</label>
                            <input
                                name="specs[memory]"
                                value="{{ old('specs.memory', data_get($device->specs, 'memory')) }}"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                maxlength="50"
                                :disabled="!isComputerType()"
                            >
                        </div>

                        <div x-show="isComputerType()" x-cloak>
                            <label class="text-sm font-medium dark:text-gray-300">Storage</label>
                            <input
                                name="specs[storage]"
                                value="{{ old('specs.storage', data_get($device->specs, 'storage')) }}"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                maxlength="50"
                                :disabled="!isComputerType()"
                            >
                        </div>

                        <div x-show="isDesktopType()" x-cloak>
                            <label class="text-sm font-medium dark:text-gray-300">Form Factor</label>
                            <select
                                name="specs[form_factor]"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                :disabled="!isDesktopType()"
                            >
                                <option value="">-- Select Form Factor --</option>
                                <option value="Tower Desktops" @selected(old('specs.form_factor', data_get($device->specs, 'form_factor')) === 'Tower Desktops')>Tower Desktops</option>
                                <option value="Small Form Factor (SFF) Desktops" @selected(old('specs.form_factor', data_get($device->specs, 'form_factor')) === 'Small Form Factor (SFF) Desktops')>Small Form Factor (SFF) Desktops</option>
                                <option value="All-in-One (AIO) Desktops" @selected(old('specs.form_factor', data_get($device->specs, 'form_factor')) === 'All-in-One (AIO) Desktops')>All-in-One (AIO) Desktops</option>
                                <option value="Mini PCs" @selected(old('specs.form_factor', data_get($device->specs, 'form_factor')) === 'Mini PCs')>Mini PCs</option>
                                <option value="Workstations" @selected(old('specs.form_factor', data_get($device->specs, 'form_factor')) === 'Workstations')>Workstations</option>
                            </select>
                        </div>

                        {{-- OS Version --}}
                        <div id="show_os_version_wrapper" style="display:none;">
                            <label class="text-sm font-medium dark:text-gray-300">OS Version</label>
                            <select name="os_version" id="show_os_version_select" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <option value="">-- Select OS --</option>
                                <option value="Windows 7" {{ old('os_version', $device->os_version) === 'Windows 7' ? 'selected' : '' }}>Windows 7</option>
                                <option value="Windows 8" {{ old('os_version', $device->os_version) === 'Windows 8' ? 'selected' : '' }}>Windows 8</option>
                                <option value="Windows 10" {{ old('os_version', $device->os_version) === 'Windows 10' ? 'selected' : '' }}>Windows 10</option>
                                <option value="Windows 11" {{ old('os_version', $device->os_version) === 'Windows 11' ? 'selected' : '' }}>Windows 11</option>
                            </select>
                        </div>

                        {{-- OS License --}}
                        <div id="show_os_license_wrapper" style="display:none;">
                            <label class="text-sm font-medium dark:text-gray-300">OS License</label>
                            <select name="os_license" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <option value="">-- Select License --</option>
                                <option value="Cracked" {{ old('os_license', $device->os_license) === 'Cracked' ? 'selected' : '' }}>Cracked</option>
                                <option value="OEM Licensed" {{ old('os_license', $device->os_license) === 'OEM Licensed' ? 'selected' : '' }}>OEM Licensed</option>
                            </select>
                        </div>

                        {{-- MS Office Version --}}
                        <div id="show_ms_version_wrapper" style="display:none;">
                            <label class="text-sm font-medium dark:text-gray-300">MS Office Version</label>
                            <select name="ms_office_version" id="show_ms_version_select" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <option value="">-- Select MS Office --</option>
                                <option value="Office 2007" {{ old('ms_office_version', $device->ms_office_version) === 'Office 2007' ? 'selected' : '' }}>Office 2007</option>
                                <option value="Office 2010" {{ old('ms_office_version', $device->ms_office_version) === 'Office 2010' ? 'selected' : '' }}>Office 2010</option>
                                <option value="Office 2013" {{ old('ms_office_version', $device->ms_office_version) === 'Office 2013' ? 'selected' : '' }}>Office 2013</option>
                                <option value="Office 2016" {{ old('ms_office_version', $device->ms_office_version) === 'Office 2016' ? 'selected' : '' }}>Office 2016</option>
                                <option value="Office 2019" {{ old('ms_office_version', $device->ms_office_version) === 'Office 2019' ? 'selected' : '' }}>Office 2019</option>
                                <option value="Office 2021" {{ old('ms_office_version', $device->ms_office_version) === 'Office 2021' ? 'selected' : '' }}>Office 2021</option>
                                <option value="Microsoft 365" {{ old('ms_office_version', $device->ms_office_version) === 'Microsoft 365' ? 'selected' : '' }}>Microsoft 365</option>
                            </select>
                        </div>

                        {{-- MS Office License --}}
                        <div id="show_ms_license_wrapper" style="display:none;">
                            <label class="text-sm font-medium dark:text-gray-300">MS Office License</label>
                            <select name="ms_office_license" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <option value="">-- Select License --</option>
                                <option value="Cracked" {{ old('ms_office_license', $device->ms_office_license) === 'Cracked' ? 'selected' : '' }}>Cracked</option>
                                <option value="OEM Licensed" {{ old('ms_office_license', $device->ms_office_license) === 'OEM Licensed' ? 'selected' : '' }}>OEM Licensed</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-medium dark:text-gray-300">Unit Price</label>
                            <input
                                name="unit_price"
                                type="text"
                                inputmode="decimal"
                                value="{{ old('unit_price', $device->unit_price) }}"
                                class="unit-price-input mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                placeholder="e.g. 25,000.00"
                                x-on:input="formatUnitPriceInput($event)"
                            >
                        </div>

                        <div>
                            <label class="text-sm font-medium dark:text-gray-300">Date Acquired</label>
                            <input
                                name="date_acquired"
                                type="date"
                                max="{{ now()->format('Y-m-d') }}"
                                value="{{ old('date_acquired', $device->date_acquired ? $device->date_acquired->format('Y-m-d') : '') }}"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                        </div>

                        <div>
                            <label class="text-sm font-medium dark:text-gray-300">Condition</label>
                            <select
                                name="condition"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="serviceable" @selected(old('condition', $device->condition ?? 'serviceable') === 'serviceable')>
                                    Serviceable
                                </option>

                                <option value="unserviceable" @selected(old('condition', $device->condition) === 'unserviceable')>
                                    Unserviceable
                                </option>
                                <option value="condemned" @selected(old('condition', $device->condition) === 'condemned')>
                                    Condemned
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-medium dark:text-gray-300">Last Maintenance Date</label>
                            <input
                                name="last_maintenance_date"
                                type="date"
                                max="{{ now()->format('Y-m-d') }}"
                                value="{{ old('last_maintenance_date', $device->last_maintenance_date ? $device->last_maintenance_date->format('Y-m-d') : '') }}"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                        </div>

                        @include('admin.devices._photo-input', [
                            'photoInputId' => 'show_equipment_photo',
                            'existingPhotoPath' => $device->photo_path,
                        ])
                    </div>

                    <div class="mt-3">
                        <label class="text-sm font-medium dark:text-gray-300">Maintenance Remarks</label>
                        <textarea
                            name="maintenance_remarks"
                            rows="3"
                            maxlength="1000"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >{{ old('maintenance_remarks', $device->maintenance_remarks) }}</textarea>
                    </div>

                </div>

                <div class="flex justify-end gap-2 border-t border-gray-200 px-6 py-4 dark:border-gray-700">
                    <button
                        type="button"
                        x-on:click="editOpen = false"
                        class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                    >
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div
    id="device-camera-panel"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-gray-950/90 px-4 py-6"
    role="dialog"
    aria-modal="true"
    aria-label="Equipment camera"
>
    <div class="w-full max-w-md overflow-hidden rounded-xl border border-gray-700 bg-gray-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-gray-700 px-4 py-3">
            <h2 class="text-base font-semibold text-white">Take Equipment Photo</h2>
            <button
                type="button"
                onclick="closeDeviceCamera()"
                class="rounded-lg p-2 text-gray-300 hover:bg-gray-800 hover:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                aria-label="Close camera"
            >
                <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" />
                </svg>
            </button>
        </div>

        <div class="aspect-square bg-black">
            <video
                id="device-camera-video"
                class="h-full w-full object-cover"
                autoplay
                playsinline
                muted
            ></video>
            <canvas id="device-camera-canvas" class="hidden"></canvas>
        </div>

        <div class="flex gap-3 px-4 py-4">
            <button
                type="button"
                onclick="closeDeviceCamera()"
                class="inline-flex flex-1 items-center justify-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-gray-100 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500"
            >
                Cancel
            </button>
            <button
                id="device-capture-photo-button"
                type="button"
                onclick="captureDevicePhoto()"
                class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3.5" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 8.5A2.5 2.5 0 0 1 6.5 6H8l1.1-1.6a2 2 0 0 1 1.7-.9h2.4a2 2 0 0 1 1.7.9L16 6h1.5A2.5 2.5 0 0 1 20 8.5v7A2.5 2.5 0 0 1 17.5 18h-11A2.5 2.5 0 0 1 4 15.5v-7Z" />
                </svg>
                Capture Photo
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var typeName = @json(strtolower($device->type?->name ?? ''));
    var isComputer = typeName === 'desktop' || typeName === 'laptop';

    var osVerSel  = document.getElementById('show_os_version_select');
    var msVerSel  = document.getElementById('show_ms_version_select');
    var osVerWrap = document.getElementById('show_os_version_wrapper');
    var osLicWrap = document.getElementById('show_os_license_wrapper');
    var msVerWrap = document.getElementById('show_ms_version_wrapper');
    var msLicWrap = document.getElementById('show_ms_license_wrapper');

    function show(el) { if (el) el.style.display = ''; }
    function hide(el) { if (el) el.style.display = 'none'; }

    function init() {
        if (!isComputer) return;
        show(osVerWrap);
        show(msVerWrap);
        if (osVerSel && osVerSel.value) show(osLicWrap); else hide(osLicWrap);
        if (msVerSel && msVerSel.value) show(msLicWrap); else hide(msLicWrap);
    }

    if (osVerSel) {
        osVerSel.addEventListener('change', function () {
            this.value ? show(osLicWrap) : hide(osLicWrap);
        });
    }
    if (msVerSel) {
        msVerSel.addEventListener('change', function () {
            this.value ? show(msLicWrap) : hide(msLicWrap);
        });
    }

    // Re-init when quick edit panel opens
    document.addEventListener('click', function () {
        setTimeout(init, 150);
    });

    init();
})();
</script>
@endpush
@endsection
