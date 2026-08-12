@extends('admin.layouts.app')

@section('title', 'Maintenance Attention')
@section('page_title', 'Maintenance Attention')
@section('breadcrumbs')
    <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-600">Dashboard</a><span>/</span>
    <a href="{{ route('admin.reports.index') }}" class="hover:text-blue-600">Reports</a><span>/</span>
    <span class="font-medium">Maintenance Attention</span>
@endsection

@section('content')
<div class="space-y-6">
    @if(session('status'))
        <div class="text-sm font-medium text-emerald-700 dark:text-emerald-300" role="status">{{ session('status') }}</div>
    @endif
    <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/20">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-amber-950 dark:text-amber-100">Maintenance attention</h1>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-amber-900/80 dark:text-amber-200/80">
                    Local, explainable recommendations for Desktop, Laptop, Printer, Monitor, and UPS equipment that may need attention before the next preventive-maintenance cycle.
                    Desktop/Laptop rows use hardware and license signals; Printer/Monitor/UPS rows use condition, status, age, and maintenance history only.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="rounded-full bg-amber-200 px-3 py-1 font-semibold text-amber-950 dark:bg-amber-900/70 dark:text-amber-100">
                    @if($loaded) {{ number_format($reviewCount) }} need review @else Not loaded @endif
                </span>
                <span class="rounded-full bg-white/70 px-3 py-1 text-amber-900 dark:bg-gray-900/50 dark:text-amber-200">
                    @if($loaded) {{ number_format($recommendations->total()) }} equipment scored @else Apply a filter to load @endif
                </span>
                @php
                    $modelReady = config('maintenance.attention_ai.enabled') && is_file(config('maintenance.attention_ai.model'));
                    $modeLabel = match ($mode) {
                        'rules' => 'Laravel rules',
                        'ai' => $modelReady ? 'Local AI' : 'Local AI (rules fallback)',
                        default => $modelReady ? 'Rules + Local AI' : 'Laravel rules (AI not trained)',
                    };
                @endphp
                <span class="rounded-full bg-indigo-100 px-3 py-1 font-semibold text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200">{{ $modeLabel }}</span>
            </div>
        </div>
        @if($loaded)
            @php
                $attentionExportQuery = request()->except('page');
            @endphp
            <div class="mt-4 flex flex-wrap items-center justify-end gap-2 border-t border-amber-200/70 pt-4 dark:border-amber-900/50">
                <a href="{{ route('admin.maintenance-attention.pdf', $attentionExportQuery) }}" target="_blank" rel="noopener" data-no-spa="true" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-300" title="Open the filtered Maintenance Attention report as a PDF for printing">
                    Print
                </a>
                <a href="{{ route('admin.maintenance-attention.excel', $attentionExportQuery) }}" data-no-spa="true" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-300" title="Download the filtered Maintenance Attention report as Excel">
                    Export Excel
                </a>
            </div>
        @endif
        @if(auth()->user()?->isSuperAdmin())
            <form method="POST" action="{{ route('admin.maintenance-attention.mode') }}" class="mt-4 flex flex-wrap items-end gap-3 rounded-xl border border-amber-200/80 bg-white/60 p-3 dark:border-amber-900/50 dark:bg-gray-900/30">
                @csrf
                <div class="min-w-[16rem] flex-1">
                    <label for="maintenance-attention-mode" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-amber-900 dark:text-amber-200">Recommendation engine (Super Admin)</label>
                    <select id="maintenance-attention-mode" name="mode" class="w-full rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200 dark:border-amber-800 dark:bg-gray-800 dark:text-white">
                        <option value="rules" @selected($mode === 'rules')>Laravel coded rules only</option>
                        <option value="ai" @selected($mode === 'ai')>Local AI trained model only</option>
                        <option value="hybrid" @selected($mode === 'hybrid')>Rules + Local AI (recommended)</option>
                    </select>
                </div>
                <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-300">Save engine</button>
            </form>
            <p class="mt-2 text-xs text-amber-900/70 dark:text-amber-200/70">AI mode uses the trained local model when available and safely falls back to Laravel rules when it is not.</p>
        @endif
    </section>

    <section class="rounded-2xl border border-indigo-200 bg-indigo-50/70 p-5 shadow-sm dark:border-indigo-900/60 dark:bg-indigo-950/20" aria-labelledby="maintenance-attention-reference">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="maintenance-attention-reference" class="text-lg font-semibold text-indigo-950 dark:text-indigo-100">Recommendation criteria reference</h2>
                <p class="mt-1 text-sm leading-6 text-indigo-900/80 dark:text-indigo-200/80">Use these criteria to interpret each score. Recommendations are advisory and do not change equipment, checklist, or issuance data.</p>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2 text-xs">
                @if(!empty($aiMetadata))
                    <span class="rounded-full bg-indigo-200 px-3 py-1 font-semibold text-indigo-900 dark:bg-indigo-900/70 dark:text-indigo-100">
                        Model trained: {{ number_format((int) ($aiMetadata['samples'] ?? 0)) }} samples
                    </span>
                @else
                    <span class="rounded-full bg-gray-200 px-3 py-1 font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-200">Model artifact not available</span>
                @endif
                <span class="rounded-full bg-indigo-100 px-3 py-1 font-semibold text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200">
                    Last auto-trained:
                    {{ $aiTrainedAt ? $aiTrainedAt->format('M j, Y g:i A') : 'Not trained yet' }}
                </span>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <article class="rounded-xl border border-indigo-200 bg-white/80 p-4 dark:border-indigo-900/60 dark:bg-gray-900/30">
                <h3 class="font-semibold text-gray-900 dark:text-white">Laravel coded rules</h3>
                <ul class="mt-2 space-y-1.5 text-sm leading-5 text-gray-700 dark:text-gray-300">
                    <li>• Covers Desktop, Laptop, Printer, Monitor, and UPS; condemned equipment is excluded.</li>
                    <li>• Printer, Monitor, and UPS recommendations do not inspect RAM, storage, OS, or Office specifications.</li>
                    <li>• Windows 10/11 with RAM ≤ 8 GB: recommend at least 16 GB RAM.</li>
                    <li>• Windows 7/8 with RAM ≤ 4 GB: recommend at least 8 GB RAM.</li>
                    <li>• Desktop with HDD: recommend an SSD flash-storage upgrade.</li>
                    <li>• Cracked OS or Microsoft Office: recommend procuring a genuine license.</li>
                    <li>• Adds points for unserviceable, Repair/Not in Use, recent issues, equipment procured at least 6 years ago, overdue/missing maintenance, and repeated transfers.</li>
                </ul>
                <p class="mt-3 text-xs text-gray-600 dark:text-gray-400">Priority bands: Critical ≥ 75, High ≥ 50, Medium ≥ 25, otherwise Low.</p>
            </article>

            <article class="rounded-xl border border-purple-200 bg-white/80 p-4 dark:border-purple-900/60 dark:bg-gray-900/30">
                <h3 class="font-semibold text-gray-900 dark:text-white">Local AI trained model</h3>
                <ul class="mt-2 space-y-1.5 text-sm leading-5 text-gray-700 dark:text-gray-300">
                    <li>• A local Random Forest model trained from PMAMS inventory and recent checklist history.</li>
                    <li>• Desktop/Laptop rows use RAM, HDD, equipment type, license flags, issue count, transfers, age, and maintenance recency.</li>
                    <li>• Printer/Monitor/UPS rows use equipment type, condition/status, issue count, transfers, age, and maintenance recency; hardware/license fields are ignored.</li>
                    <li>• The initial training label is derived from the same auditable rule signals; it is not an external or online service.</li>
                    <li>• At ≥ 70% predicted attention probability, the card is marked <strong>AI recommended</strong>.</li>
                    <li>• AI mode controls the score; Hybrid mode keeps Laravel reasons and lets AI raise priority.</li>
                </ul>
                <p class="mt-3 text-xs text-gray-600 dark:text-gray-400">Age-based AI labels use the same 6-year procurement threshold as the Laravel rules.</p>
                <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">If Python, dependencies, or the model is unavailable, PMAMS safely displays Laravel rules instead.</p>
            </article>
        </div>
    </section>

    <form method="GET" action="{{ route('admin.maintenance-attention.index') }}" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-12 md:items-end">
            <div class="min-w-0 md:col-span-2">
                <label for="maintenance-attention-year" class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Year</label>
                <input id="maintenance-attention-year" name="year" type="number" min="2000" max="2100" value="{{ $selectedYear }}" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
            </div>
            <div class="min-w-0 md:col-span-3">
                <label for="maintenance-attention-semester" class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Semester</label>
                <select id="maintenance-attention-semester" name="semester" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="1" @selected((int) $selectedSemester === 1)>1st Semi-Annually (Jan-Jun)</option>
                    <option value="2" @selected((int) $selectedSemester === 2)>2nd Semi-Annually (Jul-Dec)</option>
                </select>
            </div>
            <div class="min-w-0 md:col-span-5">
                <label for="maintenance-attention-search" class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Search equipment</label>
                <input id="maintenance-attention-search" data-pmams-persist-input name="q" value="{{ $q }}" type="search" autocomplete="off" placeholder="Search property #, serial #, model, brand..." class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400" />
            </div>
            <div class="min-w-0 md:col-span-2">
                <label for="maintenance-attention-location" class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Location</label>
                <select id="maintenance-attention-location" name="location" onchange="window.pmamsUpdateMaintenanceOffices(this)" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">All locations</option>
                    @foreach($locations as $availableLocation)
                        <option value="{{ $availableLocation }}" @selected($location === $availableLocation)>{{ $availableLocation }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-0 md:col-span-2">
                <label for="maintenance-attention-office" class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Office</label>
                <select id="maintenance-attention-office" name="office" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">All offices</option>
                    @foreach($offices as $availableOffice)
                        <option value="{{ $availableOffice }}" @selected($office === $availableOffice)>{{ $availableOffice }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-0 md:col-span-2">
                <label for="maintenance-attention-type" class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Attention filter</label>
                <select id="maintenance-attention-type" name="attention" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">All recommendations</option>
                    <option value="ram_upgrade" @selected($attention === 'ram_upgrade')>Memory (RAM) Upgrade</option>
                    <option value="hdd_upgrade" @selected($attention === 'hdd_upgrade')>Storage Upgrade</option>
                    <option value="cracked_os" @selected($attention === 'cracked_os')>Procure Genuine OS License</option>
                    <option value="cracked_ms_office" @selected($attention === 'cracked_ms_office')>Procure Genuine MS Office License</option>
                    <option value="old_equipment" @selected($attention === 'old_equipment')>Old equipment (6+ years)</option>
                </select>
            </div>
            <div class="min-w-0 md:col-span-2">
                <label for="maintenance-attention-priority" class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Priority</label>
                <select id="maintenance-attention-priority" name="priority" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">All priorities</option>
                    @foreach($priorityOptions as $priorityOption)
                        @php
                            $priorityKey = strtolower($priorityOption);
                        @endphp
                        <option value="{{ $priorityKey }}" @selected($priority === $priorityKey)>{{ $priorityOption }}</option>
                    @endforeach
                </select>
            </div>
            @php
                $equipmentTypeKeys = array_map(static fn ($equipmentTypeOption) => strtolower((string) $equipmentTypeOption), $equipmentTypeOptions);
                // An empty type filter means all supported types. Keep that
                // meaning when rendering the form so a refresh never appears
                // to lose the user's selection.
                $allEquipmentTypesSelected = $equipmentTypes === []
                    || count(array_intersect($equipmentTypes, $equipmentTypeKeys)) === count($equipmentTypeKeys);
            @endphp
            <div class="min-w-0 md:col-span-3">
                <div class="mb-1 flex items-center justify-between gap-2">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">Equipment type</label>
                    <span id="maintenance-attention-equipment-types-summary" class="text-xs text-gray-500 dark:text-gray-400" aria-live="polite"></span>
                </div>
                <div class="rounded-lg border border-gray-300 bg-white p-3 dark:border-gray-600 dark:bg-gray-700">
                    <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-800 dark:text-gray-100">
                        <input id="maintenance-attention-equipment-types-all" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-800" @checked($allEquipmentTypesSelected)>
                        <span>Select all supported types</span>
                    </label>
                    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach($equipmentTypeOptions as $equipmentTypeOption)
                            @php
                                $equipmentTypeKey = strtolower((string) $equipmentTypeOption);
                            @endphp
                            <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1 text-sm text-gray-700 transition hover:bg-blue-50 dark:text-gray-200 dark:hover:bg-gray-600">
                                <input type="checkbox" name="equipment_types[]" value="{{ $equipmentTypeKey }}" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-800" data-maintenance-attention-equipment-type @checked($allEquipmentTypesSelected || in_array($equipmentTypeKey, $equipmentTypes, true))>
                                <span>{{ $equipmentTypeOption }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Leave all unchecked to include every supported equipment type. Press <strong>Apply</strong> to run the filter.</span>
            </div>
            <div class="min-w-0 md:col-span-2">
                <label for="maintenance-attention-condition" class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Condition</label>
                <select id="maintenance-attention-condition" name="condition" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">All conditions</option>
                    @foreach($conditionOptions as $conditionOption)
                        <option value="{{ $conditionOption }}" @selected($condition === $conditionOption)>{{ ucwords(str_replace('_', ' ', $conditionOption)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-0 md:col-span-2">
                <label for="maintenance-attention-status" class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Status</label>
                <select id="maintenance-attention-status" name="status" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">All statuses</option>
                    @foreach($statusOptions as $statusOption)
                        <option value="{{ $statusOption }}" @selected($status === $statusOption)>{{ ucwords(str_replace('_', ' ', $statusOption)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-0 md:col-span-1 flex items-end">
                <button type="submit" class="inline-flex min-h-10 w-full items-center justify-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-300" title="Apply selected equipment type filters">Apply</button>
            </div>
            <div class="min-w-0 md:col-span-1 flex items-end">
                <a href="{{ route('admin.maintenance-attention.index', ['reset' => 1]) }}" class="inline-flex min-h-10 w-full items-center justify-center rounded-lg bg-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">Reset</a>
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Filters are not applied while typing or changing selections. Press <strong>Apply</strong> to load maintenance attention, or <strong>Reset</strong> to clear and reload. Desktop/Laptop hardware rules require supported OS and readable memory; Printer/Monitor/UPS use condition and status only.</p>
    </form>

    <script>
        window.pmamsMaintenanceOfficeOptions = @json($officeOptionsByLocation);
        window.pmamsUpdateMaintenanceOffices = function (locationSelect) {
            const officeSelect = document.getElementById('maintenance-attention-office');
            if (!officeSelect) return;

            const selectedLocation = locationSelect?.value || '';
            const officeMap = window.pmamsMaintenanceOfficeOptions || {};
            const offices = selectedLocation
                ? (officeMap[selectedLocation] || [])
                : Object.values(officeMap).flat().filter((value, index, values) => values.indexOf(value) === index).sort();
            const previousOffice = officeSelect.value;

            officeSelect.replaceChildren(new Option('All offices', ''));
            offices.forEach((office) => officeSelect.add(new Option(office, office)));
            officeSelect.value = offices.includes(previousOffice) ? previousOffice : '';
        };
        window.pmamsUpdateMaintenanceOffices(document.getElementById('maintenance-attention-location'));

        // Checkbox groups submit repeated equipment_types[] values reliably on
        // desktop and mobile. This only changes the form state; Apply remains
        // the explicit query trigger.
        (() => {
            const selectAll = document.getElementById('maintenance-attention-equipment-types-all');
            const checkboxes = Array.from(document.querySelectorAll('[data-maintenance-attention-equipment-type]'));
            const summary = document.getElementById('maintenance-attention-equipment-types-summary');
            if (!selectAll || !checkboxes.length) return;

            const sync = () => {
                const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
                selectAll.checked = selected === checkboxes.length;
                selectAll.indeterminate = selected > 0 && selected < checkboxes.length;
                if (summary) {
                    summary.textContent = selected === checkboxes.length
                        ? 'All supported types'
                        : selected > 0
                            ? `${selected} selected`
                            : 'All types (none selected)';
                }
            };

            selectAll.addEventListener('change', () => {
                checkboxes.forEach((checkbox) => { checkbox.checked = selectAll.checked; });
                sync();
            });
            checkboxes.forEach((checkbox) => checkbox.addEventListener('change', sync));
            sync();
        })();
    </script>

    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Equipment recommendations</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Higher scores indicate more signals for an earlier review.</p>
            </div>
            @if($recommendations->total() > 0)
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Showing {{ $recommendations->firstItem() }}–{{ $recommendations->lastItem() }} of {{ $recommendations->total() }}
                </span>
            @endif
        </div>

        @if(!$loaded)
            <div class="rounded-xl border border-dashed border-gray-300 px-4 py-12 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                Apply a filter or press <strong>Reset</strong> to load maintenance attention.
            </div>
        @elseif($recommendations->total() > 0)
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                @foreach($recommendations as $attention)
                    @php
                        $device = $attention['device'];
                        $priorityClasses = match ($attention['priority']) {
                            'Critical' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200',
                            'High' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-200',
                            'Medium' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-200',
                            default => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
                        };
                    @endphp
                    <article class="rounded-xl border border-gray-200 bg-gray-50 p-4 transition hover:border-amber-300 hover:shadow-sm dark:border-gray-700 dark:bg-gray-900/40 dark:hover:border-amber-800">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('admin.devices.index', ['q' => $device->property_number]) }}" class="break-words font-semibold text-blue-700 hover:underline dark:text-blue-300">
                                    {{ $device->property_number ?: 'Unnumbered equipment' }}
                                </a>
                                <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                    {{ $device->type?->name ?? 'Equipment' }} <span aria-hidden="true">·</span> {{ $attention['location'] }}
                                    @if(filled($attention['responsible_name'] ?? null))
                                        <span class="mt-1 block text-indigo-700 dark:text-indigo-300">{{ $attention['responsible_title'] ?: 'Head of Unit' }}: {{ $attention['responsible_name'] }}</span>
                                    @endif
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center justify-end gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $priorityClasses }}">
                                {{ $attention['priority'] }} · {{ $attention['score'] }}/100
                                @if($attention['ai_recommended'] ?? false)
                                    <span class="rounded-full bg-purple-100 px-2.5 py-1 text-xs font-semibold text-purple-800 dark:bg-purple-900/50 dark:text-purple-200" title="The local trained model recommends attention for this equipment">AI recommended</span>
                                @endif
                            </div>
                        </div>
                        <ul class="mt-3 space-y-1.5 text-sm text-gray-700 dark:text-gray-300">
                            @foreach($attention['reasons'] as $reason)
                                <li class="flex gap-2"><span class="text-amber-600 dark:text-amber-400" aria-hidden="true">•</span><span>{{ $reason }}</span></li>
                            @endforeach
                        </ul>
                        <dl class="mt-4 grid grid-cols-1 gap-2 border-t border-gray-200 pt-3 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-400 sm:grid-cols-4">
                            <div>
                                <dt class="font-semibold uppercase tracking-wide">Condition</dt>
                                <dd class="mt-0.5 text-sm text-gray-800 dark:text-gray-200">{{ ucwords(str_replace('_', ' ', (string) ($attention['condition'] ?? ''))) ?: 'Not recorded' }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold uppercase tracking-wide">Status</dt>
                                <dd class="mt-0.5 text-sm text-gray-800 dark:text-gray-200">{{ ucwords(str_replace('_', ' ', (string) ($attention['report_status'] ?? ''))) ?: 'Not recorded' }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold uppercase tracking-wide">Latest checklist remarks</dt>
                                <dd class="mt-0.5 break-words text-sm text-gray-800 dark:text-gray-200">{{ $attention['checklist_remarks'] ?: 'None recorded' }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold uppercase tracking-wide">PM Plan schedule</dt>
                                <dd class="mt-0.5 break-words whitespace-pre-line text-sm text-gray-800 dark:text-gray-200">{{ $attention['pm_schedule'] ?: 'Not scheduled' }}</dd>
                            </div>
                        </dl>
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 pt-3 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <span>Last maintenance: {{ $attention['last_maintenance']?->format('M d, Y') ?? 'Not recorded' }} <span class="ml-2 text-gray-400">Source: {{ $attention['recommendation_source'] ?? 'Laravel rules' }}</span></span>
                            <a href="{{ route('admin.devices.index', ['q' => $device->property_number]) }}" class="font-medium text-blue-700 hover:underline dark:text-blue-300">Review equipment</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="rounded-xl border border-dashed border-gray-300 px-4 py-12 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                No equipment is available for maintenance scoring yet.
            </div>
        @endif

        @if($loaded && $recommendations->total() > 0)
            <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-xs leading-relaxed text-gray-600 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-300">
                <p class="font-semibold text-gray-800 dark:text-gray-100">Priority and Score Explanation</p>
                <p class="mt-1">Critical (75–100): Immediate attention required; High (50–74): Attention should be scheduled soon; Medium (25–49): Monitor and include in the next maintenance cycle; Low (0–24): No urgent issue detected; continue monitoring.</p>
                <p class="mt-1">The score is capped at 100 and is advisory only; existing approval workflows remain required.</p>
            </div>
        @endif

        @if($recommendations->hasPages())
            <div class="mt-6 border-t border-gray-200 pt-4 dark:border-gray-700">
                {{ $recommendations->onEachSide(1)->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
