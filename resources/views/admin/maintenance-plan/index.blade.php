@extends('admin.layouts.app')

@section('title', 'PM Plan')
@section('page_title', 'PM Plan')

@section('breadcrumbs')
    <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Dashboard</a>
    <span>/</span>
    <span class="font-medium text-gray-800 dark:text-gray-200">Preventive Maintenance Plan</span>
@endsection

@section('content')
@php
    $pmPlanUser = auth()->user();
    $canAddPmPlan = $pmPlanUser?->canAction('maintenance_plan', 'add') ?? false;
    $canEditPmPlan = $pmPlanUser?->canAction('maintenance_plan', 'edit') ?? false;
    $canDeletePmPlan = $pmPlanUser?->canAction('maintenance_plan', 'delete') ?? false;
@endphp
<div class="space-y-6" x-data="maintenanceCompletionModal()">
    <div class="flex flex-col gap-4 rounded-2xl sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                The Super Admin and Custodian publishes the approved schedule. Assigned Admins and Super Admins can view their targets, propose a temporary reschedule with a reason, and record completion details after all office equipment has been checked.
            </p>
        </div>
        <a href="{{ route('admin.reports.maintenanceSchedule', request()->query()) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-cyan-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-cyan-700">
            View monitoring report
        </a>
    </div>

    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-900/20 dark:text-red-200">
            <ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if($canAddPmPlan)
        <section class="rounded-2xl border border-blue-200 bg-blue-50/60 p-5 shadow-sm dark:border-blue-900/60 dark:bg-blue-950/20" x-data="maintenancePlanForm(@js($locations->map(fn ($location) => ['id' => $location->id, 'offices' => $location->offices->map(fn ($office) => ['id' => $office->id, 'name' => $office->name])->values()])->values()))">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Publish approved schedule</h2>
              
            </div>

            <form method="POST" action="{{ route('admin.maintenance-plan.store') }}" data-spa-form="true" class="grid gap-4 lg:grid-cols-2">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Location <span class="text-red-500">*</span></label>
                    <select name="location_id" x-model="locationId" required class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="">Select a registered location</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->code ? $location->code . ' - ' : '' }}{{ $location->name }}</option>
                        @endforeach
                    </select>
                      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Choose one location and optionally select several offices. Leaving offices unchecked creates one location-wide schedule.</p>
                </div>
                <div>
                    @php($selectedAssignedUserIds = collect(old('assigned_user_ids', old('assigned_user_id') ? [old('assigned_user_id')] : []))->map(fn ($id) => (int) $id)->all())
                    <label class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Assigned Admin / Super Admin <span class="font-normal text-gray-500">(select one or more)</span></label>
                    <select name="assigned_user_ids[]" multiple size="4" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        @foreach($admins as $admin)
                            <option value="{{ $admin->id }}" @selected(in_array((int) $admin->id, $selectedAssignedUserIds, true))>{{ $admin->name }} ({{ $admin->roleLabel() }})</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Admin and Super Admin accounts are assignable. Hold Ctrl (Windows) or Command (Mac) to select multiple users.</p>
                </div>
                <div class="lg:col-span-2">
                    <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">Offices <span class="font-normal text-gray-500">(optional)</span></label>
                    <div class="grid gap-2 rounded-xl border border-gray-200 bg-white p-3 sm:grid-cols-2 lg:grid-cols-3 dark:border-gray-700 dark:bg-gray-800" x-show="locationId && availableOffices.length" x-cloak>
                        <template x-for="office in availableOffices" :key="office.id">
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
                                <input type="checkbox" name="office_ids[]" :value="office.id" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span x-text="office.name"></span>
                            </label>
                        </template>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400" x-show="locationId && !availableOffices.length" x-cloak>No registered offices are available for this location; the schedule will apply to the location.</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Maintenance month / range <span class="text-red-500">*</span></label>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <input type="month" name="schedule_month_from" value="{{ old('schedule_month_from', now()->format('Y-m')) }}" required aria-label="Starting month" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <input type="month" name="schedule_month_to" value="{{ old('schedule_month_to', old('schedule_month_from', now()->format('Y-m'))) }}" aria-label="Ending month" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Use the same month for a single-month plan, or choose an ending month for a range.</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Schedule title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" value="{{ old('title', 'Preventive Maintenance') }}" required maxlength="150" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                </div>
                <div class="lg:col-span-2">
                    <label class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Planning notes</label>
                    <textarea name="notes" rows="2" maxlength="2000" placeholder="Optional instructions for the assigned checker" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">{{ old('notes') }}</textarea>
                </div>
                <div class="lg:col-span-2 flex justify-end">
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">Publish schedule</button>
                </div>
            </form>
        </section>
    @endif

    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Published schedules</h2>
        
            </div>
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <select name="location_id" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">All locations</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected($selectedLocationId === $location->id)>{{ $location->code ? $location->code . ' - ' : '' }}{{ $location->name }}</option>
                    @endforeach
                </select>
                <select name="office_id" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">All offices</option>
                    @foreach($locations as $location)
                        <optgroup label="{{ $location->code ? $location->code . ' - ' : '' }}{{ $location->name }}">
                            @foreach($location->offices as $office)
                                <option value="{{ $office->id }}" @selected($selectedOfficeId === $office->id)>{{ $office->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <input type="month" name="month_from" value="{{ $monthFrom }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white" aria-label="Schedule month from">
                <input type="month" name="month_to" value="{{ $monthTo }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white" aria-label="Schedule month to">
                <button class="inline-flex h-11 items-center justify-center whitespace-nowrap rounded-lg bg-gray-700 px-3 text-sm font-semibold text-white hover:bg-gray-800">Filter</button>
                <a href="{{ route('admin.maintenance-plan.index') }}" class="inline-flex h-11 items-center justify-center whitespace-nowrap rounded-lg border border-gray-300 px-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
            </form>
        </div>

        @if($canDeletePmPlan && $schedules->total() > 0)
            <div class="mb-4 flex items-center justify-between rounded-xl bg-transparent px-5 py-3 shadow-none">
                <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                    <input id="pm-plan-select-all-matching" type="checkbox" onchange="togglePmPlanAllMatching(this.checked)" class="h-5 w-5 rounded border-gray-300 text-red-600 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-700" aria-label="Select all PM Plans matching the current filters">
                    Select all PM Plans matching the current filters
                </label>
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ number_format($schedules->total()) }} matching</span>
            </div>

            <form id="pm-plan-bulk-delete-form" method="POST" action="{{ route('admin.maintenance-plan.bulkDestroy') }}" class="mb-4 hidden flex flex-wrap items-center gap-2 rounded-xl border border-red-200 bg-red-50 p-3 dark:border-red-900/60 dark:bg-red-950/20" onsubmit="return submitPmPlanBulkDelete(event)">
                @csrf
                <input type="hidden" name="select_all" id="pm-plan-delete-select-all" value="0">
                <input type="hidden" name="location_id" value="{{ $selectedLocationId }}">
                <input type="hidden" name="office_id" value="{{ $selectedOfficeId }}">
                <input type="hidden" name="month_from" value="{{ $monthFrom }}">
                <input type="hidden" name="month_to" value="{{ $monthTo }}">
                <label class="inline-flex items-center gap-2 text-xs font-semibold text-red-800 dark:text-red-200">
                    <input type="checkbox" data-pm-plan-page-master onchange="togglePmPlanPageSelection(this)" class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500">
                    Select page
                </label>
                <button type="submit" class="rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700">Delete selected</button>
                <span id="pm-plan-selection-count" class="text-xs text-red-800 dark:text-red-200"><strong>0</strong> selected</span>
                <span class="text-xs text-red-700 dark:text-red-300">Deletion moves plans to the recycle bin; assignments and completion history are retained.</span>
            </form>
        @endif

        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="min-w-[1180px] w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900/50 dark:text-gray-400">
                    <tr>
                        @if($canDeletePmPlan)
                            <th class="w-12 px-4 py-3 text-center">
                            </th>
                        @endif
                        <th class="px-4 py-3">Office / Location</th>
                        <th class="px-4 py-3">Approved schedule</th>
                        <th class="px-4 py-3 text-amber-700 dark:text-amber-300">Override schedule</th>
                        <th class="px-4 py-3">Actual maintenance</th>
                        <th class="px-4 py-3">Completion</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($schedules as $row)
                        @php($schedule = $row['schedule'])
                        <tr class="align-top hover:bg-gray-50 dark:hover:bg-gray-900/30">
                            @if($canDeletePmPlan)
                                <td class="px-4 py-4 text-center">
                                    <input type="checkbox" value="{{ $schedule->id }}" data-pm-plan-checkbox onchange="syncPmPlanSelection()" aria-label="Select PM Plan {{ $row['office'] }}" class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                </td>
                            @endif
                            <td class="px-4 py-4">
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $row['office'] }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $schedule->title }}</div>
                                @php($assignedNames = $schedule->assignedUsers->pluck('name')->filter()->values())
                                @if($assignedNames->isNotEmpty())
                                    <div class="mt-2 text-xs text-blue-700 dark:text-blue-300">Assigned: {{ $assignedNames->join(', ') }}</div>
                                @elseif($schedule->assignedUser)
                                    <div class="mt-2 text-xs text-blue-700 dark:text-blue-300">Assigned: {{ $schedule->assignedUser->name }}</div>
                                @else
                                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Available to all Admins</div>
                                @endif
                            </td>
                            <td class="px-4 py-4 font-semibold text-gray-800 dark:text-gray-200">
                                {{ $row['original_schedule'] }}
                                @if($schedule->notes)<div class="mt-1 max-w-xs text-xs font-normal text-gray-500 dark:text-gray-400">{{ $schedule->notes }}</div>@endif
                            </td>
                            <td class="px-4 py-4">
                                @if($row['override_schedule'])
                                    <div class="font-semibold text-amber-700 dark:text-amber-300">{{ $row['override_schedule'] }}</div>
                                    <div class="mt-1 max-w-xs text-xs text-gray-600 dark:text-gray-300">{{ $row['override_reason'] }}</div>
                                @else
                                    <span class="text-xs text-gray-400">No override</span>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                @if($row['actual_date'])
                                    <div class="font-semibold text-gray-900 dark:text-white">{{ $row['actual_date'] }}</div>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">Pending</span>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <div class="mb-1 flex items-center justify-between gap-3 text-xs">
                                    @if((int) $row['checked_equipment'] > 0)
                                        <span class="font-semibold {{ $row['is_complete'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-300' }}">{{ $row['is_complete'] ? 'All equipment checked' : 'In progress' }}</span>
                                    @else
                                        <span aria-hidden="true"></span>
                                    @endif
                                    <span class="text-gray-500 dark:text-gray-400">{{ $row['checked_equipment'] }}/{{ $row['total_equipment'] }}</span>
                                </div>
                                <div class="h-2 w-48 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div class="h-full rounded-full {{ $row['is_complete'] ? 'bg-emerald-500' : 'bg-blue-500' }}" style="width: {{ $row['total_equipment'] ? min(100, round(($row['checked_equipment'] / $row['total_equipment']) * 100)) : 0 }}%"></div>
                                </div>
                                @if((int) $row['checked_equipment'] > 0 && $row['completion'])
                                    <div class="mt-2 text-xs text-gray-600 dark:text-gray-300">Checked by: {{ $row['person_in_charge'] ?: 'Not recorded' }}<br>Signed by: {{ $row['completion']->signer_name ?: 'Not recorded' }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex min-w-[220px] flex-wrap items-center gap-2">
                                    @if($canEditPmPlan || $canDeletePmPlan)
                                        @if($canEditPmPlan)
                                        <button type="button" data-open-modal="pm-plan-edit-{{ $schedule->id }}" title="Edit published schedule" aria-label="Edit published schedule" class="action-icon-button group relative inline-flex h-9 w-9 min-h-0 min-w-0 shrink-0 items-center justify-center rounded-lg bg-blue-600 p-0 text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-white dark:bg-blue-500 dark:hover:bg-blue-600 dark:focus:ring-offset-gray-900">
                                             <x-action-icon-symbol icon="edit" />
                                             <span class="sr-only">Edit published schedule</span>
                                             <span class="pointer-events-none absolute bottom-full left-1/2 z-[70] mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[11px] font-medium text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100 group-focus-within:opacity-100 dark:bg-gray-100 dark:text-gray-900" role="tooltip">Edit published schedule</span>
                                         </button>
                                        @endif
                                        @if($canDeletePmPlan)
                                        <form method="POST" action="{{ route('admin.maintenance-plan.destroy', $schedule) }}" data-spa-form="true" class="inline-flex border-0 bg-transparent p-0" onsubmit="return confirm('Move this published PM schedule to the recycle bin? Its schedule, assignments, override, and completion details can be restored later.')">
                                            @csrf
                                            @method('DELETE')
                                            <x-action-icon type="submit" icon="recycle" variant="red" label="Move PM plan to recycle bin" />
                                        </form>
                                        @endif
                                    @endif
                                    @if($canEditPmPlan)
                                    <button type="button" data-open-modal="pm-plan-override-{{ $schedule->id }}" title="Override schedule" aria-label="Override schedule" class="action-icon-button group relative inline-flex h-9 w-9 min-h-0 min-w-0 shrink-0 items-center justify-center rounded-lg bg-amber-600 p-0 text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-white dark:bg-amber-500 dark:hover:bg-amber-600 dark:focus:ring-offset-gray-900">
                                            <x-action-icon-symbol icon="calendar" />
                                            <span class="sr-only">Override schedule</span>
                                            <span class="pointer-events-none absolute bottom-full left-1/2 z-[70] mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[11px] font-medium text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100 group-focus-within:opacity-100 dark:bg-gray-100 dark:text-gray-900" role="tooltip">Override schedule</span>
                                    </button>
                                    @if($row['is_complete'])
                                        <button type="button" title="{{ $row['completion'] ? 'Edit completion details' : 'Record completion details' }}" aria-label="{{ $row['completion'] ? 'Edit completion details' : 'Record completion details' }}" class="action-icon-button group relative inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-600 text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-white dark:bg-emerald-500 dark:hover:bg-emerald-600 dark:focus:ring-offset-gray-900" @click="openCompletion({{ $schedule->id }}, @js(route('admin.maintenance-plan.complete', $schedule)), @js(['actual_date' => $row['completion']?->actual_date?->format('Y-m-d') ?? $row['latest_actual_date'] ?? now()->toDateString(), 'person_in_charge' => $row['person_in_charge'] ?? '', 'signer_name' => $row['completion']?->signer_name ?? '', 'signature_data' => $row['completion']?->signature_data ?? '', 'remarks' => $row['completion']?->remarks ?? '']))">
                                            <x-action-icon-symbol icon="clipboard" />
                                            <span class="sr-only">{{ $row['completion'] ? 'Edit completion details' : 'Record completion details' }}</span>
                                            <span class="pointer-events-none absolute bottom-full left-1/2 z-[70] mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[11px] font-medium text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100 group-focus:opacity-100 dark:bg-gray-100 dark:text-gray-900" role="tooltip">{{ $row['completion'] ? 'Edit completion details' : 'Record completion details' }}</span>
                                        </button>
                                    @endif
                                    @endif
                                </div>
                                 @if($canEditPmPlan)
                                    @php($scheduleAssignedIds = $schedule->assignedUsers->pluck('id')->merge([$schedule->assigned_user_id])->filter()->map(fn ($id) => (int) $id)->unique()->values()->all())
                                    <div id="pm-plan-edit-{{ $schedule->id }}" role="dialog" aria-modal="true" style="display:none" class="fixed inset-0 z-[80] overflow-y-auto bg-gray-950/70 p-4">
                                        <div class="flex min-h-full items-center justify-center">
                                            <div class="w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-700 dark:bg-gray-800">
                                                <div class="mb-4 flex items-start justify-between gap-4">
                                                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Edit published schedule</h2>
                                                    <button type="button" data-native-modal-close="pm-plan-edit-{{ $schedule->id }}" class="rounded-lg px-2 py-1 text-2xl leading-none text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Close">&times;</button>
                                                </div>
                                                <form method="POST" action="{{ route('admin.maintenance-plan.update', $schedule) }}" data-spa-form="true" class="space-y-3">
                                                    @csrf @method('PUT')
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <input type="month" name="schedule_month_from" value="{{ optional($schedule->schedule_month_from ?: $schedule->scheduled_date)->format('Y-m') }}" required class="w-full rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                                        <input type="month" name="schedule_month_to" value="{{ optional($schedule->schedule_month_to ?: $schedule->scheduled_date)->format('Y-m') }}" class="w-full rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                                    </div>
                                                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">Assigned Admin / Super Admin</label>
                                                    <select name="assigned_user_ids[]" multiple size="4" class="w-full rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                                        @foreach($admins as $admin)<option value="{{ $admin->id }}" @selected(in_array((int) $admin->id, $scheduleAssignedIds, true))>{{ $admin->name }} ({{ $admin->roleLabel() }})</option>@endforeach
                                                    </select>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">Ctrl/Command-click to select multiple. Clear all to allow every Admin or Super Admin.</p>
                                                    <input type="text" name="title" value="{{ $schedule->title }}" required maxlength="150" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Schedule title">
                                                    <textarea name="notes" rows="3" maxlength="2000" placeholder="Planning notes" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">{{ $schedule->notes }}</textarea>
                                                    <div class="flex justify-end gap-2 pt-2">
                                                        <button type="button" data-native-modal-close="pm-plan-edit-{{ $schedule->id }}" class="rounded-lg bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600">Cancel</button>
                                                        <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save schedule</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                <div id="pm-plan-override-{{ $schedule->id }}" role="dialog" aria-modal="true" style="display:none" class="fixed inset-0 z-[80] overflow-y-auto bg-gray-950/70 p-4">
                                    <div class="flex min-h-full items-center justify-center">
                                        <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-700 dark:bg-gray-800">
                                            <div class="mb-4 flex items-start justify-between gap-4">
                                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Override schedule</h2>
                                                <button type="button" data-native-modal-close="pm-plan-override-{{ $schedule->id }}" class="rounded-lg px-2 py-1 text-2xl leading-none text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Close">&times;</button>
                                            </div>
                                            <form method="POST" action="{{ route('admin.maintenance-plan.override', $schedule) }}" data-spa-form="true" class="space-y-3">
                                                @csrf
                                                <input type="date" name="override_date" value="{{ optional($schedule->latestOverride?->override_date)->format('Y-m-d') }}" required class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" aria-label="Override date">
                                                <textarea name="reason" rows="4" required maxlength="2000" placeholder="Required reason / remarks" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">{{ $row['override_reason'] ?? '' }}</textarea>
                                                <div class="flex justify-end gap-2 pt-2">
                                                    <button type="button" data-native-modal-close="pm-plan-override-{{ $schedule->id }}" class="rounded-lg bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600">Cancel</button>
                                                    <button class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">Save override</button>
                                                </div>
                                            </form>
                                            @if(auth()->user()?->isSuperAdmin() && $row['override_schedule'])
                                                <form method="POST" action="{{ route('admin.maintenance-plan.override.reset', $schedule) }}" data-spa-form="true" class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700" onsubmit="return confirm('Remove this temporary override and restore the approved published schedule?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="w-full rounded-lg bg-gray-600 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 dark:bg-gray-500 dark:hover:bg-gray-400">Reset / remove override</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canDeletePmPlan ? 7 : 6 }}" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">No preventive maintenance schedules match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="sm:flex-row sm:items-center sm:justify-between">
        <span class="text-gray-600 dark:text-gray-300">
            @if($schedules->total() > 0)
              <!--  Showing {{ $schedules->firstItem() }}–{{ $schedules->lastItem() }} of {{ $schedules->total() }} published schedules --> 
            @else
                No published schedules
            @endif
        </span>
        @if($schedules->hasPages())
            {{ $schedules->onEachSide(1)->links() }}
        @else
            <span class="text-gray-500 dark:text-gray-400">Page 1 of 1</span>
        @endif
    </div>

    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-950/70 p-4 sm:items-center" role="dialog" aria-modal="true" @keydown.escape.window="open = false">
        <div class="my-auto max-h-[calc(100vh-2rem)] w-full max-w-lg overflow-y-auto overscroll-contain rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-700 dark:bg-gray-800" @click.outside="open = false">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Record office completion</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">All equipment in this target has a checklist record. Add the sign-off details for the monitoring report.</p>
                </div>
                <button type="button" @click="open = false" class="rounded-lg px-2 py-1 text-2xl leading-none text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Close">&times;</button>
            </div>
            <form method="POST" :action="action" data-spa-form="true" class="mt-5 space-y-4">
                @csrf
                <input type="hidden" name="privacy_consent" :value="consentGiven ? '1' : '0'">
                <div x-show="!consentGiven" x-cloak class="space-y-4 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-100">
                    <h3 class="font-semibold">Data Privacy consent</h3>
                    <p>Under the Data Privacy Act of 2012 (Republic Act No. 10173), I consent to the collection and processing of my name and signature for preventive-maintenance monitoring, reporting, and records management. My information shall be handled securely and used only for authorized purposes.</p>
                    <label class="flex items-start gap-2">
                        <input type="checkbox" x-model="consentChecked" class="mt-0.5 h-4 w-4 rounded border-blue-400 text-blue-600 focus:ring-blue-500">
                        <span>I have read and consent to proceed.</span>
                    </label>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-xl bg-gray-200 px-4 py-3 text-sm font-semibold text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-100">Cancel</button>
                        <button type="button" @click="consentGiven = true" :disabled="!consentChecked" class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">Proceed</button>
                    </div>
                </div>
                <div x-show="consentGiven" x-cloak class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Actual date</label>
                    <input type="date" name="actual_date" x-model="form.actual_date" required class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Person/s in charge</label>
                    <div class="rounded-xl border border-blue-200 bg-blue-50 px-3 py-3 text-sm text-blue-900 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-100" x-text="form.person_in_charge || 'Names are taken from the users who marked the equipment checked.'"></div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Name of signer</label>
                    <input type="text" name="signer_name" x-model="form.signer_name" required maxlength="255" placeholder="Full name of the person signing" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Signature <span class="font-normal text-gray-500">(optional)</span></label>
                    <input type="hidden" name="signature_data" x-model="form.signature_data">
                    <div class="rounded-xl border border-dashed border-gray-400 bg-gray-50 p-2 dark:border-gray-600 dark:bg-gray-900">
                        <canvas id="completion-signature-pad" width="520" height="170" class="h-36 w-full touch-none rounded-lg bg-white dark:bg-gray-100" aria-label="Draw signature"></canvas>
                        <div class="mt-2 flex items-center justify-between gap-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Sign with your mouse, finger, or stylus.</span>
                            <button type="button" @click="clearSignature()" class="rounded-lg bg-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-100">Clear</button>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Remarks</label>
                    <textarea name="remarks" x-model="form.remarks" rows="3" maxlength="2000" placeholder="Optional monitoring remarks" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="open = false" class="rounded-xl bg-gray-200 px-4 py-3 text-sm font-semibold text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600">Cancel</button>
                    <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700">Save completion</button>
                </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function maintenancePlanForm(locations) {
        return {
            locations,
            locationId: @js((string) old('location_id', '')),
            get availableOffices() {
                return this.locations.find((location) => String(location.id) === String(this.locationId))?.offices || [];
            },
        };
    }

    function maintenanceCompletionModal() {
        return {
            open: false,
            consentChecked: false,
            consentGiven: false,
            action: '',
            form: { actual_date: '', person_in_charge: '', signer_name: '', signature_data: '', remarks: '' },
            pad: null,
            ctx: null,
            drawing: false,
            init() {
                this.$nextTick(() => {
                    const canvas = document.getElementById('completion-signature-pad');
                    if (!canvas) return;
                    this.pad = canvas;
                    this.ctx = canvas.getContext('2d');
                    this.ctx.lineWidth = 2;
                    this.ctx.lineCap = 'round';
                    this.ctx.strokeStyle = '#111827';
                    canvas.addEventListener('pointerdown', (event) => { this.drawing = true; canvas.setPointerCapture(event.pointerId); this.ctx.beginPath(); this.ctx.moveTo(...this.point(event)); });
                    canvas.addEventListener('pointermove', (event) => { if (!this.drawing) return; this.ctx.lineTo(...this.point(event)); this.ctx.stroke(); });
                    ['pointerup', 'pointercancel', 'pointerleave'].forEach((name) => canvas.addEventListener(name, () => { if (this.drawing) this.form.signature_data = this.pad.toDataURL('image/png'); this.drawing = false; }));
                });
            },
            point(event) { const rect = this.pad.getBoundingClientRect(); return [(event.clientX - rect.left) * (this.pad.width / rect.width), (event.clientY - rect.top) * (this.pad.height / rect.height)]; },
            clearSignature() { if (this.ctx) this.ctx.clearRect(0, 0, this.pad.width, this.pad.height); this.form.signature_data = ''; },
            openCompletion(id, action, form) {
                this.action = action;
                this.form = { ...this.form, ...(form || {}) };
                this.consentChecked = false;
                this.consentGiven = false;
                this.open = true;
                this.$nextTick(() => {
                    if (!this.ctx) return;
                    this.ctx.clearRect(0, 0, this.pad.width, this.pad.height);
                    if (this.form.signature_data) { const image = new Image(); image.onload = () => this.ctx.drawImage(image, 0, 0, this.pad.width, this.pad.height); image.src = this.form.signature_data; }
                });
            },
        };
    }

    function pmPlanSelectionBoxes() {
        return Array.from(document.querySelectorAll('[data-pm-plan-checkbox]'));
    }

    function syncPmPlanSelection() {
        const boxes = pmPlanSelectionBoxes();
        const selected = boxes.filter((box) => box.checked).length;
        const count = document.getElementById('pm-plan-selection-count');
        const allMatching = document.getElementById('pm-plan-select-all-matching');
        const actionBar = document.getElementById('pm-plan-bulk-delete-form');

        if (allMatching?.checked && boxes.some((box) => !box.checked)) {
            allMatching.checked = false;
            document.getElementById('pm-plan-delete-select-all').value = '0';
        }
        if (count) count.innerHTML = `<strong>${allMatching?.checked ? '{{ number_format($schedules->total()) }}' : selected}</strong> selected${allMatching?.checked ? ' across filtered results' : ''}`;
        if (actionBar) actionBar.classList.toggle('hidden', selected === 0 && !allMatching?.checked);

        document.querySelectorAll('[data-pm-plan-page-master]').forEach((master) => {
            master.checked = boxes.length > 0 && selected === boxes.length;
            master.indeterminate = selected > 0 && selected < boxes.length;
        });
    }

    function togglePmPlanPageSelection(master) {
        const allMatching = document.getElementById('pm-plan-select-all-matching');
        if (allMatching) allMatching.checked = false;
        document.getElementById('pm-plan-delete-select-all').value = '0';
        pmPlanSelectionBoxes().forEach((box) => { box.checked = master.checked; });
        syncPmPlanSelection();
    }

    function togglePmPlanAllMatching(checked) {
        document.getElementById('pm-plan-delete-select-all').value = checked ? '1' : '0';
        pmPlanSelectionBoxes().forEach((box) => { box.checked = checked; });
        document.querySelectorAll('[data-pm-plan-page-master]').forEach((master) => {
            master.checked = checked;
            master.indeterminate = false;
        });
        syncPmPlanSelection();
    }

    function preparePmPlanBulkForm(selectAll) {
        const form = document.getElementById('pm-plan-bulk-delete-form');
        if (!form) return null;
        form.querySelectorAll('input[name="schedule_ids[]"]').forEach((input) => input.remove());
        form.querySelector('#pm-plan-delete-select-all').value = selectAll ? '1' : '0';
        if (!selectAll) {
            pmPlanSelectionBoxes().filter((box) => box.checked).forEach((box) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'schedule_ids[]';
                input.value = box.value;
                form.appendChild(input);
            });
        }
        return form;
    }

    function submitPmPlanBulkDelete(event) {
        event.preventDefault();
        const allMatching = document.getElementById('pm-plan-select-all-matching')?.checked === true;
        const selected = pmPlanSelectionBoxes().filter((box) => box.checked);
        if (!allMatching && !selected.length) {
            window.alert('Select at least one PM Plan first.');
            return false;
        }
        if (!window.confirm(allMatching
            ? 'Move every PM Plan matching the current filters, including plans on other pages, to the recycle bin?'
            : `Move ${selected.length} selected PM Plan(s) to the recycle bin?`)) return false;
        const form = preparePmPlanBulkForm(allMatching);
        if (form) form.submit();
        return false;
    }

    function submitPmPlanBulkDeleteAll() {
        if (!window.confirm('Move every PM Plan matching the current filters, including plans on other pages, to the recycle bin?')) return;
        const form = preparePmPlanBulkForm(true);
        if (form) form.submit();
    }
</script>
@endsection
