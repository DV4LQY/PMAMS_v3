@extends('admin.layouts.app')

@section('title', 'Add Equipment')
@section('page_title', 'Add Equipment')

@section('breadcrumb')
    <a class="text-blue-700 hover:underline" href="{{ route('admin.devices.index') }}">Equipment</a>
    <span class="mx-2">/</span>
    <span>Add Equipment</span>
@endsection

@php
    $createStorage = trim((string) old('specs.storage', ''));
    $createStorageType = preg_match('/\b(HDD|SSD)\s*$/i', $createStorage, $createStorageTypeMatch)
        ? strtoupper($createStorageTypeMatch[1])
        : '';
    $createStorageCapacity = $createStorageType !== ''
        ? trim((string) preg_replace('/\s*(HDD|SSD)\s*$/i', '', $createStorage))
        : '';
@endphp

@section('content')
<div class="bg-white rounded shadow-sm p-6 max-w-4xl">
    <form method="POST" action="{{ route('admin.devices.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Equipment Type --}}
            <div>
                <label class="text-sm font-medium">Equipment Type</label>
                <select name="device_type_id"
                        id="device_type_select"
                        class="mt-1 w-full border rounded px-3 py-2"
                        required>
                    @foreach($types as $t)
                        <option value="{{ $t->id }}"
                            data-name="{{ $t->name }}"
                            @selected(old('device_type_id') == $t->id)>
                            {{ $t->name }}
                        </option>
                    @endforeach
                </select>
                @error('device_type_id')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            {{-- Property Number --}}
            <div>
                <label class="text-sm font-medium">Property Number <span class="font-normal text-gray-500">(optional when linked)</span></label>
                <input name="property_number"
                       value="{{ old('property_number') }}"
                       class="mt-1 w-full border rounded px-3 py-2"
                       maxlength="50"
                       pattern="[A-Za-z0-9][A-Za-z0-9\-\/]*"
                       title="Letters, numbers, hyphens, and slashes only">
                @error('property_number')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
                <div class="text-xs text-gray-500 mt-1">Leave blank when using a parent property number below.</div>
            </div>

            @include('admin.devices._part-property-number-field')

            {{-- Computer Name --}}
            <div id="computer_name_wrapper" data-equipment-field="computer">
                <label class="text-sm font-medium">Computer Name</label>
                <input name="computer_name"
                       id="computer_name_input"
                       value="{{ old('computer_name') }}"
                       class="mt-1 w-full border rounded px-3 py-2"
                       maxlength="100"
                       pattern="[A-Za-z0-9][A-Za-z0-9\-\s]*"
                       title="Letters, numbers, hyphens, and spaces only">
                @error('computer_name')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="text-sm font-medium">Serial Number</label>
                <input name="serial_number" value="{{ old('serial_number') }}" maxlength="100" pattern="[A-Za-z0-9\-]*" title="Letters, numbers, and hyphens only" class="mt-1 w-full border rounded px-3 py-2" placeholder="Enter serial number">
                @error('serial_number')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
            </div>

            {{-- Brand --}}
            <div>
                <label class="text-sm font-medium">Brand</label>
                <input name="brand"
                       maxlength="100"
                       pattern="[A-Za-zÑñ0-9][A-Za-zÑñ0-9.\-\s]*"
                       title="Letters and numbers only"
                       value="{{ old('brand') }}"
                       class="mt-1 w-full border rounded px-3 py-2">
                @error('brand')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="text-sm font-medium">Model</label>
                <input name="model" value="{{ old('model') }}" maxlength="100" pattern="[A-Za-z0-9][A-Za-z0-9.\-\/\s]*" title="Letters and numbers only" class="mt-1 w-full border rounded px-3 py-2" placeholder="Example: L3210, 2199">
                @error('model')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
            </div>

            <div id="network_device_type_wrapper" style="display:none;">
                <label class="text-sm font-medium">Network Device Type</label>
                <select name="network_device_type" id="network_device_type_select" class="mt-1 w-full border rounded px-3 py-2">
                    <option value="">-- Select Network Device Type --</option>
                    @foreach(['Access point', 'Router', 'Switch (managed)', 'Switch (unmanaged)'] as $networkType)
                        <option value="{{ $networkType }}" @selected(old('network_device_type') === $networkType)>{{ $networkType }}</option>
                    @endforeach
                </select>
                @error('network_device_type')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
            </div>

            <div id="location_deployed_wrapper" style="display:none;">
                <label class="text-sm font-medium">Location Deployed</label>
                <input name="location_deployed" list="network-location-options" data-location-deployed-search data-location-deployed-input value="{{ old('location_deployed') }}" maxlength="255" class="mt-1 w-full border rounded px-3 py-2" placeholder="e.g. Server Room, 2nd Floor">
                <input type="hidden" name="location_deployed_id" data-location-deployed-id value="{{ old('location_deployed_id') }}">
                <input type="hidden" name="office_deployed_id" data-office-deployed-id value="{{ old('office_deployed_id') }}">
                <p class="mt-1 text-xs text-gray-500">Choose a registered Location or Office from the suggestions to save the reference.</p>
                @error('location_deployed')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                @error('location_deployed_id')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                @error('office_deployed_id')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
            </div>

            {{-- Unit Price --}}
            <div>
                <label class="text-sm font-medium">Unit Price</label>
                <input name="unit_price"
                       type="number"
                       step="0.01"
                       min="0"
                       max="9999999999.99"
                       value="{{ old('unit_price') }}"
                       class="mt-1 w-full border rounded px-3 py-2">
                @error('unit_price')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            {{-- MAC Address --}}
            <div id="mac_address_wrapper">
                <label class="text-sm font-medium">MAC Address</label>
                <input name="mac_address"
                       value="{{ old('mac_address') }}"
                       class="mt-1 w-full border rounded px-3 py-2"
                       maxlength="100"
                       pattern="[0-9A-Fa-f]{2}(:[0-9A-Fa-f]{2}){5}(;\s*[0-9A-Fa-f]{2}(:[0-9A-Fa-f]{2}){5})*"
                       title="Enter one or more MAC addresses separated by semicolons"
                       placeholder="90:DE:80:08:8D:5C; 00:DE:80:08:8D:5C">
                @error('mac_address')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            {{-- Memory (Computer only) --}}
            <div id="memory_wrapper" style="display:none;">
                <label class="text-sm font-medium">Memory</label>
                <select name="specs[memory]" id="memory_select" class="mt-1 w-full border rounded px-3 py-2" disabled>
                    <option value="">-- Select Memory --</option>
                    @foreach(['2GB', '4GB', '8GB', '16GB', '32GB', '64GB'] as $memoryOption)
                        <option value="{{ $memoryOption }}" @selected(old('specs.memory') === $memoryOption)>{{ $memoryOption }}</option>
                    @endforeach
                    @if(old('specs.memory') && !in_array(old('specs.memory'), ['2GB', '4GB', '8GB', '16GB', '32GB', '64GB'], true))
                        <option value="{{ old('specs.memory') }}" selected>{{ old('specs.memory') }}</option>
                    @endif
                </select>
                @error('specs.memory')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
            </div>

            {{-- Storage (Computer only) --}}
            <div id="storage_wrapper" style="display:none;">
                <label class="text-sm font-medium">Storage</label>
                <div class="mt-1 grid grid-cols-2 gap-2">
                    <select id="storage_type_select" class="w-full border rounded px-3 py-2" disabled>
                        <option value="">Select storage type</option>
                        <option value="SSD" @selected($createStorageType === 'SSD')>SSD</option>
                        <option value="HDD" @selected($createStorageType === 'HDD')>HDD</option>
                    </select>
                    <select id="storage_capacity_select" class="w-full border rounded px-3 py-2" data-initial-capacity="{{ $createStorageCapacity }}" style="display:none;" disabled>
                        <option value="" selected>Select capacity</option>
                    </select>
                </div>
                <input type="hidden" name="specs[storage]" id="storage_value_input" value="{{ $createStorage }}" data-raw-value="{{ $createStorage }}" disabled>
                <p class="mt-1 text-xs text-gray-500">Select a drive type, then choose its capacity.</p>
                @error('specs.storage')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
            </div>

            {{-- Date Acquired --}}
            <div>
                <label class="text-sm font-medium">Date Acquired</label>
                <input type="date"
                       max="{{ now()->format('Y-m-d') }}"
                       name="date_acquired"
                       value="{{ old('date_acquired') }}"
                       class="mt-1 w-full border rounded px-3 py-2">
                @error('date_acquired')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            {{-- Status is editable when an item is marked unserviceable. --}}
            <div id="device_status_wrapper" style="display: none;">
                <label class="text-sm font-medium">Status</label>
                <select name="status" id="device_status_select" class="mt-1 w-full border rounded px-3 py-2">
                    @foreach(['repair', 'not_in_use', 'available', 'issued'] as $statusOption)
                        <option value="{{ $statusOption }}" @selected(old('status', 'available') === $statusOption)>
                            {{ $statusOption === 'not_in_use' ? 'Not in Use' : ucfirst($statusOption) }}
                        </option>
                    @endforeach
                </select>
                @error('status')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                <div class="text-xs text-gray-500 mt-1">Shown only for equipment marked unserviceable.</div>
            </div>

            {{-- Condition --}}
            <div>
                <label class="text-sm font-medium">Condition</label>
                <select name="condition" id="device_condition_select" class="mt-1 w-full border rounded px-3 py-2">
                    <option value="serviceable" @selected(old('condition', 'serviceable') === 'serviceable')>Serviceable</option>
                    <option value="unserviceable" @selected(old('condition') === 'unserviceable')>Unserviceable</option>
                    <option value="condemned" @selected(old('condition') === 'condemned')>Condemned</option>
                </select>
                @error('condition')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
            </div>

            {{-- OS Version (Computer only) --}}
            <div id="os_version_wrapper" style="display:none;">
                <label class="text-sm font-medium">OS Version</label>
                <select name="os_version"
                        id="os_version_select"
                        class="mt-1 w-full border rounded px-3 py-2">
                    <option value="">-- Select OS --</option>
                    <option value="Windows 7" @selected(old('os_version') === 'Windows 7')>Windows 7</option>
                    <option value="Windows 8" @selected(old('os_version') === 'Windows 8')>Windows 8</option>
                    <option value="Windows 10" @selected(old('os_version') === 'Windows 10')>Windows 10</option>
                    <option value="Windows 11" @selected(old('os_version') === 'Windows 11')>Windows 11</option>
                    <option value="Windows Server" @selected(old('os_version') === 'Windows Server')>Windows Server</option>
                    <option value="Linux" @selected(old('os_version') === 'Linux')>Linux</option>
                </select>
                @error('os_version')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            {{-- OS License --}}
            <div id="os_license_wrapper" style="display:none;">
                <label class="text-sm font-medium">OS License</label>
                <select name="os_license"
                        class="mt-1 w-full border rounded px-3 py-2">
                    <option value="">-- Select License --</option>
                    <option value="Cracked" @selected(old('os_license') === 'Cracked')>Cracked</option>
                    <option value="OEM Licensed" @selected(old('os_license') === 'OEM Licensed')>OEM Licensed</option>
                    <option value="Open Source" @selected(old('os_license') === 'Open Source')>Open Source</option>
                </select>
                @error('os_license')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            {{-- MS Office Version (Computer only) --}}
            <div id="ms_office_version_wrapper" style="display:none;">
                <label class="text-sm font-medium">MS Office Version</label>
                <select name="ms_office_version"
                        id="ms_office_version_select"
                        class="mt-1 w-full border rounded px-3 py-2">
                    <option value="">-- Select MS Office --</option>
                    <option value="Office 2007" @selected(old('ms_office_version') === 'Office 2007')>Office 2007</option>
                    <option value="Office 2010" @selected(old('ms_office_version') === 'Office 2010')>Office 2010</option>
                    <option value="Office 2013" @selected(old('ms_office_version') === 'Office 2013')>Office 2013</option>
                    <option value="Office 2016" @selected(old('ms_office_version') === 'Office 2016')>Office 2016</option>
                    <option value="Office 2019" @selected(old('ms_office_version') === 'Office 2019')>Office 2019</option>
                    <option value="Office 2021" @selected(old('ms_office_version') === 'Office 2021')>Office 2021</option>
                    <option value="Microsoft 365" @selected(old('ms_office_version') === 'Microsoft 365')>Microsoft 365</option>
                </select>
                @error('ms_office_version')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            {{-- MS Office License --}}
            <div id="ms_office_license_wrapper" style="display:none;">
                <label class="text-sm font-medium">MS Office License</label>
                <select name="ms_office_license"
                        class="mt-1 w-full border rounded px-3 py-2">
                    <option value="">-- Select License --</option>
                    <option value="Cracked" @selected(old('ms_office_license') === 'Cracked')>Cracked</option>
                    <option value="OEM Licensed" @selected(old('ms_office_license') === 'OEM Licensed')>OEM Licensed</option>
                </select>
                @error('ms_office_license')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

        </div>

        <div class="flex gap-2">
            <button class="px-4 py-2 rounded bg-blue-600 text-white">Save</button>
            <a href="{{ route('admin.devices.index') }}" class="px-4 py-2 rounded bg-gray-100">Cancel</a>
        </div>
    </form>
    <datalist id="network-location-options"></datalist>
