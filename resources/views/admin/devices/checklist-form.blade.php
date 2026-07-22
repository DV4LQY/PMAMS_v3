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
        method="POST"
        action="{{ route('admin.devices.checklist.save', $device) }}"
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
                this.$root.querySelectorAll('input').forEach((input) => {
                    if (input.name === `disposition[${key}]`) input.disabled = !enabled;
                });
            },
            isNotOkSelected(key) {
                return Array.from(this.$root.querySelectorAll('input'))
                    .some((input) => input.name === `hardware[${key}]` && input.value === 'Not OK' && input.checked);
            },
            clearDisposition(key) {
                if (this.isNotOkSelected(key)) return;

                this.$root.querySelectorAll('input').forEach((input) => {
                    if (input.name === `disposition[${key}]`) input.checked = false;
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
        x-init="$nextTick(() => { $data.applyChecklistDefaults(); $data.refreshChecklistState() })"
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

            <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">Linked Child Property Numbers</div>
                @if(($linkedPeripherals ?? collect())->isNotEmpty())
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($linkedPeripherals as $peripheral)
                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-3 py-1 text-xs font-medium text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200">
                                {{ $peripheral->type?->name ?? 'Peripheral' }}: {{ $peripheral->property_number }}
                            </span>
                        @endforeach
                    </div>
                @else
                    <div class="mt-1 text-sm text-amber-700 dark:text-amber-300">No linked monitor, AVR/UPS, printer, scanner, or other peripheral.</div>
                @endif
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
                        <th class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Repair / Condemn / Not in Use</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                    @foreach($checklistItems as $key => $item)
                        @php
                            $sectionName = $item['group'] ?? '-';
                            $sectionKey = strtolower($sectionName);
                            $sectionProperties = match ($sectionKey) {
                                'system unit' => [$device->property_number],
                                'monitor' => $linkedByType->get('monitor', collect())->pluck('property_number')->all(),
                                'avr/ups' => $linkedByType->get('avr', collect())->pluck('property_number')
                                    ->merge($linkedByType->get('ups', collect())->pluck('property_number'))
                                    ->values()
                                    ->all(),
                                'printer' => $linkedByType->get('printer', collect())->pluck('property_number')->all(),
                                default => [],
                            };
                        @endphp
                        <tr>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                <div>{{ $sectionName }}</div>
                                @if($sectionProperties)
                                    <div class="mt-1 text-xs text-indigo-600 dark:text-indigo-300">
                                        Property #: {{ implode(', ', $sectionProperties) }}
                                    </div>
                                @elseif(in_array($sectionKey, ['monitor', 'avr/ups', 'printer'], true))
                                    <div class="mt-1 text-xs text-amber-600 dark:text-amber-300">Property #: Not linked</div>
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
                                            x-on:change="setNotOkRow('{{ $key }}', $event.target.value === 'Not OK'); clearDisposition('{{ $key }}'); applyChecklistDefaults(); refreshChecklistState()"
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
                                    <label class="inline-flex cursor-pointer items-center gap-1">
                                        <input
                                            type="checkbox"
                                            name="disposition[{{ $key }}]"
                                            value="repair"
                                            class="h-3.5 w-3.5 accent-amber-500"
                                            x-bind:disabled="!notOkRows['{{ $key }}']"
                                            x-on:change="$event.target.closest('td').querySelectorAll('input[type=checkbox]').forEach((checkbox) => { if (checkbox !== $event.target) checkbox.checked = false })"
                                            @checked(old("disposition.$key") === 'repair')
                                        >
                                        <span>Repair</span>
                                    </label>
                                    <label class="inline-flex cursor-pointer items-center gap-1">
                                        <input
                                            type="checkbox"
                                            name="disposition[{{ $key }}]"
                                            value="condemn"
                                            class="h-3.5 w-3.5 accent-red-600"
                                            x-bind:disabled="!notOkRows['{{ $key }}']"
                                            x-on:change="$event.target.closest('td').querySelectorAll('input[type=checkbox]').forEach((checkbox) => { if (checkbox !== $event.target) checkbox.checked = false })"
                                            @checked(old("disposition.$key") === 'condemn')
                                        >
                                        <span>Condemn</span>
                                    </label>
                                    <label class="inline-flex cursor-pointer items-center gap-1">
                                        <input
                                            type="checkbox"
                                            name="disposition[{{ $key }}]"
                                            value="not_in_use"
                                            class="h-3.5 w-3.5 accent-slate-500"
                                            x-bind:disabled="!notOkRows['{{ $key }}']"
                                            x-on:change="$event.target.closest('td').querySelectorAll('input[type=checkbox]').forEach((checkbox) => { if (checkbox !== $event.target) checkbox.checked = false })"
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
                        Do you want to verify the checklist?
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
</div>
@endsection
