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
<style>
    /* Mobile checklist cards: keep the desktop table intact, but remove the
       wide-table horizontal scroll requirement on small screens. */
    @media (max-width: 767px) {
        .checklist-progress {
            position: sticky;
            top: 4rem;
            z-index: 20;
            margin-top: 1rem;
            box-shadow: 0 8px 20px rgba(15, 23, 42, .12);
            backdrop-filter: blur(10px);
        }

        .dark .checklist-progress {
            box-shadow: 0 8px 20px rgba(2, 6, 23, .34);
        }

        .checklist-items-table {
            width: 100% !important;
            min-width: 0 !important;
            border-collapse: separate;
            border-spacing: 0;
        }

        .checklist-items-table thead {
            display: none;
        }

        .checklist-items-table tbody {
            display: grid;
            gap: .75rem;
            padding: .75rem;
        }

        .checklist-items-table tbody.divide-y > :not([hidden]) ~ :not([hidden]) {
            border-top-width: 0;
        }

        .checklist-items-table tbody tr {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .5rem;
            padding: .75rem;
            border: 1px solid #d1d5db;
            border-radius: .875rem;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .06);
        }

        .dark .checklist-items-table tbody tr {
            border-color: #374151;
            background: rgba(31, 41, 55, .78);
            box-shadow: none;
        }

        .checklist-items-table tbody td {
            display: block !important;
            min-width: 0;
            padding: .5rem !important;
            border: 0 !important;
        }

        .checklist-items-table tbody td:nth-child(1),
        .checklist-items-table tbody td:nth-child(2),
        .checklist-items-table tbody td:nth-child(6),
        .checklist-items-table tbody td:nth-child(7),
        .checklist-items-table tbody td:nth-child(8) {
            grid-column: 1 / -1;
        }

        .checklist-items-table tbody td:nth-child(1) {
            padding-bottom: 0 !important;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #64748b;
        }

        .dark .checklist-items-table tbody td:nth-child(1) {
            color: #94a3b8;
        }

        .checklist-items-table tbody td:nth-child(2) {
            padding-top: .125rem !important;
            font-size: .9rem;
            font-weight: 600;
        }

        .checklist-items-table tbody td:nth-child(3),
        .checklist-items-table tbody td:nth-child(4),
        .checklist-items-table tbody td:nth-child(5) {
            display: flex !important;
            min-height: 4.25rem;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            border: 1px solid #e5e7eb !important;
            border-radius: .75rem;
            background: #f8fafc;
            text-align: center;
        }

        .dark .checklist-items-table tbody td:nth-child(3),
        .dark .checklist-items-table tbody td:nth-child(4),
        .dark .checklist-items-table tbody td:nth-child(5) {
            border-color: #475569 !important;
            background: rgba(15, 23, 42, .35);
        }

        .checklist-items-table tbody td:nth-child(3)::before,
        .checklist-items-table tbody td:nth-child(4)::before,
        .checklist-items-table tbody td:nth-child(5)::before,
        .checklist-items-table tbody td:nth-child(6)::before,
        .checklist-items-table tbody td:nth-child(7)::before,
        .checklist-items-table tbody td:nth-child(8)::before {
            display: block;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .03em;
            text-transform: uppercase;
            color: #64748b;
        }

        .dark .checklist-items-table tbody td:nth-child(3)::before,
        .dark .checklist-items-table tbody td:nth-child(4)::before,
        .dark .checklist-items-table tbody td:nth-child(5)::before,
        .dark .checklist-items-table tbody td:nth-child(6)::before,
        .dark .checklist-items-table tbody td:nth-child(7)::before,
        .dark .checklist-items-table tbody td:nth-child(8)::before {
            color: #94a3b8;
        }

        .checklist-items-table tbody td:nth-child(3)::before { content: 'OK'; }
        .checklist-items-table tbody td:nth-child(4)::before { content: 'Not OK'; }
        .checklist-items-table tbody td:nth-child(5)::before { content: 'Not Available'; }
        .checklist-items-table tbody td:nth-child(6)::before { content: 'Condition'; }
        .checklist-items-table tbody td:nth-child(7)::before { content: 'Status'; }
        .checklist-items-table tbody td:nth-child(8)::before { content: 'Assigned Staff'; }

        .checklist-items-table tbody td:nth-child(3) span.h-8.w-8,
        .checklist-items-table tbody td:nth-child(4) span.h-8.w-8,
        .checklist-items-table tbody td:nth-child(5) span.h-8.w-8 {
            width: 2.75rem !important;
            height: 2.75rem !important;
        }

        .checklist-items-table tbody td:nth-child(6),
        .checklist-items-table tbody td:nth-child(7) {
            border: 1px solid #e5e7eb !important;
            border-radius: .75rem;
            text-align: left !important;
        }

        .dark .checklist-items-table tbody td:nth-child(6),
        .dark .checklist-items-table tbody td:nth-child(7) {
            border-color: #475569 !important;
        }

        .checklist-items-table tbody td:nth-child(6) > div,
        .checklist-items-table tbody td:nth-child(7) > div {
            align-items: flex-start;
        }

        .checklist-action-bar {
            padding-bottom: calc(.75rem + env(safe-area-inset-bottom));
        }
    }
