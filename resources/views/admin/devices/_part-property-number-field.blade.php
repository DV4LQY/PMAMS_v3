@php($partPropertyValue = $value ?? old('part_of_property_number'))

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

            this.visible = ['printer', 'monitor', 'avr', 'ups', 'other'].includes(name);
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

                const response = await fetch(url, { headers: { 'Accept': 'application/json' }, signal: this.abort.signal });
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
            value="{{ $partPropertyValue }}"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            maxlength="50"
            pattern="[A-Za-z0-9][A-Za-z0-9\-/]*"
            title="Letters, numbers, hyphens, and slashes only"
            placeholder="e.g. PN-2026-0001"
            autocomplete="off"
            :disabled="!visible"
            @input="if ($event.isTrusted) queueSearch()"
        >
    </div>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Link a Printer, Monitor, AVR, UPS, or Other item to the main system-unit property number.</p>
    @error('part_of_property_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

    <div x-show="open" x-cloak @click.outside="open = false" class="absolute inset-x-0 z-30 mt-1 max-h-56 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800">
        <div x-show="loading" class="px-3 py-2 text-sm text-gray-500">Searching...</div>
        <div x-show="!loading && results.length === 0" class="px-3 py-2 text-sm text-gray-500">No matching property number found.</div>
        <template x-for="result in results" :key="result.id">
            <button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-indigo-50 dark:hover:bg-gray-700" @click="select(result)">
                <span class="font-medium text-gray-900 dark:text-white" x-text="result.property_number"></span>
                <span class="block text-xs text-gray-500 dark:text-gray-300" x-text="result.label"></span>
            </button>
        </template>
    </div>
</div>
