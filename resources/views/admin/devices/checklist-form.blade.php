@extends('admin.layouts.app')

@section('title', 'Maintenance Checklist')
@section('page_title', 'Maintenance Checklist')
@section('breadcrumbs')
    <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-600">Dashboard</a>
    <span>/</span>
    <a href="{{ route('admin.devices.index') }}" class="hover:text-blue-600">Equipment</a>
    <span>/</span>
    <a href="{{ route('admin.devices.show', $device) }}" class="hover:text-blue-600">Equipment Details</a>
    <span>/</span>
    <span class="font-medium text-gray-800 dark:text-gray-200">Maintenance Checklist</span>
@endsection

@section('content')
@php
    $assignment = $device->currentAssignment;
    $staff = $assignment?->staff;
    $office = $staff?->office;
    $college = $office?->college;
    $linkedByType = collect($linkedPeripherals ?? [])->groupBy(fn ($peripheral) => strtolower($peripheral->type?->name ?? ''));
    $linkablePeripheralOptions = collect($linkablePeripherals ?? [])->map(fn ($peripheral) => [
        'id' => $peripheral->id,
        'type' => $peripheral->type?->name ?? 'Peripheral',
        'property_number' => $peripheral->property_number,
        'serial_number' => $peripheral->serial_number,
        'computer_name' => $peripheral->computer_name,
        'parent_property_number' => $peripheral->part_of_property_number,
    ])->values()->all();
    $openLink = request()->boolean('open_link');
    $requestedPeripheralType = request()->query('peripheral_type', '');
    $requestedAllowLinked = request()->boolean('allow_linked');
    $checklistPath = parse_url(route('admin.devices.checklist.form', $device), PHP_URL_PATH);
    $checklistReturnPath = parse_url(route('admin.devices.checklist.form', $device), PHP_URL_PATH)
        . '?open_link=1&peripheral_type=' . rawurlencode($requestedPeripheralType ?: 'monitor')
        . '&allow_linked=' . ($requestedAllowLinked ? '1' : '0');
@endphp

