<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\DeviceMaintenanceRecord;
use App\Models\Location;
use App\Models\MaintenancePlanCompletion;
use App\Models\MaintenancePlanSchedule;
use App\Models\Office;
use App\Models\SystemSetting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PreventiveMaintenancePlanController extends Controller
{
    public function index(Request $request)
    {
        $locationId = $request->integer('location_id') ?: null;
        $officeId = $request->integer('office_id') ?: null;
        $monthFrom = $request->query('month_from');
        $monthTo = $request->query('month_to');
        $monthFromStart = $this->monthStartOrNull($monthFrom);
        $monthToStart = $this->monthStartOrNull($monthTo);

        $schedules = $this->visibleSchedules($request)
            ->when($locationId, fn ($query) => $query->where('location_id', $locationId))
            ->when($officeId, fn ($query) => $query->where('office_id', $officeId))
            ->when($monthFromStart, fn ($query) => $query->whereDate('schedule_month_to', '>=', $monthFromStart))
            ->when($monthToStart, fn ($query) => $query->whereDate('schedule_month_from', '<=', $monthToStart))
            ->with([
                'location:id,name,code',
                'office:id,location_id,name',
                'assignedUser:id,name,email,role',
                'assignedUsers:id,name,email,role',
                'latestOverride',
                'completion',
            ])
            ->orderByDesc('scheduled_date')
            ->orderBy('location_id')
            ->orderBy('office_id')
            ->paginate(10)
            ->withQueryString();

        $schedules->setCollection(
            $schedules->getCollection()->map(fn (MaintenancePlanSchedule $schedule) => $this->scheduleRow($schedule))
        );

        return view('admin.maintenance-plan.index', [
            'schedules' => $schedules,
            'locations' => Location::with(['offices:id,location_id,name'])->orderBy('name')->get(),
            'admins' => User::query()
                ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_UNIT_HEAD])
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role']),
            'selectedLocationId' => $locationId,
            'selectedOfficeId' => $officeId,
            'monthFrom' => $monthFrom,
            'monthTo' => $monthTo,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'office_ids' => ['nullable', 'array'],
            'office_ids.*' => ['integer', 'distinct', 'exists:offices,id'],
            'assigned_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_UNIT_HEAD])
                    ->whereNull('deleted_at')),
            ],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_UNIT_HEAD])
                    ->whereNull('deleted_at')),
            ],
            'schedule_month_from' => ['required', 'date_format:Y-m'],
            'schedule_month_to' => ['nullable', 'date_format:Y-m'],
            'title' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $assignedUserIds = $this->assignedUserIds($data);
        [$monthFrom, $monthTo] = $this->monthRange($data['schedule_month_from'], $data['schedule_month_to'] ?? null);
        if ($monthTo->lt($monthFrom)) {
            return back()->withInput()->withErrors(['schedule_month_to' => 'The ending month must be the same as or after the starting month.']);
        }

        $location = Location::findOrFail($data['location_id']);
        $officeIds = collect($data['office_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $offices = Office::query()
            ->where('location_id', $location->id)
            ->whereIn('id', $officeIds)
            ->orderBy('name')
            ->get();

        if ($officeIds->isNotEmpty() && $offices->count() !== $officeIds->count()) {
            return back()->withInput()->withErrors(['office_ids' => 'Select only offices belonging to the chosen location.']);
        }

        $targets = $offices->isEmpty() ? collect([null]) : $offices;
        $created = 0;
        $duplicates = 0;

        DB::transaction(function () use ($targets, $data, $assignedUserIds, $location, $monthFrom, $monthTo, &$created, &$duplicates, $request) {
            foreach ($targets as $office) {
                // Include soft-deleted schedules in duplicate detection. This
                // prevents publishing a second hidden copy that would later
                // collide with the original when it is restored from the
                // recycle bin.
                $duplicateExists = MaintenancePlanSchedule::withTrashed()
                    ->where('location_id', $location->id)
                    ->where('office_id', $office?->id)
                    ->whereDate('schedule_month_from', $monthFrom->toDateString())
                    ->whereDate('schedule_month_to', $monthTo->toDateString())
                    ->exists();

                if ($duplicateExists) {
                    $duplicates++;
                    continue;
                }

                $schedule = MaintenancePlanSchedule::create([
                    'location_id' => $location->id,
                    'office_id' => $office?->id,
                    // Keep the first assignment in the legacy column for
                    // existing integrations; the pivot stores the full list.
                    'assigned_user_id' => $assignedUserIds[0] ?? null,
                    'created_by' => $request->user()->id,
                    'scheduled_date' => $monthFrom->toDateString(),
                    'schedule_month_from' => $monthFrom->toDateString(),
                    'schedule_month_to' => $monthTo->toDateString(),
                    'title' => $data['title'],
                    'notes' => $data['notes'] ?? null,
                ]);
                $schedule->assignedUsers()->sync($assignedUserIds);

                ActivityLog::record(
                    'created',
                    'Created preventive maintenance schedule for ' . $this->scheduleTargetLabel($schedule->fresh(['location', 'office'])),
                    $schedule,
                    ActivityLog::makePayload([
                        'location' => $location->name,
                        'office' => $office?->name,
                        'schedule_month_from' => $monthFrom->format('Y-m'),
                        'schedule_month_to' => $monthTo->format('Y-m'),
                        'assigned_user_ids' => $assignedUserIds,
                        'title' => $data['title'],
                    ])
                );
                $created++;
            }
        });

        $message = $created > 0
            ? "PM Plan published successfully. Created {$created} preventive maintenance schedule(s)."
            : 'No new schedule was published.';
        $warning = $duplicates > 0
            ? ($created > 0
                ? "Duplicate detection: {$duplicates} schedule(s) were skipped because the selected location/office and month range already exist."
                : 'Duplicate PM Plan detected. No new schedule was published because the selected location/office and month range already exist.')
            : null;

        if ($request->header('X-SPA-Request') === '1') {
            if ($created > 0) {
                $request->session()->flash('success', $message);
            }
            if ($warning) {
                $request->session()->flash('warning', $warning);
            }

            return response()->json([
                'redirect' => route('admin.maintenance-plan.index'),
                'success' => $created > 0 ? $message : null,
                'warning' => $warning,
            ]);
        }

        $redirect = redirect()->route('admin.maintenance-plan.index')->with('success', $message);
        if ($warning) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    public function update(Request $request, MaintenancePlanSchedule $schedule)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'schedule_month_from' => ['required', 'date_format:Y-m'],
            'schedule_month_to' => ['nullable', 'date_format:Y-m'],
            'assigned_user_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_UNIT_HEAD])
                    ->whereNull('deleted_at')),
            ],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_UNIT_HEAD])
                    ->whereNull('deleted_at')),
            ],
            'title' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $assignedUserIds = $this->assignedUserIds($data);
        [$monthFrom, $monthTo] = $this->monthRange($data['schedule_month_from'], $data['schedule_month_to'] ?? null);
        if ($monthTo->lt($monthFrom)) {
            return back()->withInput()->withErrors(['schedule_month_to' => 'The ending month must be the same as or after the starting month.']);
        }

        $schedule->update([
            'scheduled_date' => $monthFrom->toDateString(),
            'schedule_month_from' => $monthFrom->toDateString(),
            'schedule_month_to' => $monthTo->toDateString(),
            'assigned_user_id' => $assignedUserIds[0] ?? null,
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
        ]);
        $schedule->assignedUsers()->sync($assignedUserIds);

        ActivityLog::record('updated', 'Edited preventive maintenance schedule for ' . $this->scheduleTargetLabel($schedule->load(['location', 'office'])), $schedule, ActivityLog::makePayload([
            'schedule_month_from' => $monthFrom->format('Y-m'),
            'schedule_month_to' => $monthTo->format('Y-m'),
            'assigned_user_ids' => $assignedUserIds,
            'title' => $data['title'],
        ]));

        return back()->with('success', 'Preventive maintenance schedule updated.');
    }

    /**
     * Move a published PM schedule to the recycle bin. This is intentionally
     * restricted to Super Admin. Soft deletion keeps its temporary overrides,
     * office-completion sign-off, and assigned-admin pivot rows available for
     * an exact restore; equipment checklist history is stored separately.
     */
    public function destroy(Request $request, MaintenancePlanSchedule $schedule)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $schedule->load(['location', 'office', 'latestOverride', 'completion']);
        $targetLabel = $this->scheduleTargetLabel($schedule);
        $scheduleId = $schedule->id;

        DB::transaction(function () use ($schedule, $targetLabel, $scheduleId) {
            ActivityLog::record(
                'deleted',
                'Removed preventive maintenance schedule for ' . $targetLabel,
                $schedule,
                ActivityLog::makePayload([
                    'schedule_id' => $scheduleId,
                    'location' => $schedule->location?->name,
                    'office' => $schedule->office?->name,
                    'schedule_month_from' => optional($schedule->schedule_month_from ?: $schedule->scheduled_date)->format('Y-m'),
                    'schedule_month_to' => optional($schedule->schedule_month_to ?: $schedule->scheduled_date)->format('Y-m'),
                    'assigned_user_ids' => $this->assignedUserIdsForSchedule($schedule),
                    'preserved_override' => (bool) $schedule->latestOverride,
                    'preserved_completion' => (bool) $schedule->completion,
                    'reason' => 'Published PM schedule moved to the recycle bin by Super Admin.',
                ])
            );

            $schedule->delete();
        });

        $message = 'The scheduled preventive maintenance plan was moved to the recycle bin. Its plan details and equipment checklist history were retained.';
        if ($request->header('X-SPA-Request') === '1') {
            $request->session()->flash('success', $message);
            $request->session()->flash('recycle_bin_notice', 'Deleted PM Plans can be restored by a Super Admin from the Recycle Bin.');

            return response()->json([
                'redirect' => route('admin.maintenance-plan.index'),
                'success' => $message,
            ]);
        }

        return redirect()->route('admin.maintenance-plan.index')
            ->with('success', $message)
            ->with('recycle_bin_notice', 'Deleted PM Plans can be restored by a Super Admin from the Recycle Bin.');
    }

    /** Move selected or filtered PM Plans to the recycle bin. */
    public function bulkDestroy(Request $request): \Illuminate\Http\RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'schedule_ids' => ['nullable', 'array'],
            'schedule_ids.*' => ['integer', 'distinct'],
            'select_all' => ['nullable', 'boolean'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'office_id' => ['nullable', 'integer', 'exists:offices,id'],
            'month_from' => ['nullable', 'date_format:Y-m'],
            'month_to' => ['nullable', 'date_format:Y-m'],
        ]);

        $selectAll = (bool) ($data['select_all'] ?? false);
        $ids = collect($data['schedule_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
        if (! $selectAll && $ids->isEmpty()) {
            return back()->withErrors(['schedule_ids' => 'Select at least one PM Plan or choose delete all matching the filters.']);
        }

        $monthFrom = $this->monthStartOrNull($data['month_from'] ?? null);
        $monthTo = $this->monthStartOrNull($data['month_to'] ?? null);
        $query = MaintenancePlanSchedule::query()
            ->when($data['location_id'] ?? null, fn ($builder, $id) => $builder->where('location_id', $id))
            ->when($data['office_id'] ?? null, fn ($builder, $id) => $builder->where('office_id', $id))
            ->when($monthFrom, fn ($builder) => $builder->whereDate('schedule_month_to', '>=', $monthFrom))
            ->when($monthTo, fn ($builder) => $builder->whereDate('schedule_month_from', '<=', $monthTo));

        if (! $selectAll) {
            $query->whereIn('id', $ids);
        }

        $schedules = $query->with(['location', 'office', 'latestOverride', 'completion'])->get();
        if ($schedules->isEmpty()) {
            return back()->with('warning', 'No active PM Plans matched the selected records or filters.');
        }

        $items = [];
        DB::transaction(function () use ($schedules, &$items): void {
            foreach ($schedules as $schedule) {
                $items[] = [
                    'schedule_id' => $schedule->id,
                    'target' => $this->scheduleTargetLabel($schedule),
                    'location' => $schedule->location?->name,
                    'office' => $schedule->office?->name,
                    'schedule_month_from' => optional($schedule->schedule_month_from ?: $schedule->scheduled_date)->format('Y-m'),
                    'schedule_month_to' => optional($schedule->schedule_month_to ?: $schedule->scheduled_date)->format('Y-m'),
                    'preserved_override' => (bool) $schedule->latestOverride,
                    'preserved_completion' => (bool) $schedule->completion,
                ];

                $schedule->delete();
            }

            ActivityLog::record(
                'deleted',
                'Moved ' . count($items) . ' preventive maintenance plan(s) to the recycle bin.',
                null,
                ActivityLog::makePayload([
                    'bulk' => true,
                    'record_type' => 'PM Plan',
                    'items' => $items,
                ])
            );
        });

        $count = count($items);
        return back()
            ->with('success', "{$count} PM Plan(s) moved to the recycle bin. Plan details and checklist history were retained.")
            ->with('recycle_bin_notice', 'Deleted PM Plans can be restored by a Super Admin from the Recycle Bin.');
    }

    /**
     * Restore a PM Plan from the Super Admin recycle bin. The schedule's
     * overrides, completion sign-off and assigned-admin pivot rows are kept
     * while it is deleted, so restoring brings the plan back exactly as it
     * was published.
     */
    public function restore(int $schedule): \Illuminate\Http\RedirectResponse
    {
        $deletedSchedule = MaintenancePlanSchedule::onlyTrashed()
            ->with(['location', 'office', 'latestOverride', 'completion', 'assignedUsers'])
            ->findOrFail($schedule);

        $targetLabel = $this->scheduleTargetLabel($deletedSchedule);
        $deletedAt = $deletedSchedule->deleted_at?->toDateTimeString();
        $deletedSchedule->restore();

        ActivityLog::record(
            'restored',
            'Restored preventive maintenance schedule for ' . $targetLabel,
            $deletedSchedule,
            ActivityLog::makePayload([
                'schedule_id' => $deletedSchedule->id,
                'location' => $deletedSchedule->location?->name,
                'office' => $deletedSchedule->office?->name,
                'deleted_at' => $deletedAt,
                'assigned_user_ids' => $this->assignedUserIdsForSchedule($deletedSchedule),
            ])
        );

        return back()->with('success', 'Preventive maintenance plan restored.');
    }

    /**
     * Permanently remove a deleted PM Plan and its plan-specific rows. This is
     * deliberately separate from the normal delete action so recycle-bin
     * recovery remains available until Super Admin explicitly purges it.
     */
    public function forceDestroy(int $schedule): \Illuminate\Http\RedirectResponse
    {
        $deletedSchedule = MaintenancePlanSchedule::onlyTrashed()
            ->with(['location', 'office', 'latestOverride', 'completion', 'assignedUsers'])
            ->findOrFail($schedule);

        $targetLabel = $this->scheduleTargetLabel($deletedSchedule);
        $summary = [
            'schedule_id' => $deletedSchedule->id,
            'location' => $deletedSchedule->location?->name,
            'office' => $deletedSchedule->office?->name,
            'schedule_month_from' => optional($deletedSchedule->schedule_month_from ?: $deletedSchedule->scheduled_date)->format('Y-m'),
            'schedule_month_to' => optional($deletedSchedule->schedule_month_to ?: $deletedSchedule->scheduled_date)->format('Y-m'),
            'assigned_user_ids' => $this->assignedUserIdsForSchedule($deletedSchedule),
            'removed_override' => (bool) $deletedSchedule->latestOverride,
            'removed_completion' => (bool) $deletedSchedule->completion,
        ];

        DB::transaction(function () use ($deletedSchedule, $targetLabel, $summary): void {
            ActivityLog::record(
                'force_deleted',
                'Permanently deleted preventive maintenance schedule for ' . $targetLabel,
                null,
                ActivityLog::makePayload($summary)
            );

            // Remove child rows explicitly before force deleting the parent.
            // This works consistently on both MySQL and MariaDB regardless of
            // the foreign-key cascade options in an older installation.
            $deletedSchedule->assignedUsers()->detach();
            $deletedSchedule->overrides()->delete();
            $deletedSchedule->completion()->delete();
            $deletedSchedule->forceDelete();
        });

        return back()->with('success', 'Preventive maintenance plan permanently deleted.');
    }

    public function override(Request $request, MaintenancePlanSchedule $schedule)
    {
        $this->authorizeSchedule($schedule, $request->user());

        $data = $request->validate([
            'override_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $overrideDate = Carbon::createFromFormat('Y-m-d', $data['override_date'])->startOfDay();

        $override = $schedule->overrides()->create([
            'override_date' => $overrideDate->toDateString(),
            'override_month_from' => $overrideDate->toDateString(),
            'override_month_to' => $overrideDate->toDateString(),
            'reason' => $data['reason'],
            'overridden_by' => $request->user()->id,
        ]);

        ActivityLog::record(
            'updated',
            'Temporarily rescheduled preventive maintenance for ' . $this->scheduleTargetLabel($schedule->load(['location', 'office'])),
            $schedule,
            ActivityLog::makePayload([
                'original_schedule' => $this->formatMonthRange($schedule->schedule_month_from ?: $schedule->scheduled_date, $schedule->schedule_month_to ?: $schedule->scheduled_date),
                'override_schedule' => $this->formatOverrideDate($overrideDate),
                'reason' => $override->reason,
            ])
        );

        return back()->with('success', 'Override schedule saved. The original schedule remains unchanged.');
    }

    public function resetOverride(Request $request, MaintenancePlanSchedule $schedule)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $overrides = $schedule->overrides()->orderByDesc('id')->get();
        if ($overrides->isEmpty()) {
            return back()->with('success', 'This schedule has no temporary override to remove.');
        }

        DB::transaction(function () use ($schedule, $overrides) {
            $schedule->overrides()->delete();

            ActivityLog::record(
                'updated',
                'Removed temporary preventive maintenance override for ' . $this->scheduleTargetLabel($schedule->load(['location', 'office'])),
                $schedule,
                ActivityLog::makePayload([
                    'removed_override_count' => $overrides->count(),
                    'removed_override_dates' => $overrides->map(fn ($override) => $this->formatOverrideDate($override->override_date ?: $override->override_month_from))->values()->all(),
                    'reason' => 'Override reset; original published schedule restored.',
                ])
            );
        });

        return back()->with('success', 'Override removed. The original published schedule is active again.');
    }

    public function complete(Request $request, MaintenancePlanSchedule $schedule)
    {
        $this->authorizeSchedule($schedule, $request->user());

        $schedule->load(['latestOverride', 'completion', 'location', 'office']);
        $progress = $this->scheduleProgress($schedule);

        if (! $progress['is_complete']) {
            return back()->withErrors(['completion' => 'Completion details can be added after all equipment in this target has a checklist record on or after the effective schedule date.']);
        }

        $data = $request->validate([
            'actual_date' => ['required', 'date', 'before_or_equal:today'],
            'signer_name' => ['required', 'string', 'max:255'],
            'signature_data' => ['nullable', 'string', 'max:500000'],
            'privacy_consent' => ['accepted'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $personInCharge = $progress['checker_names']
            ?: $schedule->completion?->person_in_charge
            ?: 'Not recorded in checklist';

        $completion = MaintenancePlanCompletion::updateOrCreate(
            ['maintenance_plan_schedule_id' => $schedule->id],
            [
                'actual_date' => $data['actual_date'],
                'person_in_charge' => $personInCharge,
                'signer_name' => $data['signer_name'],
                // Keep the legacy text column populated for old exports; the
                // actual drawn signature is stored in the dedicated text field.
                'signature' => filled($data['signature_data'] ?? null) ? 'Digital signature' : null,
                'signature_data' => $data['signature_data'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'completed_by' => $request->user()->id,
            ]
        );

        ActivityLog::record(
            'updated',
            'Completed preventive maintenance monitoring row for ' . $this->scheduleTargetLabel($schedule),
            $schedule,
            ActivityLog::makePayload([
                'actual_date' => $completion->actual_date->format('Y-m-d'),
                'person_in_charge' => $completion->person_in_charge,
                'signer_name' => $completion->signer_name,
                'signature' => $completion->signature_data ? 'Digital signature' : $completion->signature,
                'privacy_consent' => true,
                'remarks' => $completion->remarks,
            ])
        );

        return back()->with('success', 'Completion details saved for the preventive maintenance schedule.');
    }

    public function report(Request $request)
    {
        $rows = $this->reportRows($request);

        return view('admin.reports.preventive-maintenance-schedule', [
            'rows' => $rows,
            'monthFrom' => $request->query('month_from'),
            'monthTo' => $request->query('month_to'),
            'locationId' => $request->integer('location_id') ?: null,
            'officeId' => $request->integer('office_id') ?: null,
            'locations' => Location::with('offices')->orderBy('name')->get(),
            'unitHead' => User::where('role', User::ROLE_UNIT_HEAD)->first(),
        ]);
    }

    public function reportPdf(Request $request)
    {
        $pdf = Pdf::loadView('admin.reports.preventive-maintenance-schedule-pdf', [
            'rows' => $this->reportRows($request),
            'generatedAt' => now(),
            'unitHead' => User::where('role', User::ROLE_UNIT_HEAD)->first(),
        // 8.5 x 13 inch long-bond paper in landscape orientation.
        // Dompdf custom paper dimensions are expressed in points (72/in).
        ])->setPaper([0, 0, 936, 612]);

        return $pdf->download('preventive-maintenance-schedule-monitoring.pdf');
    }

    /**
     * Used by the checklist controller to protect marking checked records.
     * A plan with no assignee is visible to every Admin/Unit Head; an assigned
     * plan is visible to any account in its assignment list. Super Admin
     * remains unrestricted.
     */
    public static function canMarkDevice(User $user, Device $device): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $device->loadMissing([
            'currentAssignment.staff.office',
            'currentAssignment.office',
            'currentAssignment.location',
        ]);
        $assignment = $device->currentAssignment;
        $office = $assignment?->office ?: $assignment?->staff?->office;
        // The office (including a staff member's office) is the authoritative
        // source when both office and location values exist. Older issuance
        // rows can retain a stale direct location_id, which previously caused
        // a false 403 after a PM Plan assignment was edited.
        $officeId = $office?->id;
        $locationId = $office?->location_id ?: $assignment?->location_id;

        if (! $locationId) {
            return false;
        }

        $target = MaintenancePlanSchedule::query()
            ->where('location_id', $locationId)
            ->where(function ($query) use ($officeId) {
                if ($officeId) {
                    $query->where('office_id', $officeId)->orWhereNull('office_id');
                } else {
                    $query->whereNull('office_id');
                }
            });

        if (! (clone $target)->exists()) {
            return false;
        }

        return $target->where(function ($query) use ($user) {
            $query
                ->where(function ($unassigned) {
                    $unassigned->whereNull('assigned_user_id')
                        ->whereDoesntHave('assignedUsers');
                })
                ->orWhere('assigned_user_id', $user->id)
                ->orWhereHas('assignedUsers', fn ($assigned) => $assigned->whereKey($user->id));
        })->exists();
    }

    private function visibleSchedules(Request $request)
    {
        return MaintenancePlanSchedule::query()->visibleTo($request->user());
    }

    private function authorizeSchedule(MaintenancePlanSchedule $schedule, ?User $user): void
    {
        if (! $user || $user->isSuperAdmin()) {
            return;
        }

        $assignedUserIds = $this->assignedUserIdsForSchedule($schedule);
        abort_unless($assignedUserIds === [] || in_array((int) $user->id, $assignedUserIds, true), 403);
    }

    private function reportRows(Request $request)
    {
        $monthFrom = $this->monthStartOrNull($request->query('month_from'));
        $monthTo = $this->monthStartOrNull($request->query('month_to'));

        return $this->visibleSchedules($request)
            ->when($request->integer('location_id'), fn ($query, $id) => $query->where('location_id', $id))
            ->when($request->integer('office_id'), fn ($query, $id) => $query->where('office_id', $id))
            ->when($monthFrom, fn ($query) => $query->whereDate('schedule_month_to', '>=', $monthFrom))
            ->when($monthTo, fn ($query) => $query->whereDate('schedule_month_from', '<=', $monthTo))
            ->with(['location', 'office', 'latestOverride', 'completion', 'assignedUser', 'assignedUsers'])
            ->orderBy('location_id')
            ->orderBy('office_id')
            ->orderBy('scheduled_date')
            ->get()
            ->map(fn (MaintenancePlanSchedule $schedule) => $this->scheduleRow($schedule));
    }

    private function scheduleRow(MaintenancePlanSchedule $schedule): array
    {
        $progress = $this->scheduleProgress($schedule);
        $override = $schedule->latestOverride;

        return [
            'schedule' => $schedule,
            // Monitoring reports use the registered location and office
            // names in a compact "Location - Office" format. The setup page
            // continues to show the location code separately.
            'office' => collect([
                $schedule->location?->name,
                $schedule->office?->name,
            ])->filter()->join(' - ') ?: 'Unassigned location',
            'original_schedule' => $this->formatMonthRange($schedule->schedule_month_from ?: $schedule->scheduled_date, $schedule->schedule_month_to ?: $schedule->scheduled_date),
            'override_schedule' => $override ? $this->formatOverrideDate($override->override_date ?: $override->override_month_from) : null,
            'override_reason' => $override?->reason,
            'effective_schedule' => $progress['effective_date']->format('m/d/Y'),
            'actual_date' => $progress['actual_dates'],
            'latest_actual_date' => $progress['actual_date']?->format('Y-m-d'),
            'person_in_charge' => $progress['checker_names'] ?: $schedule->completion?->person_in_charge,
            'total_equipment' => $progress['total'],
            'checked_equipment' => $progress['checked'],
            'is_complete' => $progress['is_complete'],
            'completion' => $this->currentCompletion($schedule),
        ];
    }

    private function scheduleProgress(MaintenancePlanSchedule $schedule): array
    {
        $effectiveDate = $schedule->latestOverride?->override_month_from
            ?? $schedule->latestOverride?->override_date
            ?? $schedule->schedule_month_from
            ?? $schedule->scheduled_date;
        $effectiveDate = $effectiveDate instanceof Carbon ? $effectiveDate->copy() : Carbon::parse($effectiveDate);

        $completion = $this->currentCompletion($schedule);
        $cycleStart = $effectiveDate->copy();
        if (! $completion && $schedule->completion?->actual_date) {
            $cycleStart = $cycleStart->max(Carbon::parse($schedule->completion->actual_date)->addMonthsNoOverflow($this->duplicateWindowMonths()));
        }

        $devices = $this->targetDevices($schedule);
        $deviceIds = $devices->pluck('id');
        $records = $deviceIds->isEmpty()
            ? collect()
            : DeviceMaintenanceRecord::query()
                ->whereIn('device_id', $deviceIds)
                ->whereDate('maintenance_date', '>=', $cycleStart->toDateString())
                ->with('checkedBy:id,name')
                ->orderByDesc('maintenance_date')
                ->orderByDesc('id')
                ->get()
                ->groupBy('device_id');

        $checked = $records->keys()->count();
        $isComplete = $devices->isNotEmpty() && $checked === $devices->count();
        $actualDateValues = $records->flatten()
            ->map(fn ($record) => Carbon::parse($record->maintenance_date))
            ->sortBy(fn (Carbon $date) => $date->timestamp)
            ->values();
        $actualDate = $actualDateValues->last();
        $actualDates = $actualDateValues
            ->map(fn (Carbon $date) => $date->format('m/d/Y'))
            ->unique()
            ->values()
            ->implode(', ');

        $checkerNames = $records->flatten()
            ->map(fn ($record) => $record->checkedBy?->name)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->implode(', ');

        return [
            'effective_date' => $effectiveDate,
            'total' => $devices->count(),
            'checked' => $checked,
            'is_complete' => $isComplete,
            'actual_date' => $actualDate,
            'actual_dates' => $actualDates,
            'checker_names' => $checkerNames,
        ];
    }

    private function targetDevices(MaintenancePlanSchedule $schedule)
    {
        return Device::query()
            ->whereHas('type', fn ($query) => $query->whereIn('name', ['Desktop', 'Laptop']))
            ->whereHas('currentAssignment', function ($query) use ($schedule) {
                if ($schedule->office_id) {
                    $query->where(function ($assignment) use ($schedule) {
                        $assignment->where('office_id', $schedule->office_id)
                            ->orWhereHas('staff', fn ($staff) => $staff->where('office_id', $schedule->office_id));
                    });
                } else {
                    $query->where(function ($assignment) use ($schedule) {
                        $assignment->where('location_id', $schedule->location_id)
                            ->orWhereHas('office', fn ($office) => $office->where('location_id', $schedule->location_id))
                            ->orWhereHas('staff.office', fn ($office) => $office->where('location_id', $schedule->location_id));
                    });
                }
            })
            ->get(['id', 'property_number', 'device_type_id']);
    }

    private function scheduleTargetLabel(MaintenancePlanSchedule $schedule): string
    {
        $location = $schedule->location?->code
            ? $schedule->location->code . ' - ' . $schedule->location->name
            : ($schedule->location?->name ?? 'Unassigned location');

        return $schedule->office?->name ? $location . ' / ' . $schedule->office->name : $location;
    }

    /**
     * Normalize the new multi-select field while accepting the legacy
     * assigned_user_id field from older clients and bookmarked forms.
     *
     * @param array<string, mixed> $data
     * @return list<int>
     */
    private function assignedUserIds(array $data): array
    {
        $ids = collect($data['assigned_user_ids'] ?? []);
        if (array_key_exists('assigned_user_id', $data) && filled($data['assigned_user_id'])) {
            $ids->push($data['assigned_user_id']);
        }

        return $ids
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Return both pivot assignments and the legacy primary assignment so
     * schedules created before the pivot migration compare correctly.
     *
     * @return list<int>
     */
    private function assignedUserIdsForSchedule(MaintenancePlanSchedule $schedule): array
    {
        return collect($schedule->assignedUsers()->pluck('users.id')->all())
            ->push($schedule->assigned_user_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function monthRange(string $from, ?string $to): array
    {
        $start = Carbon::createFromFormat('Y-m', $from)->startOfMonth();
        $end = Carbon::createFromFormat('Y-m', $to ?: $from)->startOfMonth();

        return [$start, $end];
    }

    private function monthStartOrNull(?string $month): ?Carbon
    {
        if (! $month) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatMonthRange(mixed $from, mixed $to): string
    {
        $start = $from instanceof Carbon ? $from->copy() : Carbon::parse($from);
        $end = $to instanceof Carbon ? $to->copy() : Carbon::parse($to);

        return $start->format('F Y') . ($start->isSameMonth($end) ? '' : ' - ' . $end->format('F Y'));
    }

    private function formatOverrideDate(mixed $date): string
    {
        $value = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);

        return $value->format('m/d/Y');
    }

    private function duplicateWindowMonths(): int
    {
        return max(1, min(36, (int) SystemSetting::getValue('maintenance_checklist_duplicate_window_months', 3)));
    }

    private function currentCompletion(MaintenancePlanSchedule $schedule): ?MaintenancePlanCompletion
    {
        $completion = $schedule->completion;
        if (! $completion?->actual_date) {
            return $completion;
        }

        return Carbon::parse($completion->actual_date)->addMonthsNoOverflow($this->duplicateWindowMonths())->isFuture()
            ? $completion
            : null;
    }
}