</div>

@push('scripts')
<script>
    (function () {
        var typeSelect      = document.getElementById('device_type_select');
        var conditionSelect = document.getElementById('device_condition_select');
        var statusWrap      = document.getElementById('device_status_wrapper');
        var statusSelect    = document.getElementById('device_status_select');
        var osVersionSel    = document.getElementById('os_version_select');
        var msVersionSel    = document.getElementById('ms_office_version_select');
        var networkTypeWrap = document.getElementById('network_device_type_wrapper');
        var locationDeployedWrap = document.getElementById('location_deployed_wrapper');
        var macAddressWrap = document.getElementById('mac_address_wrapper');

        var osVersionWrap   = document.getElementById('os_version_wrapper');
        var osLicenseWrap   = document.getElementById('os_license_wrapper');
        var msVersionWrap   = document.getElementById('ms_office_version_wrapper');
        var msLicenseWrap   = document.getElementById('ms_office_license_wrapper');
        var computerNameWrap = document.getElementById('computer_name_wrapper');
        var computerNameInput = document.getElementById('computer_name_input');
        var memoryWrap = document.getElementById('memory_wrapper');
        var memorySelect = document.getElementById('memory_select');
        var storageWrap = document.getElementById('storage_wrapper');
        var storageTypeSelect = document.getElementById('storage_type_select');
        var storageCapacitySelect = document.getElementById('storage_capacity_select');
        var storageValueInput = document.getElementById('storage_value_input');
        var storageCapacities = {
            SSD: ['128GB', '256GB', '480GB', '512GB', '1TB', '2TB'],
            HDD: ['256GB', '500GB', '1TB', '2TB']
        };

        function isComputer(name) {
            name = String(name || '').trim().toLowerCase();
            return name === 'desktop' || name === 'laptop';
        }

        function isNetworkDevice(name) {
            return String(name || '').trim().toLowerCase() === 'network device';
        }

        function show(el) { el.style.display = ''; }
        function hide(el) { el.style.display = 'none'; }

        function syncStorageValue() {
            if (!storageTypeSelect || !storageCapacitySelect || !storageValueInput) return;
            var type = storageTypeSelect.value || '';
            var capacity = storageCapacitySelect.value || '';
            storageValueInput.value = type && capacity
                ? capacity + ' ' + type
                : (storageValueInput.dataset.rawValue || '');
        }

        function updateStorageCapacities(resetCapacity) {
            if (!storageTypeSelect || !storageCapacitySelect) return;
            var type = storageTypeSelect.value || '';
            var previous = resetCapacity ? '' : (storageCapacitySelect.value || storageCapacitySelect.dataset.initialCapacity || '');
            if (resetCapacity && storageValueInput) storageValueInput.dataset.rawValue = '';
            storageCapacitySelect.replaceChildren();
            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Select capacity';
            storageCapacitySelect.appendChild(placeholder);
            (storageCapacities[type] || []).forEach(function (capacity) {
                var option = document.createElement('option');
                option.value = capacity;
                option.textContent = capacity;
                storageCapacitySelect.appendChild(option);
            });
            if (previous && (storageCapacities[type] || []).includes(previous)) {
                storageCapacitySelect.value = previous;
            }
            syncStorageValue();
        }

        function updateStatusField() {
            var visible = conditionSelect && String(conditionSelect.value || '').toLowerCase() === 'unserviceable';
            if (!statusWrap || !statusSelect) return;

            if (visible) {
                show(statusWrap);
                statusSelect.disabled = false;
            } else {
                hide(statusWrap);
                statusSelect.disabled = true;
            }
        }

        function updateFields() {
            var selected = typeSelect.options[typeSelect.selectedIndex];
            var typeName = selected ? selected.dataset.name : '';
            var computer = isComputer(typeName);
            var networkDevice = isNetworkDevice(typeName);

            if (networkTypeWrap) networkDevice ? show(networkTypeWrap) : hide(networkTypeWrap);
            if (locationDeployedWrap) networkDevice ? show(locationDeployedWrap) : hide(locationDeployedWrap);
            if (macAddressWrap) networkDevice || computer ? show(macAddressWrap) : hide(macAddressWrap);

            if (computer) {
                show(computerNameWrap);
                computerNameInput.disabled = false;
                show(memoryWrap);
                memorySelect.disabled = false;
                show(storageWrap);
                storageTypeSelect.disabled = false;
                storageCapacitySelect.disabled = !storageTypeSelect.value;
                storageValueInput.disabled = false;
            } else {
                hide(computerNameWrap);
                computerNameInput.disabled = true;
                computerNameInput.value = '';
                hide(memoryWrap);
                memorySelect.disabled = true;
                hide(storageWrap);
                storageTypeSelect.disabled = true;
                storageCapacitySelect.disabled = true;
                storageValueInput.disabled = true;
                storageTypeSelect.value = '';
                updateStorageCapacities(true);
            }

            if (computer) {
                show(osVersionWrap);
                show(msVersionWrap);
                // OS License only if OS version picked
                if (osVersionSel.value) { show(osLicenseWrap); } else { hide(osLicenseWrap); }
                // MS License only if MS version picked
                if (msVersionSel.value) { show(msLicenseWrap); } else { hide(msLicenseWrap); }
            } else {
                hide(osVersionWrap);
                hide(osLicenseWrap);
                hide(msVersionWrap);
                hide(msLicenseWrap);
            }
        }

        typeSelect.addEventListener('change', updateFields);
        conditionSelect.addEventListener('change', updateStatusField);
            storageTypeSelect.addEventListener('change', function () {
                updateStorageCapacities(true);
                storageCapacitySelect.style.display = this.value ? '' : 'none';
                storageCapacitySelect.disabled = !this.value;
            });
        storageCapacitySelect.addEventListener('change', syncStorageValue);

        osVersionSel.addEventListener('change', function () {
            if (this.value) { show(osLicenseWrap); } else { hide(osLicenseWrap); }
        });

        msVersionSel.addEventListener('change', function () {
            if (this.value) { show(msLicenseWrap); } else { hide(msLicenseWrap); }
        });

        // Run on page load
        updateStorageCapacities(false);
        storageCapacitySelect.style.display = storageTypeSelect.value ? '' : 'none';
        updateFields();
        updateStatusField();
    })();
