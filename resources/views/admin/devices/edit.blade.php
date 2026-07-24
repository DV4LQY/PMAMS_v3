@extends('admin.layouts.app')

@section('title', 'Edit Equipment')
@section('page_title', 'Edit Equipment')

@section('breadcrumb')
    <a class="text-blue-700 hover:underline" href="{{ route('admin.devices.index') }}">Equipment</a>
    <span class="mx-2">/</span>
    <span>Edit Equipment</span>
@endsection

@section('content')
@php
    $requestedReturnTo = trim((string) request()->query('return_to', ''));
    $safeReturnTo = $requestedReturnTo !== ''
        && str_starts_with($requestedReturnTo, '/')
        && ! str_starts_with($requestedReturnTo, '//')
        ? $requestedReturnTo
        : null;
@endphp
<div class="bg-white rounded shadow-sm p-6 max-w-4xl">
    <div
        x-data="{
            addTypeId: @js(old('device_type_id', $device->device_type_id)),
            addCondition: @js(strtolower((string) old('condition', $device->condition ?? 'serviceable'))),
            addStatus: @js(strtolower((string) old('status', $device->status ?? 'available'))),
            addOsVersion: @js(old('os_version', $device->os_version ?? '')),
            addMsVersion: @js(old('ms_office_version', $device->ms_office_version ?? '')),
            typeNames: @js($types->pluck('name', 'id')),
            getTypeName(typeId) {
                return String(this.typeNames?.[String(typeId)] ?? '').trim().toLowerCase();
            },
            isComputerType(typeId) {
                const name = this.getTypeName(typeId ?? this.addTypeId);
                return name === 'desktop' || name === 'laptop';
            },
            isDesktopType(typeId) {
                return this.getTypeName(typeId ?? this.addTypeId) === 'desktop';
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
            }
        }"
    >
        <form method="POST" action="{{ route('admin.devices.update', $device) }}" enctype="multipart/form-data" class="space-y-6" x-on:submit="cleanUnitPrices($event.target)">
            @csrf
            @method('PUT')
            <input type="hidden" name="device_id" value="{{ $device->id }}">

            @include('admin.devices._add-equipment-fields', [
                'lockEquipmentType' => true,
            ])

            @if($safeReturnTo)
                <input type="hidden" name="return_to" value="{{ $safeReturnTo }}">
            @endif

            <div class="flex gap-2">
                <button class="rounded bg-blue-600 px-4 py-2 text-white">Save Changes</button>
                <a href="{{ $safeReturnTo ?: route('admin.devices.index') }}" wire:navigate class="rounded bg-gray-100 px-4 py-2">Cancel</a>
            </div>
        </form>
    </div>

    @if(false)
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
                <label class="text-sm font-medium">Property Number <span class="font-normal text-gray-500">(optional when linked)</span></label>
                <input name="property_number"
                       value="{{ old('property_number', $device->property_number) }}"
                       class="mt-1 w-full border rounded px-3 py-2"
                       maxlength="50"
                       pattern="[A-Za-z0-9][A-Za-z0-9\-\/]*"
                       title="Letters, numbers, hyphens, and slashes only">
                @error('property_number')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
                <div class="text-xs text-gray-500 mt-1">Leave blank when using a parent property number below.</div>
            </div>

            @include('admin.devices._part-property-number-field', [
                'value' => old('part_of_property_number', $device->part_of_property_number),
            ])

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
                       maxlength="100"
                       pattern="[0-9A-Fa-f]{2}(:[0-9A-Fa-f]{2}){5}(;\s*[0-9A-Fa-f]{2}(:[0-9A-Fa-f]{2}){5})*"
                       title="Enter one or more MAC addresses separated by semicolons"
                       placeholder="90:DE:80:08:8D:5C; 00:DE:80:08:8D:5C">
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

            {{-- Status is managed by issuance/return actions and is not edited here. --}}
            <input type="hidden" name="status" value="{{ old('status', $device->status ?? 'available') }}">

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
                    <option value="Windows Server" @selected($oldOsVersion === 'Windows Server')>Windows Server</option>
                    <option value="Linux" @selected($oldOsVersion === 'Linux')>Linux</option>
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
                    <option value="Open Source" @selected($oldOsLicense === 'Open Source')>Open Source</option>
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
    @endif
</div>

@endsection