</style>
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
    $issuanceSectionKeys = ['system unit', 'monitor', 'avr/ups', 'printer'];
    $canChangeIssuance = (bool) auth()->user()?->canAction('issuance', 'edit');
    $issuanceDevices = collect([$device])
        ->merge($device->linkedPeripherals)
        ->filter(function ($candidate) use ($device) {
            $candidateType = strtolower((string) ($candidate->type?->name ?? ''));

            return (int) $candidate->id === (int) $device->id
                || in_array($candidateType, ['monitor', 'avr/ups', 'avr', 'ups', 'printer'], true);
        })
        ->keyBy('id');
    $issuanceDevicePayload = function ($assignmentDevice) use ($checklistPath) {
        $currentAssignment = $assignmentDevice->currentAssignment;
        $assignedStaff = $currentAssignment?->staff;
        $assignedOffice = $currentAssignment?->office ?: $assignedStaff?->office;
        $assignedLocation = $currentAssignment?->location ?: $assignedOffice?->location;
        $assignedStaffName = $assignedStaff
            ? trim(($assignedStaff->last_name ?? '') . ', ' . ($assignedStaff->first_name ?? ''))
            : null;
        $returnPath = $checklistPath . '?issuance_open=1&issuance_device=' . (int) $assignmentDevice->id;

        return [
            'id' => (int) $assignmentDevice->id,
            'type' => $assignmentDevice->type?->name ?? 'Equipment',
            'propertyNumber' => (string) $assignmentDevice->property_number,
            'assignedStaffName' => $assignedStaffName ?: null,
            'hasAssignment' => (bool) $currentAssignment,
            'assignedOfficeName' => $assignedOffice?->name,
            'assignedLocationName' => $assignedLocation
                ? trim(($assignedLocation->code ? $assignedLocation->code . ' - ' : '') . $assignedLocation->name)
                : null,
            'assignedStaffUrl' => $assignedStaff
                ? route('admin.staff.devices.index', $assignedStaff)
                : null,
            'assignedOfficeUrl' => $assignedOffice
                ? route('admin.staff.index', $assignedOffice)
                : null,
            'assignedLocationUrl' => $assignedLocation
                ? route('admin.offices.index', $assignedLocation)
                : null,
            'actionUrl' => route('admin.devices.reissue', $assignmentDevice),
            'addStaffUrl' => $assignedOffice
                ? route('admin.staff.index', [
                    'office' => $assignedOffice->id,
                    'open_add' => 1,
                    'return_to' => $returnPath,
                ])
                : null,
        ];
    };
    $requestedIssuanceDeviceId = request()->integer('issuance_device');
    $initialIssuanceDevice = null;
    if ($canChangeIssuance && request()->boolean('issuance_open') && $requestedIssuanceDeviceId) {
        $requestedIssuanceDevice = $issuanceDevices->get($requestedIssuanceDeviceId);
        if ($requestedIssuanceDevice) {
            $initialIssuanceDevice = $issuanceDevicePayload($requestedIssuanceDevice);
        }
    }
    $pmPlanSchedule = $pmPlanProgress['schedule'] ?? null;
    $pmPlanProgressStats = $pmPlanProgress['progress'] ?? null;
    $pmPlanCompletion = $pmPlanProgress['completion'] ?? null;
    $completionSavedId = session('completion_saved');
    $pmPlanTotal = (int) ($pmPlanProgressStats['total'] ?? 0);
    $pmPlanChecked = (int) ($pmPlanProgressStats['checked'] ?? 0);
    $pmPlanPercent = $pmPlanTotal > 0 ? min(100, (int) round(($pmPlanChecked / $pmPlanTotal) * 100)) : 0;
@endphp

<div
    class="space-y-6"
    @if($completionSavedId)
        data-pmams-completion-saved="1"
        x-data
        x-init='try {
            const ownerKey = @js((string) (auth()->id() ?: "anonymous"));
            const storage = window.sessionStorage;
            const prefix = `pmams-maintenance-completion:${ownerKey}:`;
            Object.keys(storage)
                .filter((key) => key.startsWith(prefix))
                .forEach((key) => storage.removeItem(key));
        } catch (error) {
            // Draft cleanup is best effort in restricted/private browsers.
        }'
    @endif
