@extends('admin.layouts.app')

@section('title', 'Add Equipment')
@section('page_title', 'Add Equipment')

@section('breadcrumb')
    <a class="text-blue-700 hover:underline" href="{{ route('admin.devices.index') }}">Equipment</a>
    <span class="mx-2">/</span>
    <span>Add Equipment</span>
@endsection

@section('content')
<div class="bg-white rounded shadow-sm p-6 max-w-4xl">
    <form method="POST" action="{{ route('admin.devices.store') }}" class="space-y-6">
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
                <label class="text-sm font-medium">Property Number</label>
                <input name="property_number"
                       value="{{ old('property_number') }}"
                       class="mt-1 w-full border rounded px-3 py-2"
                       required
                       maxlength="50"
                       pattern="[A-Za-z0-9][A-Za-z0-9\-\/]*"
                       title="Letters, numbers, hyphens, and slashes only">
                @error('property_number')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            {{-- Computer Name --}}
            <div>
                <label class="text-sm font-medium">Computer Name</label>
                <input name="computer_name"
                       value="{{ old('computer_name') }}"
                       class="mt-1 w-full border rounded px-3 py-2"
                       maxlength="100"
                       pattern="[A-Za-z0-9][A-Za-z0-9\-\s]*"
                       title="Letters, numbers, hyphens, and spaces only">
                @error('computer_name')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
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
            <div>
                <label class="text-sm font-medium">MAC Address</label>
                <input name="mac_address"
                       value="{{ old('mac_address') }}"
                       class="mt-1 w-full border rounded px-3 py-2"
                       maxlength="17"
                       pattern="[0-9A-Fa-f]{2}(:[0-9A-Fa-f]{2}){5}"
                       title="Format: 00:1A:2B:3C:4D:5E"
                       placeholder="00:1A:2B:3C:4D:5E">
                @error('mac_address')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
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

            {{-- Status --}}
            <div>
                <label class="text-sm font-medium">Status</label>
                <select name="status" class="mt-1 w-full border rounded px-3 py-2" required>
                    @php $status = old('status', 'available'); @endphp
                    <option value="available" @selected($status === 'available')>Available</option>
                    <option value="issued" @selected($status === 'issued')>Issued</option>
                    <option value="repair" @selected($status === 'repair')>Repair</option>
                    <option value="retired" @selected($status === 'retired')>Retired</option>
                </select>
                @error('status')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            {{-- Condition --}}
            <div>
                <label class="text-sm font-medium">Condition</label>
                <select name="condition" class="mt-1 w-full border rounded px-3 py-2">
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
</div>

@push('scripts')
<script>
    (function () {
        var typeSelect      = document.getElementById('device_type_select');
        var osVersionSel    = document.getElementById('os_version_select');
        var msVersionSel    = document.getElementById('ms_office_version_select');

        var osVersionWrap   = document.getElementById('os_version_wrapper');
        var osLicenseWrap   = document.getElementById('os_license_wrapper');
        var msVersionWrap   = document.getElementById('ms_office_version_wrapper');
        var msLicenseWrap   = document.getElementById('ms_office_license_wrapper');

        function isComputer(name) {
            return name === 'Desktop' || name === 'Laptop';
        }

        function show(el) { el.style.display = ''; }
        function hide(el) { el.style.display = 'none'; }

        function updateFields() {
            var selected = typeSelect.options[typeSelect.selectedIndex];
            var typeName = selected ? selected.dataset.name : '';
            var computer = isComputer(typeName);

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

        osVersionSel.addEventListener('change', function () {
            if (this.value) { show(osLicenseWrap); } else { hide(osLicenseWrap); }
        });

        msVersionSel.addEventListener('change', function () {
            if (this.value) { show(msLicenseWrap); } else { hide(msLicenseWrap); }
        });

        // Run on page load
        updateFields();
    })();
</script>
@endpush
@endsection