<div class="space-y-6">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                    Preventive Maintenance Checklist
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Choose OK or Not OK for each hardware item. UPS/AVR and Printer may also be marked Not Available.
                </p>
            </div>

            <a
                href="{{ route('admin.devices.show', $device) }}"
                class="inline-flex rounded-xl bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
            >
                Back to Equipment
            </a>
        </div>
    </div>

    <form
        id="maintenance-checklist-form"
        method="POST"
        action="{{ route('admin.devices.checklist.save', $device) }}"
        enctype="multipart/form-data"
        target="_self"
        x-data="{
            remarks: @js(old('remarks', '')),
            remarksEdited: false,
            correctiveAction: @js(old('corrective_action', '')),
            checklistReady: false,
            checklistVersion: 0,
            checklistRowCount: {{ count($checklistItems) + count($softwareItems) }},
            duplicateReasonOpen: {{ session('duplicate_warning') ? 'true' : 'false' }},
            verificationReason: @js(old('verification_reason', '')),
            notOkRows: @js(collect($checklistItems)->mapWithKeys(fn ($item, $key) => [$key => old("hardware.$key") === 'Not OK'])->all()),
            conditionRows: @js(collect($checklistItems)->mapWithKeys(fn ($item, $key) => [
                $key => old("condition.$key", old("hardware.$key") === 'Not OK' ? 'unserviceable' : ''),
            ])->all()),
            statusRows: @js(collect($checklistItems)->mapWithKeys(fn ($item, $key) => [$key => old("disposition.$key", '')])->all()),
            checklistStateKey() {
                return `pmams-checklist-state:${window.location.pathname}`;
            },
            restoreChecklistState() {
                let stored = null;
                try {
                    stored = JSON.parse(window.sessionStorage.getItem(this.checklistStateKey()) || 'null');
                } catch (error) {
                    stored = null;
                }

                if (!stored?.fields) return;

                const controls = Array.from(this.$root.elements || []);
                stored.fields.forEach((saved) => {
                    controls
                        .filter((control) => control.name === saved.name)
                        .forEach((control) => {
                            if (control.type === 'radio' || control.type === 'checkbox') {
                                control.checked = Boolean(saved.checked);
                            } else if (typeof saved.value === 'string') {
                                control.value = saved.value;
                            }
                        });
                });

                // Replay the same events used by normal user interaction so
                // Alpine rebuilds notOkRows, conditionRows, and statusRows.
                controls.forEach((control) => {
                    if (!control.name || control.name === '_token' || control.name === '_method') return;
                    if (control.type === 'radio' || control.type === 'checkbox') {
                        if (control.checked) control.dispatchEvent(new Event('change', { bubbles: true }));
                    } else if (['date', 'text', 'textarea', 'search'].includes(control.type || control.tagName?.toLowerCase())) {
                        control.dispatchEvent(new Event('input', { bubbles: true }));
                        control.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });

                try {
                    window.sessionStorage.removeItem(this.checklistStateKey());
                } catch (error) {
                    // Storage can be unavailable in private browsing; the
                    // checklist remains usable without state restoration.
                }
            },
            allChecklistAnswered() {
                const selectedRows = new Set(
                    Array.from(this.$root.querySelectorAll('input[type=radio]:checked'))
                        .map((input) => input.name)
                );

                return selectedRows.size === this.checklistRowCount;
            },
            refreshChecklistState() {
                this.checklistVersion++;
                this.checklistReady = this.allChecklistAnswered();
            },
            setNotOkRow(key, enabled) {
                this.notOkRows[key] = enabled;

                if (!enabled) {
                    this.conditionRows[key] = '';
                    this.statusRows[key] = '';
                } else if (!this.conditionRows[key]) {
                    // Not OK rows start as unserviceable, while still allowing
                    // the reviewer to change the condition immediately.
                    this.conditionRows[key] = 'unserviceable';
                }

                this.$root.querySelectorAll('input').forEach((input) => {
                    if (input.name === `disposition[${key}]` || input.name === `condition[${key}]`) {
                        input.disabled = !enabled;
                        input.checked = enabled
                            ? input.value === (input.name.startsWith('condition[')
                                ? this.conditionRows[key]
                                : this.statusRows[key])
                            : false;
                    }
                });

                this.refreshChecklistState();
            },
            setNotAvailableRow(key) {
                // Not Available is a completed hardware response, but it is
                // intentionally outside the condition/disposition workflow.
                // Clear and disable those controls immediately so a previous
                // Not OK selection cannot keep a stale Repair/Condemn value
                // or make the linked property appear required.
                this.notOkRows[key] = false;
                this.conditionRows[key] = '';
                this.statusRows[key] = '';

                this.$root.querySelectorAll('input').forEach((input) => {
                    if (input.name === `disposition[${key}]` || input.name === `condition[${key}]`) {
                        input.disabled = true;
                        input.checked = false;
                    }
                });

                this.applyChecklistDefaults();
                this.refreshChecklistState();
            },
            isNotOkSelected(key) {
                return Array.from(this.$root.querySelectorAll('input'))
                    .some((input) => input.name === `hardware[${key}]` && input.value === 'Not OK' && input.checked);
            },
            isUnserviceableSelected(key) {
                return this.conditionRows[key] === 'unserviceable';
            },
            syncCondition(key, event) {
                const selectedValue = event?.target?.checked
                    ? event.target.value
                    : (this.$root.querySelector(`input[name='condition[${key}]']:checked`)?.value || '');
                this.conditionRows[key] = selectedValue;
                this.$root.querySelectorAll(`input[name='condition[${key}]']`).forEach((input) => {
                    input.checked = input.value === selectedValue;
                });

                // Repair/Not in Use is only valid for an explicitly
                // unserviceable row. Remove stale status choices otherwise.
                if (selectedValue !== 'unserviceable') {
                    this.statusRows[key] = '';
                    this.$root.querySelectorAll(`input[name='disposition[${key}]']`).forEach((input) => {
                        input.checked = false;
                    });
                }

                this.refreshChecklistState();
            },
            syncStatus(key, event) {
                const selectedValue = event?.target?.checked ? event.target.value : '';
                this.statusRows[key] = selectedValue;
                this.$root.querySelectorAll(`input[name='disposition[${key}]']`).forEach((input) => {
                    input.checked = input.value === selectedValue;
                });
                this.refreshChecklistState();
            },
            clearDisposition(key) {
                if (this.isNotOkSelected(key)) return;

                this.conditionRows[key] = '';
                this.statusRows[key] = '';

                this.$root.querySelectorAll('input').forEach((input) => {
                    if (input.name === `disposition[${key}]` || input.name === `condition[${key}]`) input.checked = false;
                });
            },
            formatSectionList(sections) {
                if (sections.length === 0) return '';
                if (sections.length === 1) return sections[0];
                if (sections.length === 2) return `${sections[0]} and ${sections[1]}`;
                return `${sections.slice(0, -1).join(', ')}, and ${sections[sections.length - 1]}`;
            },
            generatedRemarks() {
                const defectiveSections = Array.from(this.$root.querySelectorAll('input'))
                    .filter((input) => input.name.startsWith('hardware[') && input.value === 'Not OK' && input.checked)
                    .map((input) => input.dataset.section)
                    .filter(Boolean);
                if (defectiveSections.length) {
                    return `Defective ${this.formatSectionList(defectiveSections)}`;
                }

                const form = this.$root;
                const avrUnavailable = form.elements['hardware[avr_ups_power_recovery]']?.value === 'Not Available';
                const printerUnavailable = form.elements['hardware[printer_printout]']?.value === 'Not Available';
                if (avrUnavailable) return 'not available UPS/AVR';
                if (printerUnavailable) return '';
                return 'Serviceable';
            },
            applyChecklistDefaults() {
                const currentRemarks = this.remarks.trim();
                const isGeneratedRemark = currentRemarks === ''
                    || currentRemarks === 'Serviceable'
                    || currentRemarks === 'not available UPS/AVR'
                    || currentRemarks.startsWith('Defective ');

                if (!this.remarksEdited && isGeneratedRemark) {
                    this.remarks = this.generatedRemarks();
                }

                const form = this.$root;
                const avrUnavailable = form.elements['hardware[avr_ups_power_recovery]']?.value === 'Not Available';
                const printerUnavailable = form.elements['hardware[printer_printout]']?.value === 'Not Available';

                if (avrUnavailable && !this.remarks.trim()) {
                    this.remarks = 'not available UPS/AVR';
                } else if (!avrUnavailable && this.remarks.trim() === 'not available UPS/AVR') {
                    this.remarks = '';
                }

                if ((avrUnavailable || printerUnavailable) && !this.correctiveAction.trim()) {
                    this.correctiveAction = 'office is advised to procure the equipment';
                } else if (!avrUnavailable && !printerUnavailable && this.correctiveAction.trim() === 'office is advised to procure the equipment') {
                    this.correctiveAction = '';
                }
            }
        }"
        x-init="$nextTick(() => { $data.restoreChecklistState(); $data.applyChecklistDefaults(); $data.refreshChecklistState() })"
        class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
    >
        @csrf

        @if($errors->any())
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-900/30 dark:text-red-400">
                <div class="font-semibold">Please check the checklist form.</div>
                <ul class="mt-1 list-inside list-disc">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(session('duplicate_warning'))
            <input type="hidden" name="confirm_duplicate" value="1">
            <input type="hidden" name="verification_reason" x-model="verificationReason">
        @endif

        <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Date Checked</label>
                <input
                    type="date"
                    name="date_checked"
                    value="{{ old('date_checked', $defaultDate ?? now()->toDateString()) }}"
                    max="{{ now()->toDateString() }}"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                >
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Office / Unit</label>
                <input
                    type="text"
                    value="{{ $office?->name ?? 'Unassigned' }}"
                    readonly
                    class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 dark:border-gray-600 dark:bg-gray-900/40 dark:text-gray-300"
                >
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">College</label>
                <input
                    type="text"
                    value="{{ $college?->name ?? '-' }}"
                    readonly
                    class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 dark:border-gray-600 dark:bg-gray-900/40 dark:text-gray-300"
                >
            </div>
        </div>

        <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900/40">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Equipment Type</div>
                    <div class="font-semibold text-gray-900 dark:text-white">{{ $device->type?->name ?? '-' }}</div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">System Unit Property Number</div>
                    <div class="font-semibold text-gray-900 dark:text-white">{{ $device->property_number }}</div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Serial Number</div>
                    <div class="font-semibold text-gray-900 dark:text-white">{{ $device->serial_number ?: '-' }}</div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Checked By</div>
                    <div class="font-semibold text-gray-900 dark:text-white">{{ auth()->user()->name ?? '-' }}</div>
                </div>
            </div>


        </div>

        <div class="mt-6 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left dark:bg-gray-900/40">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Section</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Checklist Item</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">OK</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Not OK</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Not Available</th>
                        <th class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Condition</th>
                        <th class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Status</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                    @foreach($checklistItems as $key => $item)
                        @php
                            $sectionName = $item['group'] ?? '-';
                            $sectionKey = strtolower($sectionName);
                            $sectionDevices = match ($sectionKey) {
                                'system unit' => collect([$device]),
                                'monitor' => $linkedByType->get('monitor', collect()),
                                'avr/ups' => $linkedByType->get('avr', collect())
                                    ->merge($linkedByType->get('ups', collect()))
                                    ->values(),
                                'printer' => $linkedByType->get('printer', collect()),
                                default => collect(),
                            };
                            $sectionProperties = match ($sectionKey) {
                                'system unit' => $sectionDevices->pluck('property_number')->all(),
                                'monitor', 'avr/ups', 'printer' => $sectionDevices->pluck('property_number')->all(),
                                default => [],
                            };
                        @endphp
                        <tr>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                <div>{{ $sectionName }}</div>
                                @if($sectionProperties)
                                    @if(auth()->user()?->isAdmin() && in_array($sectionKey, ['monitor', 'avr/ups', 'printer'], true))
                                        <button
                                            type="button"
                                            title="Change linked equipment"
                                            class="mt-1 inline-flex cursor-pointer items-center gap-1 text-xs font-medium text-indigo-600 underline decoration-dotted underline-offset-2 hover:text-indigo-800 dark:text-indigo-300 dark:hover:text-indigo-200"
                                            x-on:click.prevent="$dispatch('open-checklist-link', { peripheralType: @js($sectionKey), allowLinked: true })"
                                        >
                                            Property #: {{ implode(', ', $sectionProperties) }}
                                            <span aria-hidden="true">&#128279;</span>
                                        </button>
                                        @foreach($sectionDevices as $sectionDevice)
                                            <a
                                                href="{{ route('admin.devices.edit', ['device' => $sectionDevice, 'return_to' => $checklistPath]) }}"
                                                wire:navigate
                                                title="Edit specs for {{ $sectionDevice->property_number }}"
                                                aria-label="Edit specs for {{ $sectionDevice->property_number }}"
                                                class="mt-1 inline-flex items-center justify-center rounded-md p-1 text-gray-500 hover:bg-gray-200 hover:text-blue-600 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-blue-300"
                                            >
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path d="M12 20h9" />
                                                    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z" />
                                                </svg>
                                                <span class="sr-only">Edit Specs</span>
                                            </a>
                                            <button
                                                type="button"
                                                class="mt-1 inline-flex cursor-pointer items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium text-red-600 underline decoration-dotted underline-offset-2 hover:text-red-800 dark:text-red-300 dark:hover:text-red-200"
                                                data-unlink-url="{{ route('admin.devices.unlinkParent', $sectionDevice) }}"
                                                data-unlink-label="{{ $sectionDevice->property_number }}"
                                                onclick="if (!window.confirm('Unlink this peripheral (' + this.dataset.unlinkLabel + ') from the system unit?')) return; const form = document.createElement('form'); form.method = 'POST'; form.action = this.dataset.unlinkUrl; const token = document.querySelector('#maintenance-checklist-form input[name=_token]')?.value || ''; const add = (name, value) => { const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; form.appendChild(input); }; add('_token', token); add('_method', 'PATCH'); document.body.appendChild(form); form.submit();"
                                            >
                                                Unlink {{ $sectionDevice->property_number }}
                                            </button>
                                        @endforeach
                                    @else
                                        <div class="mt-1 text-xs text-indigo-600 dark:text-indigo-300">
                                            Property #: {{ implode(', ', $sectionProperties) }}
                                        </div>
                                    @endif
                                @elseif(in_array($sectionKey, ['monitor', 'avr/ups', 'printer'], true))
                                    @if(auth()->user()?->isAdmin())
                                        <button
                                            type="button"
                                            title="Link equipment"
                                            class="mt-1 inline-flex cursor-pointer items-center gap-1 text-xs font-medium text-amber-600 underline decoration-dotted underline-offset-2 hover:text-amber-800 dark:text-amber-300 dark:hover:text-amber-200"
                                            x-on:click.prevent="$dispatch('open-checklist-link', { peripheralType: @js($sectionKey), allowLinked: true })"
                                        >
                                            Property #: Not linked
                                            <span aria-hidden="true" title="Link equipment">&#128279;</span>
                                        </button>
                                    @else
                                        <div class="mt-1 text-xs text-amber-600 dark:text-amber-300">Property #: Not linked</div>
                                    @endif
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ $item['label'] ?? '-' }}</td>

                            <td class="px-4 py-3 text-center">
                                <label class="inline-flex cursor-pointer items-center justify-center">
                                    <input
                                        type="radio"
                                        name="hardware[{{ $key }}]"
                                        value="OK"
                                        class="peer sr-only"
                                        @if($loop->first) required @endif
                                        x-on:change="setNotOkRow('{{ $key }}', $event.target.value === 'Not OK'); clearDisposition('{{ $key }}'); applyChecklistDefaults(); refreshChecklistState()"
                                        @checked(old("hardware.$key") === 'OK')
                                    >
                                    <span class="flex h-8 w-8 items-center justify-center rounded border-2 border-gray-400 text-lg font-bold text-transparent dark:border-gray-500 peer-checked:border-green-600 peer-checked:bg-green-50 peer-checked:text-green-700 dark:peer-checked:bg-green-900/30 dark:peer-checked:text-green-400">
                                        ✓
                                    </span>
                                </label>
                            </td>

                            <td class="px-4 py-3 text-center">
                                <label class="inline-flex cursor-pointer items-center justify-center">
                                    <input
                                        type="radio"
                                        name="hardware[{{ $key }}]"
                                        value="Not OK"
                                        data-section="{{ $item['group'] ?? '-' }}"
                                        class="peer sr-only"
                                        x-on:change="setNotOkRow('{{ $key }}', $event.target.value === 'Not OK'); clearDisposition('{{ $key }}'); applyChecklistDefaults(); refreshChecklistState()"
                                        @checked(old("hardware.$key") === 'Not OK')
                                    >
                                    <span class="flex h-8 w-8 items-center justify-center rounded border-2 border-gray-400 text-lg font-bold text-transparent dark:border-gray-500 peer-checked:border-red-600 peer-checked:bg-red-50 peer-checked:text-red-700 dark:peer-checked:bg-red-900/30 dark:peer-checked:text-red-400">
                                        ✓
                                    </span>
                                </label>
                            </td>

                            <td class="px-4 py-3 text-center">
                                @if($item['not_available'] ?? false)
                                    <label class="inline-flex cursor-pointer items-center justify-center">
                                        <input
                                            type="radio"
                                            name="hardware[{{ $key }}]"
                                            value="Not Available"
                                            class="peer sr-only"
                                            x-on:change="setNotAvailableRow('{{ $key }}')"
                                            @checked(old("hardware.$key") === 'Not Available')
                                        >
                                       <span class="flex h-8 w-8 items-center justify-center rounded border-2 border-gray-400 text-lg font-bold text-transparent dark:border-gray-500 peer-checked:border-gray-700 peer-checked:bg-gray-700 peer-checked:text-white dark:peer-checked:border-gray-400 dark:peer-checked:bg-gray-500">
    N/A
