@php($lockEquipmentType = $lockEquipmentType ?? false)

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div>
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Equipment Type</label>
        <select
            name="device_type_id"
            data-equipment-type-select
            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            required
            x-model="addTypeId"
            @disabled($lockEquipmentType)
        >
            @foreach($types as $type)
                <option value="{{ $type->id }}">{{ $type->name }}</option>
            @endforeach
        </select>
        @if($lockEquipmentType)
            <input type="hidden" name="device_type_id" x-bind:value="addTypeId">
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Equipment type is locked for this item.</p>
        @endif
        @error('device_type_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
            Property Number <span class="font-normal text-gray-500">(optional when linked)</span>
        </label>
        <input
            name="property_number"
            value="{{ old('property_number') }}"
            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            maxlength="50"
            pattern="[A-Za-z0-9][A-Za-z0-9\-/]*"
            title="Letters, numbers, hyphens, and slashes only"
            placeholder="e.g. PN-2026-0001"
        >
        @error('property_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave blank when using a parent property number below.</p>
    </div>

    <div x-data="{
            query: '',
            results: [],
            loading: false,
            open: false,
            timer: null,
            abort: null,
            visible: false,
            init() {
                this.refreshVisibility();
            },
            refreshVisibility() {
                const form = this.$refs.partPropertyInput?.closest('form');
                const select = form?.querySelector('[data-equipment-type-select], #device_type_select');
                const name = String(select?.options[select.selectedIndex]?.textContent || '')
                    .trim()
                    .toLowerCase();

                this.visible = ['printer', 'monitor', 'avr', 'ups', 'scanner', 'other'].includes(name);
            },
            searchUrl: '{{ route('admin.devices.lookup.property') }}',
            async search() {
                this.query = this.$refs.partPropertyInput.value.trim();
                if (this.abort) this.abort.abort();
                this.open = true;
                this.loading = true;
                this.abort = new AbortController();

                try {
                    const url = new URL(this.searchUrl, window.location.origin);
                    url.searchParams.set('q', this.query);
                    const form = this.$refs.partPropertyInput.closest('form');
                    const editId = form?.querySelector('[name=device_id]')?.value || '';
                    if (editId) url.searchParams.set('exclude_id', editId);

                    const response = await fetch(url, {
                        headers: { 'Accept': 'application/json' },
                        signal: this.abort.signal,
                    });
                    if (!response.ok) throw new Error('Unable to search property numbers.');
                    const data = await response.json();
                    this.results = Array.isArray(data.results) ? data.results : [];
                } catch (error) {
                    if (error.name !== 'AbortError') this.results = [];
                } finally {
                    this.loading = false;
                }
            },
            queueSearch() {
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.search(), 250);
            },
            select(result) {
                this.$refs.partPropertyInput.value = result.property_number;
                this.$refs.partPropertyInput.dispatchEvent(new Event('input', { bubbles: true }));
                this.$refs.partPropertyInput.dispatchEvent(new Event('change', { bubbles: true }));
                this.query = result.property_number;
                this.open = false;
            }
        }"
        class="relative md:col-span-2"
        x-show="visible"
        x-cloak
        @change.window="if ($event.target.matches('[data-equipment-type-select], #device_type_select')) refreshVisibility()"
    >
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
            Part of Property Number <span class="font-normal text-gray-500">(optional)</span>
        </label>
        <div class="mt-1">
            <input
                x-ref="partPropertyInput"
                name="part_of_property_number"
            value="{{ old('part_of_property_number', $addParentPropertyNumber ?? '') }}"
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                maxlength="50"
                pattern="[A-Za-z0-9][A-Za-z0-9\-/]*"
                title="Letters, numbers, hyphens, and slashes only"
            placeholder="e.g. PN-2026-0001 (link Printer/Monitor/UPS/AVR/Scanner/Other)"
            autocomplete="off"
            :disabled="!visible"
                @input="if ($event.isTrusted) queueSearch()"
                @focus="if ($refs.partPropertyInput.value.trim()) queueSearch()"
            >
        </div>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Use this for Printer, Monitor, AVR, UPS, Scanner, or Other equipment belonging to another property-number group.</p>
        @error('part_of_property_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

        <div
            x-show="open"
            x-cloak
            @click.outside="open = false"
            class="absolute inset-x-0 z-30 mt-1 max-h-56 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800"
        >
            <div x-show="loading" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-300">Searching...</div>
            <div x-show="!loading && results.length === 0" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-300">No matching property number found.</div>
            <template x-for="result in results" :key="result.id">
                <button
                    type="button"
                    class="block w-full px-3 py-2 text-left text-sm hover:bg-indigo-50 dark:hover:bg-gray-700"
                    @click="select(result)"
                >
                    <span class="font-medium text-gray-900 dark:text-white" x-text="result.property_number"></span>
                    <span class="block text-xs text-gray-500 dark:text-gray-300" x-text="result.label"></span>
                </button>
            </template>
        </div>
    </div>

    <div>
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Serial Number</label>
        <input
            name="serial_number"
            value="{{ old('serial_number') }}"
            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            maxlength="100"
            pattern="[A-Za-z0-9\-]*"
            title="Letters, numbers, and hyphens only"
            placeholder="Enter serial number"
        >
        @error('serial_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div x-show="isComputerType(addTypeId)" x-cloak data-equipment-field="computer">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Computer Name</label>
        <input
            name="computer_name"
            value="{{ old('computer_name', old('specs.computer_name')) }}"
            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            maxlength="100"
            placeholder="Enter computer name"
            :disabled="!isComputerType(addTypeId)"
        >
        @error('computer_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Brand</label>
        <input
            name="brand"
            value="{{ old('brand') }}"
            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            maxlength="100"
            pattern="[A-Za-zÑñ0-9][A-Za-zÑñ0-9.\-\s]*"
            title="Letters and numbers only"
            placeholder="Example: ACER, EPSON"
        >
        @error('brand')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Model</label>
        <input
            name="model"
            value="{{ old('model') }}"
            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            maxlength="100"
            pattern="[A-Za-z0-9][A-Za-z0-9.\-\/\s]*"
            title="Letters and numbers only"
            placeholder="Example: L3210, 2199"
        >
        @error('model')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div x-show="isComputerType(addTypeId)" x-cloak data-equipment-field="computer">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">MAC Address</label>
        <input
            name="mac_address"
            value="{{ old('mac_address') }}"
            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            maxlength="100"
            pattern="[0-9A-Fa-f]{2}(:[0-9A-Fa-f]{2}){5}(;\s*[0-9A-Fa-f]{2}(:[0-9A-Fa-f]{2}){5})*"
            title="Enter one or more MAC addresses separated by semicolons"
            placeholder="90:DE:80:08:8D:5C; 00:DE:80:08:8D:5C"
            :disabled="!isComputerType(addTypeId)"
        >
        @error('mac_address')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div x-show="isComputerType(addTypeId)" x-cloak data-equipment-field="computer">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Memory</label>
        <input
            name="specs[memory]"
            value="{{ old('specs.memory') }}"
            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            maxlength="50"
            placeholder="Example: 8GB RAM"
            :disabled="!isComputerType(addTypeId)"
        >
        @error('specs.memory')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div x-show="isComputerType(addTypeId)" x-cloak data-equipment-field="computer">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Storage</label>
        <input
            name="specs[storage]"
            value="{{ old('specs.storage') }}"
            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-700 dark:text-white"
            maxlength="50"
            placeholder="Example: 256GB SSD"
            :disabled="!isComputerType(addTypeId)"
        >
        @error('specs.storage')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div x-show="isDesktopType(addTypeId)" x-cloak data-equipment-field="desktop">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Form Factor</label>
        <select
            name="specs[form_factor]"
            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            :disabled="!isDesktopType(addTypeId)"
        >
            <option value="">-- Select Form Factor --</option>
            <option value="Tower Desktop" @selected(old('specs.form_factor') === 'Tower Desktop')>Tower Desktop</option>
            <option value="Small Form Factor (SFF) Desktop" @selected(old('specs.form_factor') === 'Small Form Factor (SFF) Desktop')>Small Form Factor (SFF) Desktop</option>
            <option value="All-in-One (AIO) Desktop" @selected(old('specs.form_factor') === 'All-in-One (AIO) Desktop')>All-in-One (AIO) Desktop</option>
            <option value="Mini PC" @selected(old('specs.form_factor') === 'Mini PCs')>Mini PC</option>
            
        </select>
        @error('specs.form_factor')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div x-show="isComputerType(addTypeId)" x-cloak data-equipment-field="computer">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">OS Version</label>
        <select
            name="os_version"
            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            x-model="addOsVersion"
            :disabled="!isComputerType(addTypeId)"
        >
            <option value="">-- Select OS --</option>
            <option value="Windows 7">Windows 7</option>
            <option value="Windows 8">Windows 8</option>
            <option value="Windows 10">Windows 10</option>
            <option value="Windows 11">Windows 11</option>
            <option value="Windows Server">Windows Server</option>
            <option value="Linux">Linux</option>
        </select>
        @error('os_version')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div x-show="isComputerType(addTypeId) && addOsVersion" x-cloak data-equipment-field="os-license">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">OS License</label>
        <select
            name="os_license"
            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            :disabled="!isComputerType(addTypeId) || !addOsVersion"
        >
            <option value="">-- Select License --</option>
            <option value="Cracked" @selected(old('os_license') === 'Cracked')>Cracked</option>
            <option value="OEM Licensed" @selected(old('os_license') === 'OEM Licensed')>OEM Licensed</option>
            <option value="Open Source" @selected(old('os_license') === 'Open Source')>Open Source</option>
        </select>
        @error('os_license')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div x-show="isComputerType(addTypeId)" x-cloak data-equipment-field="computer">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">MS Office Version</label>
        <select
            name="ms_office_version"
            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
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
        @error('ms_office_version')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div x-show="isComputerType(addTypeId) && addMsVersion" x-cloak data-equipment-field="office-license">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">MS Office License</label>
        <select
            name="ms_office_license"
            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            :disabled="!isComputerType(addTypeId) || !addMsVersion"
        >
            <option value="">-- Select License --</option>
            <option value="Cracked" @selected(old('ms_office_license') === 'Cracked')>Cracked</option>
            <option value="OEM Licensed" @selected(old('ms_office_license') === 'OEM Licensed')>OEM Licensed</option>
        </select>
        @error('ms_office_license')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Unit Price</label>
        <input
            name="unit_price"
            value="{{ old('unit_price') }}"
            type="text"
            inputmode="decimal"
            placeholder="e.g. 25,000.00"
            class="unit-price-input mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            x-on:input="formatUnitPriceInput($event)"
        >
        @error('unit_price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Date Acquired</label>
        <input
            name="date_acquired"
            value="{{ old('date_acquired') }}"
            type="date"
            max="{{ now()->format('Y-m-d') }}"
            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
        >
        @error('date_acquired')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Condition</label>
        <select name="condition" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            <option value="serviceable" @selected(old('condition', 'serviceable') === 'serviceable')>Serviceable</option>
            <option value="unserviceable" @selected(old('condition') === 'unserviceable')>Unserviceable</option>
            <option value="condemned" @selected(old('condition') === 'condemned')>Condemned</option>
        </select>
        @error('condition')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Last Maintenance Date</label>
        <input
            name="last_maintenance_date"
            value="{{ old('last_maintenance_date') }}"
            type="date"
            max="{{ now()->format('Y-m-d') }}"
            class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
        >
        @error('last_maintenance_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

</div>

<div class="mt-5">
    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Maintenance Remarks</label>
    <textarea
        name="maintenance_remarks"
        rows="3"
        maxlength="1000"
        class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
        placeholder="Example: Initial check, cleaned, inspected"
    >{{ old('maintenance_remarks') }}</textarea>
    @error('maintenance_remarks')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
