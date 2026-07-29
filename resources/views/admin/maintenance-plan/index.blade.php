@extends('admin.layouts.app')

@section('title', 'Preventive Maintenance Plan Setup')
@section('page_title', 'Preventive Maintenance Plan Setup')

@section('breadcrumbs')
    <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Dashboard</a>
    <span>/</span>
    <span class="font-medium text-gray-800 dark:text-gray-200">Preventive Maintenance Plan</span>
@endsection

@section('content')
<div class="space-y-6" x-data="maintenanceCompletionModal()">
    <div class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Preventive Maintenance Plan Setup</h1>
            <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                The Super Admin publishes the original schedule. Assigned Admins and Unit Heads can view their targets, propose a temporary reschedule with a reason, and record completion details after all office equipment has been checked.
            </p>
        </div>
        <a href="{{ route('admin.reports.maintenanceSchedule', request()->query()) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-cyan-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-cyan-700">
            View monitoring report
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-900/20 dark:text-emerald-200">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-900/20 dark:text-red-200">
            <ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if(auth()->user()?->isSuperAdmin())
        <section class="rounded-2xl border border-blue-200 bg-blue-50/60 p-5 shadow-sm dark:border-blue-900/60 dark:bg-blue-950/20" x-data="maintenancePlanForm(@js($locations->map(fn ($location) => ['id' => $location->id, 'offices' => $location->offices->map(fn ($office) => ['id' => $office->id, 'name' => $office->name])->values()])->values()))">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Publish original schedule</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Choose one location and optionally select several offices. Leaving offices unchecked creates one location-wide schedule.</p>
            </div>

            <form method="POST" action="{{ route('admin.maintenance-plan.store') }}" class="grid gap-4 lg:grid-cols-2">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Location <span class="text-red-500">*</span></label>
                    <select name="location_id" x-model="locationId" required class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="">Select a registered location</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->code ? $location->code . ' - ' : '' }}{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Assigned Admin / Unit Head</label>
                    <select name="assigned_user_id" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="">All Admins and Unit Heads</option>
                        @foreach($admins as $admin)
                            <option value="{{ $admin->id }}">{{ $admin->name }} ({{ $admin->roleLabel() }})</option>
                        @endforeach
                    </select>
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
                    <label class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Scheduled maintenance date <span class="text-red-500">*</span></label>
                    <input type="date" name="scheduled_date" value="{{ old('scheduled_date', now()->toDateString()) }}" required class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
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
                <p class="text-sm text-gray-500 dark:text-gray-400">Original dates remain unchanged. A temporary override is shown separately.</p>
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
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white" aria-label="Schedule date from">
                <input type="date" name="date_to" value="{{ $dateTo }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white" aria-label="Schedule date to">
                <button class="rounded-lg bg-gray-700 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">Filter</button>
                <a href="{{ route('admin.maintenance-plan.index') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
            </form>
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="min-w-[1180px] w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900/50 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Office / Location</th>
                        <th class="px-4 py-3">Original schedule</th>
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
                            <td class="px-4 py-4">
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $row['office'] }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $schedule->title }}</div>
                                @if($schedule->assignedUser)
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
                                    <span class="font-semibold {{ $row['is_complete'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-300' }}">{{ $row['is_complete'] ? 'All equipment checked' : 'In progress' }}</span>
                                    <span class="text-gray-500 dark:text-gray-400">{{ $row['checked_equipment'] }}/{{ $row['total_equipment'] }}</span>
                                </div>
                                <div class="h-2 w-48 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div class="h-full rounded-full {{ $row['is_complete'] ? 'bg-emerald-500' : 'bg-blue-500' }}" style="width: {{ $row['total_equipment'] ? min(100, round(($row['checked_equipment'] / $row['total_equipment']) * 100)) : 0 }}%"></div>
                                </div>
                                @if($row['completion'])
                                    <div class="mt-2 text-xs text-gray-600 dark:text-gray-300">{{ $row['completion']->person_in_charge }} · {{ $row['completion']->signature }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex min-w-[190px] flex-col gap-2">
                                    <details class="rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-900/60 dark:bg-amber-950/20">
                                        <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-amber-800 dark:text-amber-200">Override schedule</summary>
                                        <form method="POST" action="{{ route('admin.maintenance-plan.override', $schedule) }}" class="space-y-2 border-t border-amber-200 p-3 dark:border-amber-900/60">
                                            @csrf
                                            <input type="date" name="override_date" required class="w-full rounded-lg border border-gray-300 bg-white px-2 py-2 text-xs dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                            <textarea name="reason" rows="2" required maxlength="2000" placeholder="Required reason / remarks" class="w-full rounded-lg border border-gray-300 bg-white px-2 py-2 text-xs dark:border-gray-600 dark:bg-gray-800 dark:text-white"></textarea>
                                            <button class="w-full rounded-lg bg-amber-600 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-700">Save override</button>
                                        </form>
                                    </details>
                                    @if($row['is_complete'])
                                        <button type="button" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700" @click="openCompletion({{ $schedule->id }}, @js(route('admin.maintenance-plan.complete', $schedule)), @js(['actual_date' => $row['completion']?->actual_date?->format('Y-m-d') ?? $row['latest_actual_date'] ?? now()->toDateString(), 'person_in_charge' => $row['completion']?->person_in_charge ?? '', 'signature' => $row['completion']?->signature ?? '', 'remarks' => $row['completion']?->remarks ?? '']))">
                                            {{ $row['completion'] ? 'Edit completion details' : 'Record completion details' }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">No preventive maintenance schedules match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/70 p-4" role="dialog" aria-modal="true" @keydown.escape.window="open = false">
        <div class="w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-700 dark:bg-gray-800" @click.outside="open = false">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Record office completion</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">All equipment in this target has a checklist record. Add the sign-off details for the monitoring report.</p>
                </div>
                <button type="button" @click="open = false" class="rounded-lg px-2 py-1 text-2xl leading-none text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Close">&times;</button>
            </div>
            <form method="POST" :action="action" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Actual date</label>
                    <input type="date" name="actual_date" x-model="form.actual_date" required class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Person/s in charge</label>
                    <input type="text" name="person_in_charge" x-model="form.person_in_charge" required maxlength="255" placeholder="Name of person/s in charge" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Signature</label>
                    <input type="text" name="signature" x-model="form.signature" required maxlength="255" placeholder="Typed signature or signatory name" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Remarks</label>
                    <textarea name="remarks" x-model="form.remarks" rows="3" maxlength="2000" placeholder="Optional monitoring remarks" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="open = false" class="rounded-xl bg-gray-200 px-4 py-3 text-sm font-semibold text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600">Cancel</button>
                    <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700">Save completion</button>
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
            action: '',
            form: { actual_date: '', person_in_charge: '', signature: '', remarks: '' },
            openCompletion(id, action, form) {
                this.action = action;
                this.form = { ...this.form, ...(form || {}) };
                this.open = true;
            },
        };
    }
</script>
@endsection