</span>
                                    </label>
                                @else
                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                @endif
                            </td>

                            <td class="px-3 py-3 text-center">
                                @if(!in_array($item['group'] ?? '', ['Keyboard', 'Mouse'], true))
                                    <div
                                        class="flex flex-col items-start justify-center gap-1 text-xs text-gray-600 dark:text-gray-300"
                                        x-bind:class="{ 'opacity-50': !notOkRows['{{ $key }}'] }"
                                    >
                                        <span x-show="!notOkRows['{{ $key }}']" x-cloak class="font-semibold text-emerald-600 dark:text-emerald-400">Serviceable</span>
                                        <label x-show="notOkRows['{{ $key }}']" x-cloak class="inline-flex cursor-pointer items-center gap-1">
                                            <input
                                                type="checkbox"
                                                name="condition[{{ $key }}]"
                                                value="unserviceable"
                                                class="h-3.5 w-3.5 accent-red-600"
                                                x-bind:disabled="!notOkRows['{{ $key }}']"
                                                x-on:change="syncCondition('{{ $key }}', $event)"
                                                @checked(old("condition.$key", old("hardware.$key") === 'Not OK' ? 'unserviceable' : null) === 'unserviceable')
                                            >
                                            <span>Unserviceable</span>
                                        </label>
                                        <label x-show="notOkRows['{{ $key }}']" x-cloak class="inline-flex cursor-pointer items-center gap-1">
                                            <input
                                                type="checkbox"
                                                name="condition[{{ $key }}]"
                                                value="condemned"
                                                class="h-3.5 w-3.5 accent-red-700"
                                                x-bind:disabled="!notOkRows['{{ $key }}']"
                                                x-on:change="syncCondition('{{ $key }}', $event)"
                                                @checked(old("condition.$key") === 'condemned')
                                            >
                                            <span>Condemned</span>
                                        </label>
                                    </div>
                                @else
                                    <span class="text-gray-300 dark:text-gray-600">&mdash;</span>
                                @endif
                            </td>

                            <td class="px-3 py-3 text-center">
                                @if(!in_array($item['group'] ?? '', ['Keyboard', 'Mouse'], true))
                                    <div
                                        x-show="isUnserviceableSelected('{{ $key }}')"
                                        x-cloak
                                        class="flex flex-col items-start justify-center gap-1 text-xs text-gray-600 dark:text-gray-300"
                                        x-bind:class="{ 'opacity-50': !isUnserviceableSelected('{{ $key }}') }"
                                    >
                                    <label class="inline-flex cursor-pointer items-center gap-1">
                                        <input
                                            type="checkbox"
                                            name="disposition[{{ $key }}]"
                                            value="repair"
                                            class="h-3.5 w-3.5 accent-amber-500"
                                            x-bind:disabled="!isUnserviceableSelected('{{ $key }}')"
                                            x-on:change="syncStatus('{{ $key }}', $event)"
                                            @checked(old("disposition.$key") === 'repair')
                                        >
                                        <span>Repair</span>
                                    </label>
                                    <label class="inline-flex cursor-pointer items-center gap-1">
                                            <input
                                                type="checkbox"
                                                name="disposition[{{ $key }}]"
                                                value="not_in_use"
                                                class="h-3.5 w-3.5 accent-slate-500"
                                                x-bind:disabled="!isUnserviceableSelected('{{ $key }}')"
                                                x-on:change="syncStatus('{{ $key }}', $event)"
                                                @checked(old("disposition.$key") === 'not_in_use')
                                            >
                                            <span>Not in Use</span>
                                        </label>
                                    </div>
                                @else
                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach

                    @foreach($softwareItems as $key => $label)
                        <tr>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">Software</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ $label }}</td>

                            <td class="px-4 py-3 text-center">
                                <label class="inline-flex cursor-pointer items-center justify-center">
                                    <input
                                        type="radio"
                                        name="software[{{ $key }}]"
                                        value="check"
                                        class="peer sr-only"
                                        @if($loop->first) required @endif
                                        x-on:change="refreshChecklistState()"
                                        @checked(old("software.$key") === 'check')
                                    >
                                    <span class="flex h-8 w-8 items-center justify-center rounded border-2 border-gray-400 text-lg font-bold text-transparent dark:border-gray-500 peer-checked:border-green-600 peer-checked:bg-green-50 peer-checked:text-green-700 dark:peer-checked:bg-green-900/30 dark:peer-checked:text-green-400">
                                        ✓
                                    </span>
                                </label>
                            </td>

                            <td class="px-4 py-3 text-center">
                                <label class="inline-flex cursor-pointer items-center justify-center">
                                    <input
                                        type="radio"
                                        name="software[{{ $key }}]"
                                        value="dash"
                                        class="peer sr-only"
                                        x-on:change="refreshChecklistState()"
                                        @checked(old("software.$key") === 'dash')
                                    >
                                    <span class="flex h-8 w-8 items-center justify-center rounded border-2 border-gray-400 text-lg font-bold text-transparent dark:border-gray-500 peer-checked:border-gray-600 peer-checked:bg-gray-50 peer-checked:text-gray-700 dark:peer-checked:bg-gray-700 dark:peer-checked:text-gray-300">
                                        -
                                    </span>
                                </label>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-300 dark:text-gray-600">—</td>
                            <td class="px-3 py-3 text-center text-gray-300 dark:text-gray-600">—</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-5 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Remarks</label>
                <textarea
                    name="remarks"
                    x-model="remarks"
                    x-on:input="remarksEdited = true"
                    rows="4"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    placeholder="Optional remarks"
                ></textarea>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Corrective Action</label>
                <textarea
                    name="corrective_action"
                    x-model="correctiveAction"
                    rows="4"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    placeholder="Optional corrective action"
                ></textarea>
            </div>
        </div>

        <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
            <label for="maintenance-photo" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Maintenance photo <span class="font-normal text-gray-500">(optional, maximum 10 MB)</span></label>
            <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Take a rear-camera photo or upload an existing image. The photo is saved in the Maintenance Photo Gallery and linked to this checklist record.</p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,18rem)_1fr]">
                <div class="relative aspect-[4/3] overflow-hidden rounded-lg bg-gray-200 dark:bg-gray-700">
                    <video id="checklist-camera-preview" class="hidden h-full w-full object-cover" autoplay playsinline muted></video>
                    <img id="checklist-photo-preview" class="hidden h-full w-full object-contain" alt="Maintenance photo preview">
                    <div id="checklist-photo-placeholder" class="flex h-full items-center justify-center px-3 text-center text-xs text-gray-500 dark:text-gray-400">No photo selected</div>
                    <canvas id="checklist-camera-canvas" class="hidden"></canvas>
                </div>
                <div class="flex flex-wrap content-start gap-2">
                    <button id="checklist-camera-start" type="button" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">Take photo</button>
                    <button id="checklist-camera-capture" type="button" class="hidden rounded-lg bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700">Capture</button>
                    <button id="checklist-camera-stop" type="button" class="hidden rounded-lg bg-gray-600 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-500">Stop camera</button>
                    <label for="maintenance-photo" class="cursor-pointer rounded-lg bg-gray-600 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-500">Upload photo</label>
                    <input id="maintenance-photo" name="maintenance_photo" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" class="sr-only">
                    <div id="checklist-photo-name" class="w-full text-xs text-gray-500 dark:text-gray-400">No photo selected.</div>
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <a
                href="{{ route('admin.devices.show', $device) }}"
                class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
            >
                Cancel
            </a>

            @if(session('duplicate_warning'))
                <button
                    type="button"
                    x-show="checklistReady"
                    x-bind:disabled="!checklistReady"
                    x-on:click="duplicateReasonOpen = true; $nextTick(() => $refs.verificationReason?.focus())"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                >
                    Verify Checklist
                </button>
            @else
                <button
                    type="submit"
                    x-show="checklistReady"
                    x-bind:disabled="!checklistReady"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                >
                    Save Checklist
                </button>
            @endif
        </div>

        @if(session('duplicate_warning'))
            <div
                x-show="duplicateReasonOpen"
                x-cloak
                x-on:keydown.escape.window="duplicateReasonOpen = false"
                class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 px-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="duplicate-checklist-title"
            >
                <div
                    x-on:click.outside="duplicateReasonOpen = false"
                    class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-800"
                >
                    <h2 id="duplicate-checklist-title" class="text-lg font-semibold text-gray-900 dark:text-white">
                        Verify Duplicate Checklist
                    </h2>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        The equipment was already checked on date
                        <strong>{{ session('duplicate_warning.date') }}</strong>.
                        Do you want to verify the checklist? This is within the configured {{ session('duplicate_warning.window_months', 3) }}-month verification window.
                    </p>

                    <div class="mt-5">
                        
                        <textarea
                            x-ref="verificationReason"
                            x-model="verificationReason"
                            rows="4"
                            required
                            maxlength="1000"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            placeholder="Explain why this checklist is being verified again"
                        ></textarea>
                        @error('verification_reason')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Used for activity log only.</p>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button
                            type="button"
                            x-on:click="duplicateReasonOpen = false"
                            class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                        >
                            Verify &amp; Save Checklist
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </form>

    @if(auth()->user()?->isAdmin())
        <div
            x-data="{
                linkOpen: @json($openLink),
                peripheralType: @js($requestedPeripheralType),
                allowLinked: @json($requestedAllowLinked),
                peripheralQuery: '',
                candidates: [],
                selectedPeripheral: null,
                parentPropertyNumber: @js($device->property_number),
                linkBaseUrl: `${window.adminBasePath || window.location.pathname.split('/admin')[0]}/admin/devices`,
                editReturnTo: @js($checklistPath),
                equipmentAddUrl: @js(route('admin.devices.index', ['open_add' => 1])),
                returnTo: @js($checklistReturnPath),
                linkablePeripherals: @js($linkablePeripheralOptions),
                linkSubmitting: false,
                linkError: '',
                checklistStateKey() {
                    return `pmams-checklist-state:${window.location.pathname}`;
                },
                rememberChecklistState() {
                    const form = document.getElementById('maintenance-checklist-form');
                    if (!form) return;

                    const fields = Array.from(form.elements || [])
                        .filter((control) => control.name && control.type !== 'file' && !['_token', '_method'].includes(control.name))
                        .map((control) => ({
                            name: control.name,
                            type: control.type || control.tagName?.toLowerCase(),
                            value: control.value ?? '',
                            checked: control.type === 'radio' || control.type === 'checkbox' ? control.checked : undefined,
                        }));

                    try {
                        window.sessionStorage.setItem(this.checklistStateKey(), JSON.stringify({ fields }));
                    } catch (error) {
                        // State restoration is best effort only.
                    }
                },
                async submitLink(event) {
                    if (!this.selectedPeripheral || this.linkSubmitting) return;

                    this.linkSubmitting = true;
                    this.linkError = '';
                    this.rememberChecklistState();

                    try {
                        const response = await fetch(event.target.action, {
                            method: 'POST',
                            body: new FormData(event.target),
                            redirect: 'manual',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                Accept: 'text/html',
                            },
                        });

                        const redirected = response.type === 'opaqueredirect'
                            || (response.status >= 300 && response.status < 400);
                        if (!response.ok && !redirected) {
                            throw new Error('The peripheral could not be linked. Please try again.');
                        }

                        const target = new URL(window.location.href);
                        // Linking is complete, so return to the checklist
                        // itself with the modal closed. The saved checklist
                        // snapshot is restored by the form's x-init hook.
                        target.searchParams.delete('open_link');
                        target.searchParams.delete('peripheral_type');
                        target.searchParams.delete('allow_linked');
                        target.searchParams.delete('link_refresh');
                        const path = window.adminLocalNavigatePath
                            ? window.adminLocalNavigatePath(target)
                            : `${target.pathname}${target.search}`;

                        if (window.Livewire?.navigate) {
                            window.Livewire.navigate(path);
                        } else {
                            window.location.assign(path);
                        }
                    } catch (error) {
                        this.linkSubmitting = false;
                        this.linkError = error.message || 'The peripheral could not be linked. Please try again.';
                    }
                },
                openLink(type, allowLinked = false) {
                    this.peripheralType = type;
                    this.allowLinked = allowLinked;
                    this.peripheralQuery = '';
                    this.selectedPeripheral = null;
                    this.candidates = this.linkablePeripherals.filter((peripheral) => {
                        const name = String(peripheral.type || '').toLowerCase();
                        const matchesType = type === 'avr/ups'
                            ? ['avr', 'ups'].includes(name)
                            : name === type;
                        return matchesType && (allowLinked || !peripheral.parent_property_number);
                    });
                    this.linkOpen = true;
                },
                filteredCandidates() {
                    const query = this.peripheralQuery.trim().toLowerCase();
                    if (!query) return this.candidates;

                    return this.candidates.filter((peripheral) => [
                        peripheral.type,
                        peripheral.property_number,
                        peripheral.serial_number,
                        peripheral.computer_name,
                        peripheral.parent_property_number,
                    ].filter(Boolean).join(' ').toLowerCase().includes(query));
                },
                openAddEquipment() {
                    const url = new URL(this.equipmentAddUrl, window.location.origin);
                    const requestedType = this.peripheralType === 'avr/ups'
                        ? 'AVR'
                        : this.peripheralType.charAt(0).toUpperCase() + this.peripheralType.slice(1);
                    url.searchParams.set('add_type', requestedType);
                    url.searchParams.set('add_parent', this.parentPropertyNumber);
                    const returnUrl = new URL(this.returnTo, window.location.origin);
                    returnUrl.searchParams.set('open_link', '1');
                    returnUrl.searchParams.set('peripheral_type', this.peripheralType);
                    returnUrl.searchParams.set('allow_linked', this.allowLinked ? '1' : '0');
                    url.searchParams.set('return_to', returnUrl.pathname + returnUrl.search);
                    this.rememberChecklistState();
                    const path = window.adminLocalNavigatePath
                        ? window.adminLocalNavigatePath(url)
                        : `${url.pathname}${url.search}`;
                    if (window.Livewire?.navigate) {
                        window.Livewire.navigate(path);
                    } else {
                        window.location.assign(url.toString());
                    }
                },
                selectPeripheral(peripheral) {
                    this.linkError = '';
                    this.selectedPeripheral = peripheral;
                }
            }"
            x-init="if (linkOpen && peripheralType) $nextTick(() => openLink(peripheralType, allowLinked))"
            x-on:open-checklist-link.window="openLink($event.detail.peripheralType, $event.detail.allowLinked)"
        >
            <x-modal show="linkOpen" title="Link Peripheral to This System Unit" maxWidth="max-w-xl">
                <form
                    method="POST"
                    x-bind:action="selectedPeripheral ? `${linkBaseUrl}/${selectedPeripheral.id}/link-parent` : '#'"
                    x-on:submit.prevent="submitLink($event)"
                    class="space-y-4"
                >
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="replace_existing" x-bind:value="allowLinked ? 1 : 0">

                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-100">
                        Select the <span class="font-semibold" x-text="peripheralType === 'avr/ups' ? 'AVR or UPS' : peripheralType"></span> to attach or reassign to this system unit.
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Parent property number</label>
                        <input
                            type="text"
                            name="parent_property_number"
                            readonly
                            x-bind:value="parentPropertyNumber"
                            class="mt-1 w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900/40 dark:text-gray-300"
                        >
                    </div>

                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Available peripheral</label>
                            <button
                                type="button"
                                x-on:click="openAddEquipment()"
                                class="inline-flex shrink-0 items-center rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                            >
                                + Add Equipment
                            </button>
                        </div>
                        <input
                            type="search"
                            x-model="peripheralQuery"
                            placeholder="Search property number, serial number, or computer name..."
                            autocomplete="off"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-100 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400"
                        >
                        <div class="mt-2 max-h-56 overflow-y-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                            <template x-if="filteredCandidates().length === 0">
                                <div class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No matching peripheral found. Use + Add Equipment to register one.
                                </div>
                            </template>
                            <template x-for="peripheral in filteredCandidates()" :key="peripheral.id">
                                <div class="flex items-stretch border-b border-gray-100 last:border-b-0 dark:border-gray-700">
                                    <button
                                        type="button"
                                        class="min-w-0 flex-1 px-3 py-2 text-left text-sm hover:bg-amber-50 dark:hover:bg-gray-700"
                                        x-bind:class="selectedPeripheral?.id === peripheral.id ? 'bg-amber-100 dark:bg-amber-900/40' : ''"
                                        x-on:click="selectPeripheral(peripheral)"
                                    >
                                        <span class="font-semibold text-gray-900 dark:text-white" x-text="`${peripheral.type} ${peripheral.property_number || ''}`"></span>
                                        <span class="block truncate text-xs text-gray-500 dark:text-gray-400" x-text="[peripheral.serial_number, peripheral.computer_name, peripheral.parent_property_number ? `Linked to ${peripheral.parent_property_number}` : 'Not linked'].filter(Boolean).join(' / ') || 'No serial or computer name'"></span>
                                    </button>
                                    <a
                                        x-bind:href="`${linkBaseUrl}/${peripheral.id}/edit?return_to=${encodeURIComponent(editReturnTo)}`"
                                        wire:navigate
                                        title="Edit specs"
                                        aria-label="Edit specs"
                                        class="inline-flex shrink-0 items-center justify-center px-3 text-gray-500 hover:bg-gray-100 hover:text-blue-600 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-blue-300"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="M12 20h9" />
                                            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z" />
                                        </svg>
                                    </a>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                            x-on:click="linkOpen = false"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-amber-500 dark:hover:bg-amber-600"
                            x-bind:disabled="!selectedPeripheral || linkSubmitting"
                        >
                            <span x-text="linkSubmitting ? 'Linking…' : 'Link Peripheral'"></span>
                        </button>
                    </div>
                    <p x-show="linkError" x-cloak class="text-sm text-red-600 dark:text-red-400" x-text="linkError"></p>
                </form>
            </x-modal>
        </div>
    @endif