>
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                    Preventive Maintenance Checklist
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Choose OK, Not OK, or Not Available for each hardware item. Monitor, Keyboard, Mouse, UPS/AVR, and Printer may be marked Not Available.
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

    @if($pmPlanSchedule && $pmPlanProgressStats)
        <section id="pm-plan-progress" class="rounded-xl border {{ $pmPlanProgressStats['is_complete'] ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900/60 dark:bg-emerald-950/20' : 'border-blue-200 bg-blue-50 dark:border-blue-900/60 dark:bg-blue-950/20' }} p-4" aria-live="polite">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-semibold {{ $pmPlanProgressStats['is_complete'] ? 'text-emerald-900 dark:text-emerald-100' : 'text-blue-900 dark:text-blue-100' }}">PM Plan progress</h2>
                    <p class="mt-1 text-sm {{ $pmPlanProgressStats['is_complete'] ? 'text-emerald-800 dark:text-emerald-200' : 'text-blue-800 dark:text-blue-200' }}">{{ $pmPlanProgress['target_label'] ?? 'Assigned office/location' }}</p>
                </div>
                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold {{ $pmPlanProgressStats['is_complete'] ? 'text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-100' : 'text-blue-800 dark:bg-blue-900/50 dark:text-blue-100' }} shadow-sm">{{ $pmPlanChecked }}/{{ $pmPlanTotal }}</span>
            </div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-white/80 dark:bg-gray-800/70" role="progressbar" aria-label="PM Plan equipment progress" aria-valuenow="{{ $pmPlanPercent }}" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full {{ $pmPlanProgressStats['is_complete'] ? 'bg-emerald-500' : 'bg-blue-500' }} transition-all" style="width: {{ $pmPlanPercent }}%"></div>
            </div>
            @if($pmPlanProgressStats['is_complete'])
                @if($pmPlanCompletion)
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm font-semibold text-emerald-800 dark:text-emerald-200">
                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-100">Signed</span>
                        <span>Recorded{{ $pmPlanCompletion->actual_date ? ' on ' . $pmPlanCompletion->actual_date->format('F j, Y') : ' on an unspecified date' }}.</span>
                    </div>
                @elseif($pmPlanProgress['can_complete'] ?? false)
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-200">All equipment in this PM Plan target has a checklist record. Record the office completion sign-off.</p>
                        <a href="{{ $pmPlanProgress['completion_url'] }}" wire:navigate class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:bg-emerald-500 dark:hover:bg-emerald-600 dark:focus:ring-offset-emerald-950/20">Record office completion</a>
                    </div>
                @else
                    <p class="mt-3 text-sm font-semibold text-emerald-800 dark:text-emerald-200">All equipment in this PM Plan target has a checklist record. An assigned administrator can record the office completion sign-off.</p>
                @endif
            @else
                <p class="mt-3 text-xs {{ $pmPlanProgressStats['checked'] > 0 ? 'text-blue-800 dark:text-blue-200' : 'text-blue-700 dark:text-blue-300' }}">{{ $pmPlanChecked }} of {{ $pmPlanTotal }} target equipment records are complete. Progress updates after each checklist is saved.</p>
            @endif
        </section>
    @endif

    <form
        id="maintenance-checklist-form"
        method="POST"
        action="{{ route('admin.devices.checklist.save', $device) }}"
        enctype="multipart/form-data"
        target="_self"
        x-data="{
            remarks: @js(old('remarks', '')),
            remarksEdited: false,
            restoringChecklistState: false,
            correctiveAction: @js(old('corrective_action', '')),
            checklistReady: false,
            checklistAnsweredCount: 0,
            checklistVersion: 0,
            checklistRowCount: {{ count($checklistItems) + count($softwareItems) }},
            duplicateReasonOpen: {{ session('duplicate_warning') ? 'true' : 'false' }},
            verificationReason: @js(old('verification_reason', '')),
            conditionRequiredKeys: @js(collect($checklistItems)
                ->reject(fn ($item) => in_array($item['group'] ?? '', ['Keyboard', 'Mouse'], true))
                ->keys()
                ->values()
                ->all()),
            okRows: @js(collect($checklistItems)->mapWithKeys(fn ($item, $key) => [$key => old("hardware.$key") === 'OK'])->all()),
            notOkRows: @js(collect($checklistItems)->mapWithKeys(fn ($item, $key) => [$key => old("hardware.$key") === 'Not OK'])->all()),
            notAvailableRows: @js(collect($checklistItems)->mapWithKeys(fn ($item, $key) => [$key => old("hardware.$key") === 'Not Available'])->all()),
            conditionRows: @js(collect($checklistItems)->mapWithKeys(fn ($item, $key) => [
                $key => old("condition.$key", ''),
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
                controls.forEach((control) => {
                    const isChoice = control.type === 'radio' || control.type === 'checkbox';
                    const saved = stored.fields.find((candidate) => candidate.name === control.name
                        && (!isChoice
                            || (candidate.type === control.type
                                && String(candidate.value ?? '') === String(control.value ?? ''))));

                    if (!saved) return;

                    if (isChoice) {
                        // Radio buttons and same-name checkbox groups must be
                        // matched by value as well as name. Otherwise restoring
                        // one selected option would toggle every option in the
                        // group and the final radio/checkbox state would be lost.
                        control.checked = Boolean(saved.checked);
                    } else if (typeof saved.value === 'string') {
                        control.value = saved.value;
                    }
                });

                // Replay the same events used by normal user interaction so
                // Alpine rebuilds the row response, availability, condition,
                // and status state. Restored generated remarks must stay
                // eligible for regeneration when a reviewer changes a row.
                this.restoringChecklistState = true;
                try {
                    controls.forEach((control) => {
                        if (!control.name || control.name === '_token' || control.name === '_method') return;
                        if (control.type === 'radio' || control.type === 'checkbox') {
                            if (control.checked) control.dispatchEvent(new Event('change', { bubbles: true }));
                        } else if (['date', 'text', 'textarea', 'search'].includes(control.type || control.tagName?.toLowerCase())) {
                            control.dispatchEvent(new Event('input', { bubbles: true }));
                            control.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    });
                } finally {
                    this.restoringChecklistState = false;
                }

                try {
                    window.sessionStorage.removeItem(this.checklistStateKey());
                } catch (error) {
                    // Storage can be unavailable in private browsing; the
                    // checklist remains usable without state restoration.
                }
            },
            allChecklistAnswered() {
                if (this.answeredChecklistCount() !== this.checklistRowCount) return false;

                // A Not OK row must have an explicit physical condition before
                // the checklist can be submitted. Status remains optional,
                // but it is only enabled after this condition is selected.
                return this.conditionRequiredKeys.every((key) =>
                    !this.isNotOkSelected(key) || Boolean(this.conditionRows[key])
                );
            },
            answeredChecklistCount() {
                const selectedRows = new Set(
                    Array.from(this.$root.querySelectorAll('input[type=radio]:checked'))
                        .map((input) => input.name)
                );

                return selectedRows.size;
            },
            checklistCompletionPercent() {
                return this.checklistRowCount
                    ? Math.round((this.checklistAnsweredCount / this.checklistRowCount) * 100)
                    : 0;
            },
            refreshChecklistState() {
                this.checklistVersion++;
                this.checklistAnsweredCount = this.answeredChecklistCount();
                this.checklistReady = this.allChecklistAnswered();
            },
            setNotOkRow(key, enabled) {
                // Keep result state reactive. Reading only the checked DOM
                // node here would let the remarks generator see a change,
                // but Alpine would not re-evaluate x-show bindings for the
                // condition/status cells.
                this.okRows[key] = !enabled;
                this.notOkRows[key] = enabled;
                this.notAvailableRows[key] = false;

                if (!enabled) {
                    this.conditionRows[key] = '';
                    this.statusRows[key] = '';
                }

                this.syncConditionStatusControls(key);
                this.refreshChecklistState();
            },
            setNotAvailableRow(key) {
                // Not Available is a completed hardware response, but it is
                // intentionally outside the condition/disposition workflow.
                // Clear and disable those controls immediately so a previous
                // Not OK selection cannot keep a stale Repair/Condemn value
                // or make the linked property appear required.
                this.okRows[key] = false;
                this.notOkRows[key] = false;
                this.notAvailableRows[key] = true;
                this.conditionRows[key] = '';
                this.statusRows[key] = '';

                this.syncConditionStatusControls(key);
                this.applyChecklistDefaults();
                this.refreshChecklistState();
            },
            isOkSelected(key) {
                return Boolean(this.okRows[key]);
            },
            isNotOkSelected(key) {
                return Boolean(this.notOkRows[key]);
            },
            isNotAvailableSelected(key) {
                return Boolean(this.notAvailableRows[key]);
            },
            isUnserviceableSelected(key) {
                return this.isNotOkSelected(key)
                    && !this.isNotAvailableSelected(key)
                    && this.conditionRows[key] === 'unserviceable';
            },
            isStatusSelectionVisible(key) {
                const hasStatusControls = this.$root.querySelector(`input[name='disposition[${key}]']`);

                return Boolean(hasStatusControls)
                    && !this.isNotAvailableSelected(key)
                    && (this.isOkSelected(key) || this.isUnserviceableSelected(key));
            },
            syncConditionStatusControls(key) {
                const isOk = this.isOkSelected(key);
                const isNotOk = this.isNotOkSelected(key);
                const isNotAvailable = this.isNotAvailableSelected(key);
                const conditionEnabled = isNotOk && !isNotAvailable;
                const statusEnabled = !isNotAvailable
                    && (isOk || (isNotOk && this.conditionRows[key] === 'unserviceable'));

                this.$root.querySelectorAll('input').forEach((input) => {
                    if (input.name === `condition[${key}]`) {
                        input.disabled = !conditionEnabled;
                        if (!conditionEnabled) input.checked = false;
                        return;
                    }

                    if (input.name === `disposition[${key}]`) {
                        const isAllowed = statusEnabled
                            && (isOk ? input.value === 'not_in_use' : ['repair', 'not_in_use'].includes(input.value));
                        input.disabled = !isAllowed;
                        if (!isAllowed) {
                            input.checked = false;
                        } else {
                            input.checked = input.value === this.statusRows[key];
                        }
                    }
                });
            },
            syncCondition(key, event) {
                let selectedValue = event?.target?.checked
                    ? event.target.value
                    : (this.$root.querySelector(`input[name='condition[${key}]']:checked`)?.value || '');

                // A Not OK result always has a physical condition. If a
                // checkbox is toggled off with no alternative selected, keep
                // the default Unserviceable value aligned with the server.
                if (!selectedValue && this.isNotOkSelected(key)) {
                    selectedValue = 'unserviceable';
                }

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

                this.syncConditionStatusControls(key);
                this.applyChecklistDefaults();
                this.refreshChecklistState();
            },
            syncStatus(key, event) {
                const isOk = this.isOkSelected(key);
                const isUnserviceable = this.isUnserviceableSelected(key);
                const selectedValue = event?.target?.checked
                    && !this.isNotAvailableSelected(key)
                    && (isOk
                        ? event.target.value === 'not_in_use'
                        : isUnserviceable && ['repair', 'not_in_use'].includes(event.target.value))
                    ? event.target.value
                    : '';
                this.statusRows[key] = selectedValue;
                this.$root.querySelectorAll(`input[name='disposition[${key}]']`).forEach((input) => {
                    input.checked = input.value === selectedValue;
                });
                this.syncConditionStatusControls(key);
                this.applyChecklistDefaults();
                this.refreshChecklistState();
            },
            clearDisposition(key) {
                if (this.isNotOkSelected(key)) return;

                this.conditionRows[key] = '';
                this.statusRows[key] = '';

                this.$root.querySelectorAll('input').forEach((input) => {
                    if (input.name === `disposition[${key}]` || input.name === `condition[${key}]`) input.checked = false;
                });

                this.syncConditionStatusControls(key);
            },
            formatSectionList(sections) {
                return sections.join(', ');
            },
            unavailableEquipmentLabels() {
                const form = this.$root;
                const unavailableEquipment = [];
                if (form.elements['hardware[monitor_display]']?.value === 'Not Available') {
                    unavailableEquipment.push('Monitor');
                }
                if (form.elements['hardware[avr_ups_power_recovery]']?.value === 'Not Available') {
                    unavailableEquipment.push('UPS/AVR');
                }
                if (form.elements['hardware[printer_printout]']?.value === 'Not Available') {
                    unavailableEquipment.push('Printer');
                }
                if (form.elements['hardware[keyboard_keys]']?.value === 'Not Available') {
                    unavailableEquipment.push('Keyboard');
                }
                if (form.elements['hardware[mouse_buttons]']?.value === 'Not Available') {
                    unavailableEquipment.push('Mouse');
                }

                return unavailableEquipment;
            },
            generatedRemarks() {
                const sectionGroups = {};
                Array.from(this.$root.querySelectorAll('input'))
                    .filter((input) => input.name.startsWith('hardware[') && input.value === 'Not OK' && input.checked)
                    .forEach((input) => {
                        const key = input.name.match(/^hardware\[(.+)\]$/)?.[1];
                        const section = input.dataset.section;
                        if (!key || !section) return;

                        const prefix = this.statusRows[key] === 'repair'
                            ? 'Repair'
                            : (this.statusRows[key] === 'not_in_use'
                                ? 'Not in Use'
                                : (this.conditionRows[key] === 'condemned'
                                    ? 'Condemned'
                                    : (this.conditionRows[key] === 'unserviceable'
                                        ? 'Unserviceable'
                                        : (['Keyboard', 'Mouse'].includes(section) ? 'Defective' : null))));
                        if (!prefix) return;
                        (sectionGroups[prefix] ||= []).push(section);
                    });

                const unavailableEquipment = this.unavailableEquipmentLabels();
                const defectiveRemarks = Object.entries(sectionGroups)
                    .map(([prefix, sections]) => `${prefix} ${this.formatSectionList(sections)}`)
                    .join('; ');
                if (defectiveRemarks) {
                    return unavailableEquipment.length
                        ? `${defectiveRemarks}; not available ${unavailableEquipment.join(', ')}`
                        : defectiveRemarks;
                }

                if (unavailableEquipment.length) return `not available ${unavailableEquipment.join(', ')}`;

                // Do not call another section Serviceable while a
                // condition-capable Not OK row is still waiting for its
                // required condition choice.
                const hasPendingNotOk = Array.from(this.$root.querySelectorAll('input'))
                    .some((input) => input.name.startsWith('hardware[') && input.value === 'Not OK' && input.checked);
                if (hasPendingNotOk) return '';

                const serviceableKeys = [
                    'system_unit_power_on',
                    'monitor_display',
                    'avr_ups_power_recovery',
                    'printer_printout',
                ];
                if (serviceableKeys.some((key) => this.isOkSelected(key))) return 'Serviceable';
                return '';
            },
            applyChecklistDefaults() {
                const currentRemarks = this.remarks.trim();
                const isGeneratedRemark = currentRemarks === ''
                    || currentRemarks === 'Serviceable'
                    || currentRemarks.startsWith('not available ')
                    || currentRemarks.startsWith('Defective ')
                    || currentRemarks.startsWith('Repair ')
                    || currentRemarks.startsWith('Not in Use ')
                    || currentRemarks.startsWith('Condemned ')
                    || currentRemarks.startsWith('Unserviceable ');

                if (!this.remarksEdited && isGeneratedRemark) {
                    this.remarks = this.generatedRemarks();
                }

                const hasUnavailableEquipment = this.unavailableEquipmentLabels().length > 0;
                const hasNotOkEquipment = Array.from(this.$root.querySelectorAll('input'))
                    .some((input) => input.name.startsWith('hardware[') && input.value === 'Not OK' && input.checked);
                const needsProcurement = hasUnavailableEquipment || hasNotOkEquipment;

                if (needsProcurement && !this.correctiveAction.trim()) {
                    this.correctiveAction = 'office is advised to procure the equipment';
                } else if (!needsProcurement && this.correctiveAction.trim() === 'office is advised to procure the equipment') {
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

        <div class="checklist-progress mt-6 rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900/60 dark:bg-blue-950/20" aria-live="polite">
            <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                <div>
                    <span class="font-semibold text-blue-900 dark:text-blue-100">Checklist progress</span>
                    <span class="ml-2 text-blue-800 dark:text-blue-200" x-text="`${checklistAnsweredCount} of ${checklistRowCount} items completed`"></span>
                </div>
                <span
                    class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-blue-800 shadow-sm dark:bg-blue-900/50 dark:text-blue-100"
                    x-text="`${checklistCompletionPercent()}%`"
                ></span>
            </div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-blue-100 dark:bg-blue-900/50" role="progressbar" aria-label="Checklist completion" x-bind:aria-valuenow="checklistCompletionPercent()" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full bg-blue-600 transition-all duration-300 dark:bg-blue-400" x-bind:style="`width: ${checklistCompletionPercent()}%`"></div>
            </div>
            <p class="mt-2 text-xs text-blue-800 dark:text-blue-200" x-show="!checklistReady" x-cloak>Select one result for every hardware and software item. Not OK rows expose Condition, and Status is available for OK rows or for Not OK rows marked Unserviceable.</p>
            <p class="mt-2 text-xs font-semibold text-emerald-700 dark:text-emerald-300" x-show="checklistReady" x-cloak>All checklist items are complete. You can save this checklist.</p>
        </div>

        <div class="mt-6 rounded-xl border border-gray-200 dark:border-gray-700">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/40">
                <div>
                    <h2 class="font-semibold text-gray-900 dark:text-white">Checklist items</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Choose one result per row. Not OK rows require a condition; status is available for OK rows or after an Unserviceable condition is selected. Assigned Staff is shown for System Unit and linked peripherals.</p>
                </div>
                <div class="flex flex-wrap gap-2 text-[11px] font-semibold">
                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">OK</span>
                    <span class="rounded-full bg-red-100 px-2.5 py-1 text-red-800 dark:bg-red-900/40 dark:text-red-200">Not OK</span>
                    <span class="rounded-full bg-gray-200 px-2.5 py-1 text-gray-700 dark:bg-gray-700 dark:text-gray-200">Not Available</span>
                </div>
            </div>
            <div class="overflow-x-auto">
            <table class="checklist-items-table min-w-[1120px] w-full text-sm">
                <thead class="sticky top-0 z-10 bg-gray-50 text-left dark:bg-gray-900/95">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Section</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Checklist Item</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">OK</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Not OK</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Not Available</th>
                        <th class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Condition</th>
                        <th class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Status</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Assigned Staff</th>
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
                            $sectionEditReturnPath = $checklistPath
                                . '?open_link=1&peripheral_type=' . rawurlencode($sectionKey)
                                . '&allow_linked=1';
                        @endphp
                        <tr>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                <div>{{ $sectionName }}</div>
                                @if($sectionProperties)
                                    @if(auth()->user()?->canAction('equipment', 'edit') && in_array($sectionKey, ['monitor', 'avr/ups', 'printer'], true))
                                        <button
                                            type="button"
                                            title="Change linked equipment"
                                            class="mt-1 inline-flex cursor-pointer items-center gap-1 text-xs font-medium text-indigo-600 underline decoration-dotted underline-offset-2 hover:text-indigo-800 dark:text-indigo-300 dark:hover:text-indigo-200"
                                            x-on:click.prevent="$dispatch('open-checklist-link', { peripheralType: @js($sectionKey), allowLinked: true })"
                                        >
                                            Property #: {{ implode(', ', $sectionProperties) }}
                                            <span aria-hidden="true">&#128279;</span>
                                        </button>
                                        @if($sectionKey !== 'system unit')
                                            <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                                Linked to parent property #: {{ $device->property_number }}
                                            </div>
                                        @endif
                                        @foreach($sectionDevices as $sectionDevice)
                                            <a
                                                href="{{ route('admin.devices.edit', ['device' => $sectionDevice, 'return_to' => $sectionEditReturnPath]) }}"
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
                                    @if(auth()->user()?->canAction('equipment', 'edit'))
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
                                        data-section="{{ $item['group'] ?? '-' }}"
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
                                        x-bind:class="{ 'opacity-50': !isNotOkSelected('{{ $key }}') && !isNotAvailableSelected('{{ $key }}') }"
                                    >
                                        <span x-show="!isNotOkSelected('{{ $key }}') && !isNotAvailableSelected('{{ $key }}')" x-cloak class="font-semibold text-emerald-600 dark:text-emerald-400">Serviceable</span>
                                        <label x-show="isNotOkSelected('{{ $key }}')" x-cloak class="inline-flex cursor-pointer items-center gap-1">
                                            <input
                                                type="checkbox"
                                                name="condition[{{ $key }}]"
                                                value="unserviceable"
                                                class="h-3.5 w-3.5 accent-red-600"
                                                x-bind:disabled="!isNotOkSelected('{{ $key }}')"
                                                x-on:change="syncCondition('{{ $key }}', $event)"
                                                @checked(old("condition.$key") === 'unserviceable')
                                            >
                                            <span>Unserviceable</span>
                                        </label>
                                        <label x-show="isNotOkSelected('{{ $key }}')" x-cloak class="inline-flex cursor-pointer items-center gap-1">
                                            <input
                                                type="checkbox"
                                                name="condition[{{ $key }}]"
                                                value="condemned"
                                                class="h-3.5 w-3.5 accent-red-700"
                                                x-bind:disabled="!isNotOkSelected('{{ $key }}')"
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

                            <td
                                class="px-3 py-3 text-center"
                                x-cloak
                                x-show="isStatusSelectionVisible('{{ $key }}')"
                            >
                                @if(!in_array($item['group'] ?? '', ['Keyboard', 'Mouse'], true))
                                    <div
                                        x-show="isStatusSelectionVisible('{{ $key }}')"
                                        x-cloak
                                        class="flex flex-col items-start justify-center gap-1 text-xs text-gray-600 dark:text-gray-300"
                                        x-bind:class="{ 'opacity-50': !isStatusSelectionVisible('{{ $key }}') }"
                                    >
                                        <label x-show="isUnserviceableSelected('{{ $key }}')" x-cloak class="inline-flex cursor-pointer items-center gap-1">
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
                                        <label x-show="isOkSelected('{{ $key }}') || isUnserviceableSelected('{{ $key }}')" x-cloak class="inline-flex cursor-pointer items-center gap-1">
                                            <input
                                                type="checkbox"
                                                name="disposition[{{ $key }}]"
                                                value="not_in_use"
                                                class="h-3.5 w-3.5 accent-slate-500"
                                                x-bind:disabled="!isStatusSelectionVisible('{{ $key }}')"
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

                            <td class="px-4 py-3 align-top text-left text-xs text-gray-600 dark:text-gray-300">
                                @if(in_array($sectionKey, $issuanceSectionKeys, true))
                                    @if($sectionDevices->isEmpty())
                                        <span class="text-gray-400 dark:text-gray-500">Not linked</span>
                                    @else
                                        <div class="space-y-2">
                                            @foreach($sectionDevices as $sectionDevice)
                                                @php
                                                    $currentAssignment = $sectionDevice->currentAssignment;
                                                    $issuanceDevice = $issuanceDevicePayload($sectionDevice);
                                                @endphp
                                                <div class="rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-2 dark:border-gray-700 dark:bg-gray-900/40">
                                                    <div class="font-medium text-gray-500 dark:text-gray-400">
                                                        {{ $issuanceDevice['type'] }} · {{ $issuanceDevice['propertyNumber'] }}
                                                    </div>
                                                    @if($issuanceDevice['assignedStaffName'])
                                                        <a
                                                            href="{{ $issuanceDevice['assignedStaffUrl'] }}"
                                                            wire:navigate
                                                            class="mt-0.5 inline-flex font-semibold text-indigo-700 hover:underline dark:text-indigo-300"
                                                            title="View equipment assigned to {{ $issuanceDevice['assignedStaffName'] }}"
                                                        >
                                                            {{ $issuanceDevice['assignedStaffName'] }}
                                                        </a>
                                                    @elseif($currentAssignment)
                                                        <span class="mt-0.5 inline-flex font-semibold text-gray-700 dark:text-gray-200">Location assignment</span>
                                                    @else
                                                        <span class="mt-0.5 inline-flex font-semibold text-gray-500 dark:text-gray-400">Not assigned</span>
                                                    @endif
                                                    @if($issuanceDevice['assignedOfficeName'] || $issuanceDevice['assignedLocationName'])
                                                        <div class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                                                            @if($issuanceDevice['assignedOfficeUrl'])
                                                                <a href="{{ $issuanceDevice['assignedOfficeUrl'] }}" wire:navigate class="hover:underline">{{ $issuanceDevice['assignedOfficeName'] }}</a>
                                                            @elseif($issuanceDevice['assignedOfficeName'])
                                                                {{ $issuanceDevice['assignedOfficeName'] }}
                                                            @endif
                                                            @if($issuanceDevice['assignedOfficeName'] && $issuanceDevice['assignedLocationName'])
                                                                <span aria-hidden="true"> / </span>
                                                            @endif
                                                            @if($issuanceDevice['assignedLocationUrl'])
                                                                <a href="{{ $issuanceDevice['assignedLocationUrl'] }}" wire:navigate class="hover:underline">{{ $issuanceDevice['assignedLocationName'] }}</a>
                                                            @elseif($issuanceDevice['assignedLocationName'])
                                                                {{ $issuanceDevice['assignedLocationName'] }}
                                                            @endif
                                                        </div>
                                                    @endif
                                                    @if($canChangeIssuance)
                                                        <button
                                                            type="button"
                                                            class="mt-1 inline-flex items-center rounded-md bg-cyan-100 px-2 py-1 text-[11px] font-semibold text-cyan-800 hover:bg-cyan-200 dark:bg-cyan-900/40 dark:text-cyan-200 dark:hover:bg-cyan-900/60"
                                                            x-on:click.prevent="$dispatch('open-checklist-issuance', @js($issuanceDevice))"
                                                        >
                                                            {{ $currentAssignment ? 'Change issuance' : 'Assign staff' }}
                                                        </button>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
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
                            <td class="px-3 py-3 text-center text-gray-300 dark:text-gray-600">—</td>
                            <td class="px-4 py-3 text-left text-gray-300 dark:text-gray-600">—</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-5 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Remarks</label>
                <textarea
                    name="remarks"
                    x-model="remarks"
                    x-on:input="if (!restoringChecklistState) remarksEdited = true"
                    rows="3"
                    class="min-h-[7rem] w-full resize-y rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    placeholder="Optional remarks; automatic text will appear when applicable"
                ></textarea>
 
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Corrective Action</label>
                <textarea
                    name="corrective_action"
                    x-model="correctiveAction"
                    rows="3"
                    class="min-h-[7rem] w-full resize-y rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    placeholder="Optional corrective action"
                ></textarea>
            </div>
        </div>

        <div class="checklist-action-bar sticky bottom-3 z-20 mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3  dark:border-gray-700 dark:bg-gray-800/95 sm:static sm:rounded-none sm:border-0 sm:bg-transparent sm:p-0 sm:shadow-none sm:backdrop-blur-0">
            <span class="text-xs text-gray-500 dark:text-gray-400" x-show="!checklistReady" x-cloak>Complete every row to enable saving.</span>
            <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-300" x-show="checklistReady" x-cloak>Ready to save</span>
            <div class="ml-auto flex flex-wrap justify-end gap-2">
            <a
                href="{{ route('admin.devices.show', $device) }}"
                class="inline-flex min-w-[4.5rem] items-center justify-center rounded-lg bg-gray-100 px-4 py-2 text-center text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
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

    @if(auth()->user()?->canAction('equipment', 'edit'))
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
                linkDraftKey() {
                    return `pmams-checklist-link:${window.location.pathname}`;
                },
                readLinkDraft() {
                    try {
                        const stored = JSON.parse(window.sessionStorage.getItem(this.linkDraftKey()) || 'null');
                        return stored?.version === 1 ? stored : null;
                    } catch (error) {
                        return null;
                    }
                },
                rememberLinkState() {
                    if (!this.linkOpen) return;

                    try {
                        window.sessionStorage.setItem(this.linkDraftKey(), JSON.stringify({
                            version: 1,
                            peripheralType: this.peripheralType || '',
                            allowLinked: Boolean(this.allowLinked),
                            peripheralQuery: this.peripheralQuery || '',
                            selectedPeripheralId: this.selectedPeripheral?.id ?? null,
                            parentPropertyNumber: this.parentPropertyNumber || '',
                        }));
                    } catch (error) {
                        // State restoration is best effort only.
                    }
                },
                clearLinkState() {
                    try {
                        window.sessionStorage.removeItem(this.linkDraftKey());
                    } catch (error) {
                        // Storage can be unavailable in private browsing.
                    }
                },
                rebuildLinkCandidates() {
                    const type = String(this.peripheralType || '').toLowerCase();
                    this.candidates = this.linkablePeripherals.filter((peripheral) => {
                        const name = String(peripheral.type || '').toLowerCase();
                        const matchesType = type === 'avr/ups'
                            ? ['avr', 'ups'].includes(name)
                            : name === type;
                        return matchesType && (this.allowLinked || !peripheral.parent_property_number);
                    });
                },
                restoreLinkState() {
                    const stored = this.readLinkDraft();
                    const sameParent = stored
                        && String(stored.parentPropertyNumber || '') === String(this.parentPropertyNumber || '');

                    if (sameParent) {
                        this.peripheralType = stored.peripheralType || this.peripheralType;
                        this.allowLinked = Boolean(stored.allowLinked);
                        this.peripheralQuery = stored.peripheralQuery || '';
                        this.rebuildLinkCandidates();
                        this.selectedPeripheral = this.candidates.find((peripheral) =>
                            String(peripheral.id) === String(stored.selectedPeripheralId)
                        ) || null;
                        this.linkOpen = true;
                        return;
                    }

                    if (stored) this.clearLinkState();

                    if (this.linkOpen && this.peripheralType) {
                        this.rebuildLinkCandidates();
                        this.rememberLinkState();
                    }
                },
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
                    this.rememberLinkState();

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

                        this.clearLinkState();

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
                    this.rebuildLinkCandidates();
                    this.linkOpen = true;
                    this.rememberLinkState();
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
                    this.rememberLinkState();
                    const path = window.adminLocalNavigatePath
                        ? window.adminLocalNavigatePath(url)
                        : `${url.pathname}${url.search}`;
                    if (window.Livewire?.navigate) {
                        window.Livewire.navigate(path);
                    } else {
                        window.location.assign(url.toString());
                    }
                },
                peripheralEditReturnUrl(peripheral) {
                    const target = new URL(this.editReturnTo, window.location.origin);
                    const typeName = String(peripheral?.type || this.peripheralType || '').toLowerCase();
                    const requestedType = ['avr', 'ups'].includes(typeName)
                        ? 'avr/ups'
                        : (typeName || 'monitor');

                    target.searchParams.set('open_link', '1');
                    target.searchParams.set('peripheral_type', requestedType);
                    // Keep linked records visible after returning from edit.
                    target.searchParams.set('allow_linked', '1');

                    return `${target.pathname}${target.search}`;
                },
                selectPeripheral(peripheral) {
                    this.linkError = '';
                    this.selectedPeripheral = peripheral;
                    this.rememberLinkState();
                }
            }"
            x-init="restoreLinkState()"
            x-on:open-checklist-link.window="openLink($event.detail.peripheralType, $event.detail.allowLinked)"
            x-on:pmams-modal-close.window="if ($event.detail.id === 'checklist-link-modal') clearLinkState()"
        >
            <x-modal id="checklist-link-modal" show="linkOpen" title="Link Peripheral to This System Unit" maxWidth="max-w-xl">
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
                            aria-label="Search linked peripheral"
                            x-model="peripheralQuery"
                            x-on:input="$nextTick(() => rememberLinkState())"
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
                                        x-bind:href="`${linkBaseUrl}/${peripheral.id}/edit?return_to=${encodeURIComponent(peripheralEditReturnUrl(peripheral))}`"
                                        wire:navigate
                                        x-on:click="rememberLinkState()"
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
                            x-on:click="clearLinkState(); linkOpen = false"
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

    @if($canChangeIssuance)
        <div
            x-data="{
                issuanceOpen: @json((bool) $initialIssuanceDevice),
                issuanceDevice: @js($initialIssuanceDevice),
                issuanceStaffLookupUrl: @js(route('admin.devices.lookup.staff')),
                issuanceStaffQuery: '',
                issuanceStaffId: '',
                issuanceStaffResults: [],
                issuanceStaffLoading: false,
                issuanceStaffHasSearched: false,
                issuanceStaffTimer: null,
                issuanceStaffAbort: null,
                issuanceRemarks: '',
                issuanceSubmitting: false,
                issuanceError: '',
                openIssuance(device) {
                    this.issuanceDevice = device;
                    this.issuanceStaffQuery = '';
                    this.issuanceStaffId = '';
                    this.issuanceStaffResults = [];
                    this.issuanceStaffLoading = false;
                    this.issuanceStaffHasSearched = false;
                    this.issuanceRemarks = '';
                    this.issuanceSubmitting = false;
                    this.issuanceError = '';
                    this.issuanceOpen = true;
                    this.removeIssuanceQuery();
                    this.$nextTick(() => this.$refs.issuanceStaffSearch?.focus());
                },
                resetIssuance() {
                    clearTimeout(this.issuanceStaffTimer);
                    if (this.issuanceStaffAbort) this.issuanceStaffAbort.abort();
                    this.issuanceOpen = false;
                    this.issuanceDevice = null;
                    this.issuanceStaffQuery = '';
                    this.issuanceStaffId = '';
                    this.issuanceStaffResults = [];
                    this.issuanceStaffLoading = false;
                    this.issuanceStaffHasSearched = false;
                    this.issuanceRemarks = '';
                    this.issuanceSubmitting = false;
                    this.issuanceError = '';
                    this.removeIssuanceQuery();
                },
                removeIssuanceQuery() {
                    const target = new URL(window.location.href);
                    target.searchParams.delete('issuance_open');
                    target.searchParams.delete('issuance_device');
                    window.history.replaceState({}, '', `${target.pathname}${target.search}`);
                },
                selectIssuanceStaff(staff) {
                    this.issuanceStaffId = staff.id;
                    this.issuanceStaffQuery = [staff.name, staff.position, staff.office]
                        .filter(Boolean)
                        .join(' - ');
                    this.issuanceStaffResults = [];
                },
                queueIssuanceStaffLookup() {
                    clearTimeout(this.issuanceStaffTimer);
                    this.issuanceStaffTimer = setTimeout(() => this.fetchIssuanceStaff(), 250);
                },
                async fetchIssuanceStaff() {
                    const query = this.issuanceStaffQuery.trim();

                    if (query.length < 2) {
                        if (this.issuanceStaffAbort) this.issuanceStaffAbort.abort();
                        this.issuanceStaffResults = [];
                        this.issuanceStaffHasSearched = false;
                        this.issuanceStaffLoading = false;
                        return;
                    }

                    if (this.issuanceStaffAbort) this.issuanceStaffAbort.abort();

                    this.issuanceStaffAbort = new AbortController();
                    this.issuanceStaffLoading = true;
                    this.issuanceStaffHasSearched = true;
                    this.issuanceError = '';

                    try {
                        const url = new URL(this.issuanceStaffLookupUrl, window.location.origin);
                        url.searchParams.set('q', query);

                        const response = await fetch(url, {
                            headers: { 'Accept': 'application/json' },
                            signal: this.issuanceStaffAbort.signal,
                        });

                        if (!response.ok) throw new Error('Unable to search staff.');

                        const data = await response.json();
                        this.issuanceStaffResults = Array.isArray(data.results) ? data.results : [];
                    } catch (error) {
                        if (error.name !== 'AbortError') {
                            this.issuanceStaffResults = [];
                            this.issuanceError = error.message || 'Unable to search staff.';
                        }
                    } finally {
                        this.issuanceStaffLoading = false;
                    }
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
                        window.sessionStorage.setItem(`pmams-checklist-state:${window.location.pathname}`, JSON.stringify({ fields }));
                    } catch (error) {
                        // Draft preservation is best effort only.
                    }
                },
                submitIssuance(event) {
                    if (!this.issuanceDevice || !this.issuanceStaffId || this.issuanceSubmitting) {
                        event.preventDefault();
                        return;
                    }

                    this.rememberChecklistState();
                    this.issuanceSubmitting = true;
                }
            }"
            x-init="if (issuanceOpen && issuanceDevice) { removeIssuanceQuery(); $nextTick(() => $refs.issuanceStaffSearch?.focus()); }"
            x-on:open-checklist-issuance.window="openIssuance($event.detail)"
            x-on:pmams-modal-close.window="if ($event.detail.id === 'checklist-issuance-modal') resetIssuance()"
        >
            <x-modal id="checklist-issuance-modal" show="issuanceOpen" title="Assign or Change Equipment Issuance" maxWidth="max-w-lg">
                <form
                    method="POST"
                    x-bind:action="issuanceDevice?.actionUrl || '#'"
                    x-on:submit="submitIssuance($event)"
                    class="space-y-4"
                >
                    @csrf

                    <div class="rounded-xl border border-cyan-200 bg-cyan-50 p-3 text-sm text-cyan-900 dark:border-cyan-900/50 dark:bg-cyan-900/20 dark:text-cyan-100">
                        <div class="font-semibold">
                            <span x-text="issuanceDevice?.type || 'Equipment'"></span>
                            <span x-text="issuanceDevice?.propertyNumber ? ` · ${issuanceDevice.propertyNumber}` : ''"></span>
                        </div>
                        <div class="mt-1" x-show="issuanceDevice?.assignedStaffName">
                            Currently assigned to <span class="font-semibold" x-text="issuanceDevice?.assignedStaffName"></span>.
                        </div>
                        <div class="mt-1" x-show="!issuanceDevice?.assignedStaffName && issuanceDevice?.hasAssignment">
                            This equipment has a location assignment. Select the staff member who will receive it.
                        </div>
                        <div class="mt-1" x-show="!issuanceDevice?.hasAssignment">
                            This equipment is not currently assigned. Select the staff member who will receive it.
                        </div>
                        <div class="mt-1">The equipment location follows the selected staff member's registered office.</div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Search registered staff</label>
                            <a
                                x-show="issuanceDevice?.addStaffUrl"
                                x-bind:href="issuanceDevice?.addStaffUrl || '#'"
                                data-no-spa="true"
                                x-on:click="rememberChecklistState()"
                                class="inline-flex shrink-0 items-center rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                            >
                                + Add Staff
                            </a>
                        </div>
                        <input
                            type="text"
                            x-ref="issuanceStaffSearch"
                            data-pmams-inline-search
                            x-model="issuanceStaffQuery"
                            x-on:input="issuanceStaffId = ''; queueIssuanceStaffLookup()"
                            placeholder="Search name, email, or office"
                            aria-label="Search registered staff"
                            autocomplete="off"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                        <input type="hidden" name="staff_id" x-model="issuanceStaffId">
                        <div class="mt-2 max-h-48 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700" x-show="!issuanceStaffId">
                            <template x-if="issuanceStaffLoading">
                                <div class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">Searching staff...</div>
                            </template>
                            <template x-if="!issuanceStaffLoading && !issuanceStaffHasSearched && issuanceStaffResults.length === 0">
                                <div class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">Type at least 2 characters of the staff name, email, or office.</div>
                            </template>
                            <template x-for="staff in issuanceStaffResults" :key="staff.id">
                                <button type="button" x-on:click="selectIssuanceStaff(staff)" class="block w-full px-3 py-2 text-left text-sm hover:bg-cyan-50 dark:hover:bg-gray-700">
                                    <span class="font-medium text-gray-900 dark:text-white" x-text="staff.name"></span>
                                    <span class="block text-xs text-gray-500" x-text="[staff.position, staff.office].filter(Boolean).join(' - ')"></span>
                                    <span class="block text-xs text-gray-400" x-show="staff.email" x-text="staff.email"></span>
                                </button>
                            </template>
                            <div x-show="!issuanceStaffLoading && issuanceStaffHasSearched && issuanceStaffResults.length === 0" class="px-3 py-3 text-sm text-gray-500">No registered staff found.</div>
                        </div>
                        <div x-show="issuanceStaffId" class="mt-2 rounded-lg bg-cyan-50 px-3 py-2 text-sm text-cyan-900 dark:bg-cyan-900/20 dark:text-cyan-100">Selected: <span class="font-medium" x-text="issuanceStaffQuery"></span></div>
                        <p x-show="issuanceError" x-cloak class="mt-1 text-sm text-red-600 dark:text-red-400" x-text="issuanceError"></p>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Issuance remarks <span class="font-normal text-gray-500">(optional)</span></label>
                        <textarea name="issuance_remarks" x-model="issuanceRemarks" rows="3" maxlength="1000" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="Reason or activity log remarks (optional)"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-gray-200 pt-4">
                        <button type="button" x-on:click="resetIssuance()" class="rounded-lg bg-gray-100 px-4 py-2 text-sm text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200">Cancel</button>
                        <button type="submit" x-bind:disabled="!issuanceStaffId || issuanceSubmitting" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-50">
                            <span x-text="issuanceSubmitting ? 'Saving…' : (issuanceDevice?.hasAssignment ? 'Save issuance change' : 'Assign staff')"></span>
                        </button>
                    </div>
                </form>
            </x-modal>
        </div>
    @endif
</div>

@endsection