</script>
<script>
    (function () {
        if (window.__pmamsLocationLookupInit) return;
        window.__pmamsLocationLookupInit = true;
        const lookupUrl = @js(route('admin.devices.lookup.location'));
        const list = document.getElementById('network-location-options');
        const timers = new WeakMap();
        const aborters = new WeakMap();
        const resultMap = new Map();
        const populate = (results) => {
            if (!list) return;
            resultMap.clear();
            list.replaceChildren(...(Array.isArray(results) ? results : []).map((result) => {
                const option = document.createElement('option');
                option.value = result.label || result.name || result.code || '';
                if (result.id) resultMap.set(option.value, {
                    locationId: result.location_id || (result.type === 'location' ? result.id : ''),
                    officeId: result.office_id || (result.type === 'office' ? result.id : '')
                });
                return option;
            }));
        };
        const syncHidden = (input) => {
            const selected = resultMap.get(String(input.value || '').trim());
            const location = input.form?.querySelector('[data-location-deployed-id]');
            const office = input.form?.querySelector('[data-office-deployed-id]');
            if (location) location.value = selected?.locationId || '';
            if (office) office.value = selected?.officeId || '';
        };
        const search = async (input) => {
            const query = String(input.value || '').trim();
            aborters.get(input)?.abort();
            if (!query) return populate([]);
            const aborter = new AbortController();
            aborters.set(input, aborter);
            try {
                const url = new URL(lookupUrl, window.location.origin);
                url.searchParams.set('q', query);
                const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: aborter.signal });
                if (!response.ok) throw new Error('lookup failed');
                populate((await response.json()).results || []);
            } catch (error) {
                if (error.name !== 'AbortError') populate([]);
            }
        };
        document.addEventListener('input', (event) => {
            const input = event.target.closest('input[data-location-deployed-search]');
            if (!input) return;
            if (!resultMap.has(String(input.value || '').trim())) {
                const location = input.form?.querySelector('[data-location-deployed-id]');
                const office = input.form?.querySelector('[data-office-deployed-id]');
                if (location) location.value = '';
                if (office) office.value = '';
            }
            clearTimeout(timers.get(input));
            timers.set(input, setTimeout(() => search(input), 180));
        });
        document.addEventListener('change', (event) => {
            const input = event.target.closest('input[data-location-deployed-input]');
            if (!input) return;
            syncHidden(input);
        });
        document.addEventListener('submit', (event) => {
            const input = event.target.querySelector?.('input[data-location-deployed-input]');
            if (input) syncHidden(input);
        });
    })();
</script>
@endpush
@endsection