</div>

@push('scripts')
<script>
(function () {
    if (window.__checklistCameraReady) return;
    window.__checklistCameraReady = true;
    let stream = null;
    function initChecklistCamera() {
        const form = document.getElementById('maintenance-photo')?.form;
        const video = document.getElementById('checklist-camera-preview');
        const canvas = document.getElementById('checklist-camera-canvas');
        const image = document.getElementById('checklist-photo-preview');
        const placeholder = document.getElementById('checklist-photo-placeholder');
        const input = document.getElementById('maintenance-photo');
        const start = document.getElementById('checklist-camera-start');
        const capture = document.getElementById('checklist-camera-capture');
        const stop = document.getElementById('checklist-camera-stop');
        const name = document.getElementById('checklist-photo-name');
        if (!form || !video || !input || form.dataset.cameraReady === '1') return;
        form.dataset.cameraReady = '1';
        const stopCamera = () => { if (stream) stream.getTracks().forEach(track => track.stop()); stream = null; window.__checklistCameraStream = null; video.pause?.(); video.srcObject = null; video.classList.add('hidden'); capture.classList.add('hidden'); stop.classList.add('hidden'); start.classList.remove('hidden'); };
        const showFile = (file) => { if (!file) return; image.src = URL.createObjectURL(file); image.classList.remove('hidden'); placeholder.classList.add('hidden'); name.textContent = file.name || 'Captured photo'; };
        input.addEventListener('change', () => showFile(input.files?.[0]));
        start.addEventListener('click', async () => {
            if (!navigator.mediaDevices?.getUserMedia) { input.click(); return; }
            try { stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false }); window.__checklistCameraStream = stream; video.srcObject = stream; await video.play(); video.classList.remove('hidden'); image.classList.add('hidden'); placeholder.classList.add('hidden'); start.classList.add('hidden'); capture.classList.remove('hidden'); stop.classList.remove('hidden'); }
            catch (error) { input.click(); }
        });
        capture.addEventListener('click', () => { if (!stream || !video.videoWidth) return; const size = Math.min(video.videoWidth, video.videoHeight); canvas.width = 1280; canvas.height = 1280; canvas.getContext('2d').drawImage(video, (video.videoWidth-size)/2, (video.videoHeight-size)/2, size, size, 0, 0, 1280, 1280); canvas.toBlob(blob => { if (!blob || blob.size > 10 * 1024 * 1024) return; const file = new File([blob], 'maintenance-photo.jpg', { type: 'image/jpeg' }); const transfer = new DataTransfer(); transfer.items.add(file); input.files = transfer.files; showFile(file); stopCamera(); }, 'image/jpeg', 0.9); });
        stop.addEventListener('click', stopCamera);
    }
    document.addEventListener('DOMContentLoaded', initChecklistCamera);
    document.addEventListener('livewire:navigated', initChecklistCamera);
    document.addEventListener('livewire:navigating', function () {
        if (stream) stream.getTracks().forEach(track => track.stop());
        stream = null;
    });
    initChecklistCamera();
})();
</script>
@endpush
@endsection
