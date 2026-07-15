@extends('admin.layouts.app')

@section('title', 'Edit Equipment')
@section('page_title', 'Edit Equipment')

@section('breadcrumb')
    <a class="text-blue-700 hover:underline" href="{{ route('admin.devices.index') }}">Equipment</a>
    <span class="mx-2">/</span>
    <span>Edit Equipment</span>
@endsection

@section('content')
<div class="bg-white rounded shadow-sm p-6 max-w-4xl">
    <form method="POST" action="{{ route('admin.devices.update', $device) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        @php
            $selectedTypeName = old('device_type_name', $device->deviceType->name ?? '');
            $oldOsVersion     = old('os_version', $device->os_version ?? '');
            $oldOsLicense     = old('os_license', $device->os_license ?? '');
            $oldMsVersion     = old('ms_office_version', $device->ms_office_version ?? '');
            $oldMsLicense     = old('ms_office_license', $device->ms_office_license ?? '');
        @endphp

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
                            @selected(old('device_type_id', $device->device_type_id) == $t->id)>
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
                       value="{{ old('property_number', $device->property_number) }}"
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
                       value="{{ old('computer_name', $device->computer_name) }}"
                       class="mt-1 w-full border rounded px-3 py-2"
                       maxlength="100"
                       pattern="[A-Za-z0-9][A-Za-z0-9\-\s]*"
                       title="Letters and numbers only">
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
                       value="{{ old('brand', $device->brand) }}"
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
                       value="{{ old('unit_price', $device->unit_price) }}"
                       class="mt-1 w-full border rounded px-3 py-2">
                @error('unit_price')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            {{-- MAC Address --}}
            <div>
                <label class="text-sm font-medium">MAC Address</label>
                <input name="mac_address"
                       value="{{ old('mac_address', $device->mac_address) }}"
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
                       value="{{ old('date_acquired', $device->date_acquired) }}"
                       class="mt-1 w-full border rounded px-3 py-2">
                @error('date_acquired')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            {{-- Status --}}
            <div>
                <label class="text-sm font-medium">Status</label>
                @php $status = old('status', $device->status); @endphp
                <select name="status" class="mt-1 w-full border rounded px-3 py-2" required>
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
                    <option value="serviceable" @selected(old('condition', $device->condition ?? 'serviceable') === 'serviceable')>Serviceable</option>
                    <option value="unserviceable" @selected(old('condition', $device->condition) === 'unserviceable')>Unserviceable</option>
                    <option value="condemned" @selected(old('condition', $device->condition) === 'condemned')>Condemned</option>
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
                    <option value="Windows 7" @selected($oldOsVersion === 'Windows 7')>Windows 7</option>
                    <option value="Windows 8" @selected($oldOsVersion === 'Windows 8')>Windows 8</option>
                    <option value="Windows 10" @selected($oldOsVersion === 'Windows 10')>Windows 10</option>
                    <option value="Windows 11" @selected($oldOsVersion === 'Windows 11')>Windows 11</option>
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
                    <option value="Cracked" @selected($oldOsLicense === 'Cracked')>Cracked</option>
                    <option value="OEM Licensed" @selected($oldOsLicense === 'OEM Licensed')>OEM Licensed</option>
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
                    <option value="Office 2007" @selected($oldMsVersion === 'Office 2007')>Office 2007</option>
                    <option value="Office 2010" @selected($oldMsVersion === 'Office 2010')>Office 2010</option>
                    <option value="Office 2013" @selected($oldMsVersion === 'Office 2013')>Office 2013</option>
                    <option value="Office 2016" @selected($oldMsVersion === 'Office 2016')>Office 2016</option>
                    <option value="Office 2019" @selected($oldMsVersion === 'Office 2019')>Office 2019</option>
                    <option value="Office 2021" @selected($oldMsVersion === 'Office 2021')>Office 2021</option>
                    <option value="Microsoft 365" @selected($oldMsVersion === 'Microsoft 365')>Microsoft 365</option>
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
                    <option value="Cracked" @selected($oldMsLicense === 'Cracked')>Cracked</option>
                    <option value="OEM Licensed" @selected($oldMsLicense === 'OEM Licensed')>OEM Licensed</option>
                </select>
                @error('ms_office_license')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            @include('admin.devices._photo-input', [
                'photoInputId' => 'edit_page_equipment_photo',
                'existingPhotoPath' => $device->photo_path,
            ])
        </div>

        <div class="flex gap-2">
            <button class="px-4 py-2 rounded bg-blue-600 text-white">Update</button>
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
                if (osVersionSel.value) { show(osLicenseWrap); } else { hide(osLicenseWrap); }
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

        // Run on page load to restore state
        updateFields();
    })();
</script>
@endpush
@endsection
