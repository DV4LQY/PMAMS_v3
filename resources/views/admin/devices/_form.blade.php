@php
    $editing = isset($device);
    $selectedTypeId = old('device_type_id', $editing ? $device->device_type_id : null);
    $selectedType = $types->firstWhere('id', (int) $selectedTypeId);
    $typeSlug = $selectedType?->slug;
    $typeName = $selectedType?->name ?? '';
    $isComputer = in_array($typeName, ['Desktop', 'Laptop']);

    $existingSpecs = old('specs', $editing ? ($device->specs ?? []) : []);
    $oldOsVersion  = old('os_version', $editing ? ($device->os_version ?? '') : '');
    $oldOsLicense  = old('os_license', $editing ? ($device->os_license ?? '') : '');
    $oldMsVersion  = old('ms_office_version', $editing ? ($device->ms_office_version ?? '') : '');
    $oldMsLicense  = old('ms_office_license', $editing ? ($device->ms_office_license ?? '') : '');
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="text-sm font-medium">Equipment Type</label>
        <select name="device_type_id" id="device_type_select" class="mt-1 w-full border rounded px-3 py-2" required onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
            <option value="">Select type...</option>
            @foreach($types as $t)
                <option value="{{ $t->id }}" data-name="{{ $t->name }}" @selected((int)$selectedTypeId === $t->id)>
                    {{ $t->name }}
                </option>
            @endforeach
        </select>
        @error('device_type_id') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
        <div class="text-xs text-gray-500 mt-1">Changing type reloads form to show matching specs fields.</div>
    </div>

    <div>
        <label class="text-sm font-medium">Property Number</label>
        <input name="property_number" value="{{ old('property_number', $editing ? $device->property_number : '') }}"
               class="mt-1 w-full border rounded px-3 py-2" required>
        @error('property_number') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="text-sm font-medium">Brand</label>
        <input name="brand" value="{{ old('brand', $editing ? $device->brand : '') }}"
               class="mt-1 w-full border rounded px-3 py-2">
    </div>

    <div>
        <label class="text-sm font-medium">Model</label>
        <input name="model" value="{{ old('model', $editing ? $device->model : '') }}"
               class="mt-1 w-full border rounded px-3 py-2">
    </div>

    <div>
        <label class="text-sm font-medium">Serial Number</label>
        <input name="serial_number" value="{{ old('serial_number', $editing ? $device->serial_number : '') }}"
               class="mt-1 w-full border rounded px-3 py-2">
    </div>

    <div>
        <label class="text-sm font-medium">Unit Price</label>
        <input name="unit_price" type="number" step="0.01"
               value="{{ old('unit_price', $editing ? $device->unit_price : '') }}"
               class="mt-1 w-full border rounded px-3 py-2">
        @error('unit_price') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="text-sm font-medium">Status</label>
        <select name="status" class="mt-1 w-full border rounded px-3 py-2" required>
            @foreach(['available','issued','repair','retired'] as $st)
                <option value="{{ $st }}" @selected(old('status', $editing ? $device->status : 'available') === $st)>
                    {{ ucfirst($st) }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="text-sm font-medium">Last Maintenance Date</label>
        <input name="last_maintenance_date"
               type="date"
               value="{{ old('last_maintenance_date', $editing && $device->last_maintenance_date ? $device->last_maintenance_date->format('Y-m-d') : '') }}"
               class="mt-1 w-full border rounded px-3 py-2">
        @error('last_maintenance_date') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>

    <div>
        <label class="text-sm font-medium">Condition</label>
        <select name="condition" class="mt-1 w-full border rounded px-3 py-2">
            <option value="serviceable" @selected(old('condition', $editing ? ($device->condition ?? 'serviceable') : 'serviceable') === 'serviceable')>Serviceable</option>
            <option value="unserviceable" @selected(old('condition', $editing ? $device->condition : '') === 'unserviceable')>Unserviceable</option>
            <option value="condemned" @selected(old('condition', $editing ? $device->condition : '') === 'condemned')>Condemned</option>
        </select>
    </div>

    {{-- MAC Address (Computer only) --}}
    <div id="mac_address_wrapper" style="{{ $isComputer ? '' : 'display:none;' }}">
        <label class="text-sm font-medium">MAC Address</label>
        <input name="mac_address"
               value="{{ old('mac_address', $editing ? $device->mac_address : '') }}"
               class="mt-1 w-full border rounded px-3 py-2"
               maxlength="17"
               placeholder="00:1A:2B:3C:4D:5E">
        @error('mac_address') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>

    {{-- Memory (Computer only) --}}
    <div id="memory_wrapper" style="{{ $isComputer ? '' : 'display:none;' }}">
        <label class="text-sm font-medium">Memory</label>
        <input name="specs[memory]" value="{{ $existingSpecs['memory'] ?? '' }}"
               placeholder="Example: 8GB RAM"
               class="mt-1 w-full border rounded px-3 py-2">
    </div>

    {{-- Storage (Computer only) --}}
    <div id="storage_wrapper" style="{{ $isComputer ? '' : 'display:none;' }}">
        <label class="text-sm font-medium">Storage</label>
        <input name="specs[storage]" value="{{ $existingSpecs['storage'] ?? '' }}"
               placeholder="Example: 256GB SSD / 1TB HDD"
               class="mt-1 w-full border rounded px-3 py-2">
    </div>

    {{-- Form Factor (Computer only) --}}
    <div id="form_factor_wrapper" style="{{ $isComputer ? '' : 'display:none;' }}">
        <label class="text-sm font-medium">Form Factor</label>
        <input name="specs[form_factor]" value="{{ $existingSpecs['form_factor'] ?? '' }}"
               placeholder="Example: Tower, SFF, Mini PC"
               class="mt-1 w-full border rounded px-3 py-2">
    </div>

    {{-- OS Version (Computer only) --}}
    <div id="os_version_wrapper" style="{{ $isComputer ? '' : 'display:none;' }}">
        <label class="text-sm font-medium">OS Version</label>
        <select name="os_version" id="os_version_select" class="mt-1 w-full border rounded px-3 py-2">
            <option value="">-- Select OS --</option>
            <option value="Windows 7"  @selected($oldOsVersion === 'Windows 7')>Windows 7</option>
            <option value="Windows 8"  @selected($oldOsVersion === 'Windows 8')>Windows 8</option>
            <option value="Windows 10" @selected($oldOsVersion === 'Windows 10')>Windows 10</option>
            <option value="Windows 11" @selected($oldOsVersion === 'Windows 11')>Windows 11</option>
        </select>
        @error('os_version') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>

    {{-- OS License (shows after OS Version picked) --}}
    <div id="os_license_wrapper" style="{{ ($isComputer && $oldOsVersion) ? '' : 'display:none;' }}">
        <label class="text-sm font-medium">OS License</label>
        <select name="os_license" class="mt-1 w-full border rounded px-3 py-2">
            <option value="">-- Select License --</option>
            <option value="Cracked"     @selected($oldOsLicense === 'Cracked')>Cracked</option>
            <option value="OEM Licensed" @selected($oldOsLicense === 'OEM Licensed')>OEM Licensed</option>
        </select>
        @error('os_license') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>

    {{-- MS Office Version (Computer only) --}}
    <div id="ms_office_version_wrapper" style="{{ $isComputer ? '' : 'display:none;' }}">
        <label class="text-sm font-medium">MS Office Version</label>
        <select name="ms_office_version" id="ms_office_version_select" class="mt-1 w-full border rounded px-3 py-2">
            <option value="">-- Select MS Office --</option>
            <option value="Office 2007"   @selected($oldMsVersion === 'Office 2007')>Office 2007</option>
            <option value="Office 2010"   @selected($oldMsVersion === 'Office 2010')>Office 2010</option>
            <option value="Office 2013"   @selected($oldMsVersion === 'Office 2013')>Office 2013</option>
            <option value="Office 2016"   @selected($oldMsVersion === 'Office 2016')>Office 2016</option>
            <option value="Office 2019"   @selected($oldMsVersion === 'Office 2019')>Office 2019</option>
            <option value="Office 2021"   @selected($oldMsVersion === 'Office 2021')>Office 2021</option>
            <option value="Microsoft 365" @selected($oldMsVersion === 'Microsoft 365')>Microsoft 365</option>
        </select>
        @error('ms_office_version') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>

    {{-- MS Office License (shows after MS Office Version picked) --}}
    <div id="ms_office_license_wrapper" style="{{ ($isComputer && $oldMsVersion) ? '' : 'display:none;' }}">
        <label class="text-sm font-medium">MS Office License</label>
        <select name="ms_office_license" class="mt-1 w-full border rounded px-3 py-2">
            <option value="">-- Select License --</option>
            <option value="Cracked"      @selected($oldMsLicense === 'Cracked')>Cracked</option>
            <option value="OEM Licensed" @selected($oldMsLicense === 'OEM Licensed')>OEM Licensed</option>
        </select>
        @error('ms_office_license') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mt-6">
    <label class="text-sm font-medium">Maintenance Remarks</label>
    <textarea name="maintenance_remarks"
              class="mt-1 w-full border rounded px-3 py-2"
              rows="3"
              placeholder="Example: Cleaned, checked power supply, updated software">{{ old('maintenance_remarks', $editing ? $device->maintenance_remarks : '') }}</textarea>
    @error('maintenance_remarks') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
</div>

<script>
(function () {
    var typeSelect     = document.getElementById('device_type_select');
    var osVersionSel   = document.getElementById('os_version_select');
    var msVersionSel   = document.getElementById('ms_office_version_select');

    var computerFields = [
        document.getElementById('mac_address_wrapper'),
        document.getElementById('memory_wrapper'),
        document.getElementById('storage_wrapper'),
        document.getElementById('form_factor_wrapper'),
        document.getElementById('os_version_wrapper'),
        document.getElementById('ms_office_version_wrapper'),
    ];

    var osLicenseWrap = document.getElementById('os_license_wrapper');
    var msLicenseWrap = document.getElementById('ms_office_license_wrapper');

    function isComputer(name) {
        return name === 'Desktop' || name === 'Laptop';
    }

    function show(el) { if (el) el.style.display = ''; }
    function hide(el) { if (el) el.style.display = 'none'; }

    function updateFields() {
        var selected = typeSelect.options[typeSelect.selectedIndex];
        var typeName = selected ? (selected.dataset.name || selected.text) : '';
        var computer = isComputer(typeName);

        computerFields.forEach(function(el) {
            computer ? show(el) : hide(el);
        });

        if (computer) {
            osVersionSel.value ? show(osLicenseWrap) : hide(osLicenseWrap);
            msVersionSel.value ? show(msLicenseWrap) : hide(msLicenseWrap);
        } else {
            hide(osLicenseWrap);
            hide(msLicenseWrap);
        }
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', updateFields);
    }

    if (osVersionSel) {
        osVersionSel.addEventListener('change', function () {
            this.value ? show(osLicenseWrap) : hide(osLicenseWrap);
        });
    }

    if (msVersionSel) {
        msVersionSel.addEventListener('change', function () {
            this.value ? show(msLicenseWrap) : hide(msLicenseWrap);
        });
    }

    updateFields();
})();
</script>s
