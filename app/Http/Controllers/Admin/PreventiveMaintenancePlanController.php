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
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $schedules = $this->visibleSchedules($request)
            ->when($locationId, fn ($query) => $query->where('location_id', $locationId))
            ->when($officeId, fn ($query) => $query->where('office_id', $officeId))
            ->when($dateFrom, fn ($query) => $query->whereDate('scheduled_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('scheduled_date', '<=', $dateTo))
            ->with([
                'location:id,name,code',
                'office:id,location_id,name',
                'assignedUser:id,name,email,role',
                'latestOverride',
                'completion',
            ])
            ->orderByDesc('scheduled_date')
            ->orderBy('location_id')
            ->orderBy('office_id')
            ->get()
            ->map(fn (MaintenancePlanSchedule $schedule) => $this->scheduleRow($schedule));

        return view('admin.maintenance-plan.index', [
            'schedules' => $schedules,
            'locations' => Location::with(['offices:id,location_id,name'])->orderBy('name')->get(),
            'admins' => User::query()
                ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_UNIT_HEAD])
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role']),
            'selectedLocationId' => $locationId,
            'selectedOfficeId' => $officeId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
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
            'scheduled_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

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

        DB::transaction(function () use ($targets, $data, $location, &$created, $request) {
            foreach ($targets as $office) {
                $exists = MaintenancePlanSchedule::query()
                    ->where('location_id', $location->id)
                    ->where('office_id', $office?->id)
                    ->whereDate('scheduled_date', $data['scheduled_date'])
                    ->where('assigned_user_id', $data['assigned_user_id'] ?? null)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $schedule = MaintenancePlanSchedule::create([
                    'location_id' => $location->id,
                    'office_id' => $office?->id,
                    'assigned_user_id' => $data['assigned_user_id'] ?? null,
                    'created_by' => $request->user()->id,
                    'scheduled_date' => $data['scheduled_date'],
                    'title' => $data['title'],
                    'notes' => $data['notes'] ?? null,
                ]);

                ActivityLog::record(
                    'created',
                    'Created preventive maintenance schedule for ' . $this->scheduleTargetLabel($schedule->fresh(['location', 'office'])),
                    $schedule,
                    ActivityLog::makePayload([
                        'location' => $location->name,
                        'office' => $office?->name,
                        'scheduled_date' => $data['scheduled_date'],
                        'assigned_user_id' => $data['assigned_user_id'] ?? null,
                        'title' => $data['title'],
                    ])
                );
                $created++;
            }
        });

        $message = $created
            ? "Created {$created} preventive maintenance schedule(s)."
            : 'No new schedule was created because the same target/date/assignee already exists.';

        return redirect()->route('admin.maintenance-plan.index')->with('success', $message);
    }

    public function override(Request $request, MaintenancePlanSchedule $schedule)
    {
        $this->authorizeSchedule($schedule, $request->user());

        $data = $request->validate([
            'override_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $override = $schedule->overrides()->create([
            'override_date' => $data['override_date'],
            'reason' => $data['reason'],
            'overridden_by' => $request->user()->id,
        ]);

        ActivityLog::record(
            'updated',
            'Temporarily rescheduled preventive maintenance for ' . $this->scheduleTargetLabel($schedule->load(['location', 'office'])),
            $schedule,
            ActivityLog::makePayload([
                'original_schedule' => optional($schedule->scheduled_date)->format('Y-m-d'),
                'override_schedule' => $override->override_date->format('Y-m-d'),
                'reason' => $override->reason,
            ])
        );

        return back()->with('success', 'Override schedule saved. The original schedule remains unchanged.');
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
            'person_in_charge' => ['required', 'string', 'max:255'],
            'signature' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $completion = MaintenancePlanCompletion::updateOrCreate(
            ['maintenance_plan_schedule_id' => $schedule->id],
            [
                'actual_date' => $data['actual_date'],
                'person_in_charge' => $data['person_in_charge'],
                'signature' => $data['signature'],
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
                'signature' => $completion->signature,
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
            'dateFrom' => $request->query('date_from'),
            'dateTo' => $request->query('date_to'),
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
        ])->setPaper('a4', 'landscape');

        return $pdf->download('preventive-maintenance-schedule-monitoring.pdf');
    }

    /**
     * Used by the checklist controller to protect marking checked records.
     * A plan with no assignee is visible to every Admin/Unit Head; an assigned
     * plan is visible only to that account. Super Admin remains unrestricted.
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
        $location = $assignment?->location ?: $office?->location;

        if (! $location) {
            return false;
        }

        $target = MaintenancePlanSchedule::query()
            ->where('location_id', $location->id)
            ->where(function ($query) use ($office) {
                if ($office) {
                    $query->where('office_id', $office->id)->orWhereNull('office_id');
                } else {
                    $query->whereNull('office_id');
                }
            });

        if (! (clone $target)->exists()) {
            return false;
        }

        return $target->where(function ($query) use ($user) {
            $query->whereNull('assigned_user_id')->orWhere('assigned_user_id', $user->id);
        })->exists();
    }

    private function visibleSchedules(Request $request)
    {
        return MaintenancePlanSchedule::query()->visibleTo($request->user());
    }

    private function authorizeSchedule(MaintenancePlanSchedule $schedule, ?User $user): void
    {
        abort_unless($user && ($user->isSuperAdmin() || $schedule->assigned_user_id === null || (int) $schedule->assigned_user_id === (int) $user->id), 403);
    }

    private function reportRows(Request $request)
    {
        return $this->visibleSchedules($request)
            ->when($request->integer('location_id'), fn ($query, $id) => $query->where('location_id', $id))
            ->when($request->integer('office_id'), fn ($query, $id) => $query->where('office_id', $id))
            ->when($request->query('date_from'), fn ($query, $date) => $query->whereDate('scheduled_date', '>=', $date))
            ->when($request->query('date_to'), fn ($query, $date) => $query->whereDate('scheduled_date', '<=', $date))
            ->with(['location', 'office', 'latestOverride', 'completion', 'assignedUser'])
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
            'office' => $this->scheduleTargetLabel($schedule),
            'original_schedule' => optional($schedule->scheduled_date)->format('m/d/Y'),
            'override_schedule' => optional($override?->override_date)->format('m/d/Y'),
            'override_reason' => $override?->reason,
            'effective_schedule' => $progress['effective_date']->format('m/d/Y'),
            'actual_date' => $progress['actual_dates'],
            'latest_actual_date' => $progress['actual_date']?->format('Y-m-d'),
            'total_equipment' => $progress['total'],
            'checked_equipment' => $progress['checked'],
            'is_complete' => $progress['is_complete'],
            'completion' => $schedule->completion,
        ];
    }

    private function scheduleProgress(MaintenancePlanSchedule $schedule): array
    {
        $effectiveDate = $schedule->latestOverride?->override_date ?? $schedule->scheduled_date;
        $effectiveDate = $effectiveDate instanceof Carbon ? $effectiveDate->copy() : Carbon::parse($effectiveDate);

        $devices = $this->targetDevices($schedule);
        $deviceIds = $devices->pluck('id');
        $records = $deviceIds->isEmpty()
            ? collect()
            : DeviceMaintenanceRecord::query()
                ->whereIn('device_id', $deviceIds)
                ->whereDate('maintenance_date', '>=', $effectiveDate->toDateString())
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

        return [
            'effective_date' => $effectiveDate,
            'total' => $devices->count(),
            'checked' => $checked,
            'is_complete' => $isComplete,
            'actual_date' => $actualDate,
            'actual_dates' => $actualDates,
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
}
