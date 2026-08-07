<?php

namespace App\Http\Controllers\Admin;

use App\Exports\PreventiveMaintenanceQualityExport;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\DeviceMaintenanceRecord;
use App\Models\Location;
use App\Models\MaintenancePlanSchedule;
use App\Models\Office;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Read-only Quality Objective monitoring derived from PM Plans, equipment,
 * assignment history, and saved maintenance checklists.
 */
class PreventiveMaintenanceQualityController extends Controller
{
    private const QUALITY_TARGET_PERCENT = 90;

    private const ELIGIBLE_PARENT_TYPES = ['desktop', 'laptop'];

    public function index(Request $request)
    {
        $data = $this->reportData($request);
        $allRows = $data['rows'];
        $perPage = 10;
        $currentPage = LengthAwarePaginator::resolveCurrentPage('page');
        $data['rows'] = new LengthAwarePaginator(
            $allRows->forPage($currentPage, $perPage)->values(),
            $allRows->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'pageName' => 'page',
                'query' => $request->query(),
            ]
        );
        $locations = Location::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
        $offices = $data['filters']['location_id']
            ? Office::query()
                ->where('location_id', $data['filters']['location_id'])
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'location_id', 'name'])
            : collect();

        return view('admin.reports.preventive-maintenance-quality', $data + [
            'locations' => $locations,
            'offices' => $offices,
        ]);
    }

    public function pdf(Request $request)
    {
        $data = $this->reportData($request) + ['generatedAt' => now()];

        $pdf = Pdf::loadView('admin.reports.preventive-maintenance-quality-pdf', $data)
            ->setPaper([0, 0, 936, 612]);

        return $pdf->download($this->filename($data['period'], 'pdf'));
    }

    public function export(Request $request)
    {
        $data = $this->reportData($request) + ['generatedAt' => now()];

        $response = Excel::download(
            new PreventiveMaintenanceQualityExport($data),
            $this->filename($data['period'], 'xlsx')
        );

        // Explicitly set the XLSX MIME type. Without it some web servers and
        // SPA fallbacks treat the ZIP-based workbook as plain text (showing
        // the raw `PK...` bytes in the browser instead of downloading it).
        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        return $response;
    }

    /**
     * Kept public so the export can be verified without duplicating report
     * calculations. The method performs no writes.
     */
    public function reportData(Request $request): array
    {
        $filters = $this->validatedFilters($request);
        $period = $this->period((int) $filters['year'], (int) $filters['semester']);
        $currentRows = $this->rows($request, $filters, $period);
        $previousPeriod = $this->previousPeriod($filters);
        $previousRows = $this->rows($request, $filters, $previousPeriod);
        $rows = $this->applyHistoricalQualityMetrics($currentRows, $previousRows);
        $performanceGraph = $this->performanceGraphData($request, $filters, $period, $rows, $previousRows);

        return [
            'rows' => $rows,
            'summary' => $this->summary($rows),
            'chart' => $this->chartData($request, $filters, $rows),
            'performanceGraph' => $performanceGraph,
            'period' => $period,
            'filters' => $filters,
            // The account that generated the report is the preparer for both
            // PDF and Excel. Keeping it in the shared payload prevents the
            // two formats from drifting apart or using a hard-coded name.
            'preparedBy' => $request->user(),
            'unitHead' => User::where('role', User::ROLE_UNIT_HEAD)->first(),
        ];
    }

    private function filename(array $period, string $extension): string
    {
        return 'quality-objective-pm-' . $period['year'] . '-s' . $period['semester'] . '.' . $extension;
    }

    private function validatedFilters(Request $request): array
    {
        $currentYear = (int) now()->format('Y');
        $currentSemester = now()->month <= 6 ? 1 : 2;

        $data = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'office_id' => ['nullable', 'integer', 'exists:offices,id'],
        ]);

        $locationId = isset($data['location_id']) ? (int) $data['location_id'] : null;
        $officeId = isset($data['office_id']) ? (int) $data['office_id'] : null;

        // Match Equipment's dependent filters. An office identifies its
        // parent location; a stale office from another location is ignored so
        // it cannot broaden the report to an unrelated target.
        if ($officeId) {
            $office = Office::query()->select(['id', 'location_id'])->find($officeId);

            if (! $office) {
                $officeId = null;
            } elseif (! $locationId) {
                $locationId = (int) $office->location_id;
            } elseif ((int) $office->location_id !== $locationId) {
                $officeId = null;
            }
        }

        return [
            'year' => (int) ($data['year'] ?? $currentYear),
            'semester' => (int) ($data['semester'] ?? $currentSemester),
            'location_id' => $locationId,
            'office_id' => $officeId,
        ];
    }

    private function period(int $year, int $semester): array
    {
        $start = Carbon::create($year, $semester === 1 ? 1 : 7, 1)->startOfDay();
        $end = $start->copy()->addMonths(5)->endOfMonth()->endOfDay();

        return [
            'year' => $year,
            'semester' => $semester,
            'start' => $start,
            'end' => $end,
            'label' => $semester === 1
                ? '1st Semi-Annually (January-June)'
                : '2nd Semi-Annually (July-December)',
            'chart_label' => $semester === 1
                ? '1st Semi-Annually (January-June)'
                : '2nd Semi-Annually (July-December)',
        ];
    }

    private function previousPeriod(array $filters): array
    {
        $semester = (int) $filters['semester'];
        $previousSemester = $semester === 1 ? 2 : 1;
        $previousYear = $semester === 1
            ? (int) $filters['year'] - 1
            : (int) $filters['year'];

        return $this->period($previousYear, $previousSemester);
    }

    /**
     * Apply the historical target and current-period adjustment rules used by
     * the Quality Objective report. The source rows remain untouched so PDF,
     * Excel, and later historical queries can still use the original records.
     */
    private function applyHistoricalQualityMetrics(Collection $currentRows, Collection $previousRows): Collection
    {
        $previousTargets = $previousRows
            ->groupBy(fn (array $row) => $this->qualityLocationKey($row))
            ->map(fn (Collection $rows) => (int) $rows->sum('target'));

        return $currentRows->map(function (array $row) use ($previousTargets): array {
            $currentTarget = (int) ($row['target'] ?? 0);
            $target = (int) ($previousTargets->get($this->qualityLocationKey($row), 0));

            // A saved checklist is the current maintained count. Transfers
            // adjust that count, while unserviceable equipment remains part of
            // the maintained population. Condemned equipment is already
            // excluded from the eligible/checklist collections in rows().
            $actual = max(0,
                (int) ($row['checked'] ?? 0)
                - (int) ($row['transferred_out'] ?? 0)
                + (int) ($row['transferred_in'] ?? 0)
                + (int) ($row['unserviceable'] ?? 0)
            );
            $rate = $target > 0 ? $actual / $target : null;

            $warnings = collect($row['warnings'] ?? []);
            if ($target === 0 && $currentTarget > 0) {
                $warnings->push('No previous PM Plan target was found for this location/office.');
            }

            return array_merge($row, [
                'current_target' => $currentTarget,
                'target' => $target,
                'actual' => $actual,
                'rate' => $rate,
                'status' => $rate === null
                    ? 'N/A'
                    : ($rate >= self::QUALITY_TARGET_PERCENT / 100 ? 'Complied' : 'Not Complied'),
                'warnings' => $warnings->unique()->values()->all(),
            ]);
        });
    }

    private function qualityLocationKey(array $row): string
    {
        return (string) ($row['location_id'] ?? '') . ':' . (string) ($row['office_id'] ?? '');
    }

    private function schedules(Request $request, array $filters, array $period): Collection
    {
        return MaintenancePlanSchedule::query()
            ->visibleTo($request->user())
            ->whereDate('schedule_month_from', '<=', $period['end']->toDateString())
            ->whereDate('schedule_month_to', '>=', $period['start']->toDateString())
            ->when($filters['location_id'], fn ($query, $id) => $query->where('location_id', $id))
            ->when($filters['office_id'], fn ($query, $id) => $query->where('office_id', $id))
            ->with([
                'location:id,name,code',
                'office:id,location_id,name',
                'latestOverride',
                'completion',
            ])
            ->orderBy('location_id')
            ->orderBy('office_id')
            ->orderBy('schedule_month_from')
            ->get();
    }

    private function rows(Request $request, array $filters, array $period): Collection
    {
        $schedules = $this->schedules($request, $filters, $period);

        if ($schedules->isEmpty()) {
            return collect();
        }

        $locationIds = $schedules->pluck('location_id')->filter()->unique()->values();
        $officeIds = $schedules->pluck('office_id')->filter()->unique()->values();

        // Load current assignments once. This avoids repeating the same joins
        // for every office row when a report contains many PM Plan schedules.
        $currentAssignments = DeviceAssignment::query()
            ->whereNull('returned_at')
            ->where($this->assignmentTargetConstraint($locationIds, $officeIds))
            ->with([
                'device.type:id,name',
                'staff.office.location:id,name,code',
                'office.location:id,name,code',
                'location:id,name,code',
            ])
            ->get()
            ->sortBy(fn (DeviceAssignment $assignment) => sprintf('%020d-%020d', $assignment->issued_at?->timestamp ?? 0, $assignment->id))
            ->groupBy('device_id')
            ->map(fn (Collection $assignments) => $assignments->last())
            ->values();

        // Candidate transfer devices are restricted to Desktop/Laptop records
        // that touched one of the report targets. Their complete history up to
        // period end is then loaded so IN/OUT transitions are calculated safely.
        $transferDeviceIds = DeviceAssignment::query()
            ->whereHas('device.type', fn ($query) => $query->whereIn('name', ['Desktop', 'Laptop']))
            ->where($this->assignmentTargetConstraint($locationIds, $officeIds))
            ->whereDate('issued_at', '<=', $period['end']->toDateString())
            ->pluck('device_id')
            ->unique()
            ->values();

        $assignmentHistory = $transferDeviceIds->isEmpty()
            ? collect()
            : DeviceAssignment::query()
                ->whereIn('device_id', $transferDeviceIds)
                ->whereDate('issued_at', '<=', $period['end']->toDateString())
                ->with([
                    'device.type:id,name',
                    'staff.office.location:id,name,code',
                    'office.location:id,name,code',
                    'location:id,name,code',
                ])
                ->orderBy('device_id')
                ->orderBy('issued_at')
                ->orderBy('id')
                ->get()
                ->groupBy('device_id');

        // Snapshot location/office fields make historical checklist rows stay
        // linked to the office where maintenance was actually performed.
        $records = DeviceMaintenanceRecord::query()
            ->whereBetween('maintenance_date', [
                $period['start']->toDateString(),
                $period['end']->toDateString(),
            ])
            ->whereHas('device.type', fn ($query) => $query->whereIn('name', ['Desktop', 'Laptop']))
            ->with([
                'device.type:id,name',
                'checkedBy:id,name',
                'staff.office.location:id,name,code',
                'office.location:id,name,code',
                'location:id,name,code',
            ])
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->get();

        return $schedules->map(fn (MaintenancePlanSchedule $schedule) => $this->row(
            $schedule,
            $period,
            $currentAssignments,
            $assignmentHistory,
            $records
        ));
    }

    private function assignmentTargetConstraint(Collection $locationIds, Collection $officeIds): \Closure
    {
        return function ($query) use ($locationIds, $officeIds) {
            $query->where(function ($target) use ($locationIds, $officeIds) {
                if ($locationIds->isNotEmpty()) {
                    $target->whereIn('location_id', $locationIds)
                        ->orWhereHas('office', fn ($office) => $office->whereIn('location_id', $locationIds))
                        ->orWhereHas('staff.office', fn ($office) => $office->whereIn('location_id', $locationIds));
                }

                if ($officeIds->isNotEmpty()) {
                    $target->orWhereIn('office_id', $officeIds)
                        ->orWhereHas('staff', fn ($staff) => $staff->whereIn('office_id', $officeIds));
                }
            });
        };
    }

    private function row(
        MaintenancePlanSchedule $schedule,
        array $period,
        Collection $currentAssignments,
        Collection $assignmentHistory,
        Collection $records
    ): array {
        $targetAssignments = $currentAssignments
            ->filter(fn (DeviceAssignment $assignment) => $this->assignmentMatchesSchedule($assignment, $schedule));

        $devices = $targetAssignments
            ->map(fn (DeviceAssignment $assignment) => $assignment->device)
            ->filter()
            ->unique('id')
            ->values();

        $eligibleDevices = $devices
            ->filter(fn (Device $device) => $this->isEligibleParent($device)
                && strtolower((string) $device->condition) !== 'condemned');

        $condemned = $devices
            ->filter(fn (Device $device) => strtolower((string) $device->condition) === 'condemned')
            ->count();

        $unserviceable = $devices
            ->filter(function (Device $device) {
                $condition = strtolower((string) $device->condition);
                $status = strtolower((string) $device->status);

                if ($condition === 'condemned') {
                    return false;
                }

                return $condition === 'unserviceable' || in_array($status, ['repair', 'not_in_use'], true);
            })
            ->count();

        $additional = $eligibleDevices
            ->filter(fn (Device $device) => $device->date_acquired
                && $device->date_acquired->betweenIncluded($period['start'], $period['end']))
            ->count();

        $maintenanceRecords = $records
            ->filter(fn (DeviceMaintenanceRecord $record) => $this->recordMatchesSchedule($record, $schedule))
            ->filter(fn (DeviceMaintenanceRecord $record) => $record->device
                && $this->isEligibleParent($record->device)
                && strtolower((string) $record->condition) !== 'condemned'
                && strtolower((string) $record->device->condition) !== 'condemned')
            ->groupBy('device_id')
            ->map(fn (Collection $deviceRecords) => $deviceRecords->first())
            ->values();

        $checked = $maintenanceRecords->count();
        ['in' => $transferredIn, 'out' => $transferredOut] = $this->transferCounts(
            $schedule,
            $period,
            $assignmentHistory
        );

        // The QO actual is the same progress count shown on the PM Plan:
        // unique eligible target equipment with a saved checklist record.
        // Transfers remain separate reporting columns and are not added to or
        // subtracted from the maintenance progress.
        $actual = $checked;
        $target = $eligibleDevices->count();
        $rate = $target > 0 ? $actual / $target : null;

        $warnings = collect();
        if ($target === 0) {
            $warnings->push('No eligible Desktop/Laptop equipment is currently assigned to this PM Plan target.');
        }
        if ($actual > $target && $target > 0) {
            $warnings->push('Actual maintained exceeds the current target; verify the PM Plan target and checklist history.');
        }

        $remarks = collect([
            $schedule->completion?->remarks,
            $schedule->latestOverride?->reason ? 'Rescheduled due to: ' . $schedule->latestOverride->reason : null,
        ])->filter()->values()->all();

        return [
            'location_id' => (int) $schedule->location_id,
            'office_id' => $schedule->office_id ? (int) $schedule->office_id : null,
            'office' => $this->officeLabel($schedule),
            'schedule' => $this->scheduleLabel($schedule),
            'target' => $target,
            'condemned' => $condemned,
            'unserviceable' => $unserviceable,
            'additional' => $additional,
            'checked' => $checked,
            'actual' => $actual,
            'transferred_in' => $transferredIn,
            'transferred_out' => $transferredOut,
            'dates' => $maintenanceRecords
                ->pluck('maintenance_date')
                ->filter()
                ->map(fn ($date) => Carbon::parse($date)->format('m/d/Y'))
                ->unique()
                ->sort()
                ->implode(', '),
            'persons' => $maintenanceRecords
                ->map(fn (DeviceMaintenanceRecord $record) => $record->checkedBy?->name)
                ->filter()
                ->unique()
                ->sort()
                ->implode(', '),
            'rate' => $rate,
            'status' => $rate === null
                ? 'N/A'
                : ($rate >= self::QUALITY_TARGET_PERCENT / 100 ? 'Complied' : 'Not Complied'),
            'remarks' => implode(' ', $remarks),
            'warnings' => $warnings->values()->all(),
            'schedule_id' => $schedule->id,
        ];
    }

    private function transferCounts(
        MaintenancePlanSchedule $schedule,
        array $period,
        Collection $assignmentHistory
    ): array {
        $in = 0;
        $out = 0;

        foreach ($assignmentHistory as $history) {
            /** @var Collection<int, DeviceAssignment> $history */
            $historyDevice = $history->first()?->device;
            if (! $historyDevice || strtolower((string) $historyDevice->condition) === 'condemned') {
                continue;
            }

            $ordered = $history->sortBy([
                ['issued_at', 'asc'],
                ['id', 'asc'],
            ])->values();

            foreach ($ordered as $index => $assignment) {
                if ($index === 0 || ! $assignment->issued_at?->betweenIncluded($period['start'], $period['end'])) {
                    continue;
                }

                $previous = $ordered[$index - 1];
                $previousMatches = $this->assignmentMatchesSchedule($previous, $schedule);
                $currentMatches = $this->assignmentMatchesSchedule($assignment, $schedule);

                if (! $previousMatches && $currentMatches) {
                    $in++;
                } elseif ($previousMatches && ! $currentMatches) {
                    $out++;
                }
            }

            $last = $ordered->last();
            if ($last
                && $last->returned_at?->betweenIncluded($period['start'], $period['end'])
                && $this->assignmentMatchesSchedule($last, $schedule)) {
                $out++;
            }
        }

        return ['in' => $in, 'out' => $out];
    }

    private function isEligibleParent(Device $device): bool
    {
        return in_array(strtolower((string) $device->type?->name), self::ELIGIBLE_PARENT_TYPES, true);
    }

    private function assignmentMatchesSchedule(DeviceAssignment $assignment, MaintenancePlanSchedule $schedule): bool
    {
        if ($schedule->office_id) {
            return (int) $assignment->office_id === (int) $schedule->office_id
                || (int) $assignment->staff?->office_id === (int) $schedule->office_id;
        }

        return (int) $assignment->location_id === (int) $schedule->location_id
            || (int) $assignment->office?->location_id === (int) $schedule->location_id
            || (int) $assignment->staff?->office?->location_id === (int) $schedule->location_id;
    }

    private function recordMatchesSchedule(DeviceMaintenanceRecord $record, MaintenancePlanSchedule $schedule): bool
    {
        if ($schedule->office_id) {
            return (int) $record->office_id === (int) $schedule->office_id
                || (int) $record->staff?->office_id === (int) $schedule->office_id;
        }

        return (int) $record->location_id === (int) $schedule->location_id
            || (int) $record->office?->location_id === (int) $schedule->location_id
            || (int) $record->staff?->office?->location_id === (int) $schedule->location_id;
    }

    private function officeLabel(MaintenancePlanSchedule $schedule): string
    {
        $location = $schedule->location?->code ?: $schedule->location?->name;

        return collect([$location, $schedule->office?->name])
            ->filter()
            ->implode(' - ') ?: 'Unassigned location';
    }

    private function scheduleLabel(MaintenancePlanSchedule $schedule): string
    {
        $original = $this->formatMonthRange(
            $schedule->schedule_month_from ?: $schedule->scheduled_date,
            $schedule->schedule_month_to ?: $schedule->scheduled_date
        );
        $override = $schedule->latestOverride?->override_date
            ?: $schedule->latestOverride?->override_month_from;

        return $override
            ? $original . ' / Re-Scheduled on: ' . Carbon::parse($override)->format('m/d/Y')
            : $original;
    }

    private function formatMonthRange(mixed $from, mixed $to): string
    {
        $start = $from instanceof Carbon ? $from->copy() : Carbon::parse($from);
        $end = $to instanceof Carbon ? $to->copy() : Carbon::parse($to);

        return $start->format('F Y') . ($start->isSameMonth($end) ? '' : ' - ' . $end->format('F Y'));
    }

    private function summary(Collection $rows): array
    {
        $target = (int) $rows->sum('target');
        $actual = (int) $rows->sum('actual');
        $rate = $target > 0 ? $actual / $target : null;

        return [
            'target' => $target,
            'condemned' => (int) $rows->sum('condemned'),
            'unserviceable' => (int) $rows->sum('unserviceable'),
            'additional' => (int) $rows->sum('additional'),
            'checked' => (int) $rows->sum('checked'),
            'actual' => $actual,
            'transferred_in' => (int) $rows->sum('transferred_in'),
            'transferred_out' => (int) $rows->sum('transferred_out'),
            'rate' => $rate,
            'status' => $rate === null
                ? 'N/A'
                : ($rate >= self::QUALITY_TARGET_PERCENT / 100 ? 'Complied' : 'Not Complied'),
            'warnings' => $rows->pluck('warnings')->flatten()->filter()->unique()->values()->all(),
            'target_percent' => self::QUALITY_TARGET_PERCENT,
        ];
    }

    private function chartData(Request $request, array $filters, Collection $selectedRows): array
    {
        $points = collect([1, 2])->map(function (int $semester) use ($request, $filters, $selectedRows) {
            $period = $this->period((int) $filters['year'], $semester);
            $rows = $semester === (int) $filters['semester']
                ? $selectedRows
                : $this->qualityRowsForPeriod($request, $filters, $period);
            $target = (int) $rows->sum('target');
            $actual = (int) $rows->sum('actual');
            $rate = $target > 0 ? $actual / $target : null;

            return [
                'label' => $period['chart_label'],
                'actual' => $rate === null ? 0 : min(1, $rate),
                'actual_label' => $rate === null ? 'No data' : number_format($rate * 100, 2) . '%',
                'has_data' => $target > 0 || $actual > 0,
                'target' => 1,
                'target_label' => '100%',
            ];
        })->values()->all();

        return ['points' => $points, 'year' => (int) $filters['year']];
    }

    private function qualityRowsForPeriod(Request $request, array $filters, array $period): Collection
    {
        $currentRows = $this->rows($request, $filters, $period);
        $periodFilters = array_merge($filters, [
            'year' => (int) $period['year'],
            'semester' => (int) $period['semester'],
        ]);

        return $this->applyHistoricalQualityMetrics(
            $currentRows,
            $this->rows($request, $periodFilters, $this->previousPeriod($periodFilters))
        );
    }

    /**
     * Build the controlled-form performance graph values. The selected
     * semester's actual data is the number of equipment maintained in the
     * current PM cycle. Its baseline is the target count from the preceding
     * preventive cycle. The percentage is the current PM completion rate,
     * independent of the raw baseline/actual totals; the other semester
     * remains intentionally blank.
     *
     * @return array{selected_semester:int, columns:array<int,array<string,mixed>>, actual:mixed, baseline:mixed, rate:mixed}
     */
    private function performanceGraphData(
        Request $request,
        array $filters,
        array $period,
        Collection $selectedRows,
        Collection $previousRows
    ): array {
        $selectedSemester = (int) $filters['semester'];

        $actual = $selectedRows->isEmpty()
            ? null
            : (int) $selectedRows->sum('actual');
        $baseline = $previousRows->isEmpty()
            ? null
            : (int) $previousRows->sum('target');
        $rate = $selectedRows->sum('target') > 0
            ? min(1, $actual / (int) $selectedRows->sum('target'))
            : null;

        $columns = [];
        foreach ([1, 2] as $semester) {
            $labelPeriod = $this->period((int) $filters['year'], $semester);
            $isSelected = $semester === $selectedSemester;
            $columns[$semester] = [
                'label' => $labelPeriod['chart_label'],
                'actual_data' => $isSelected ? $actual : null,
                'baseline' => $isSelected ? $baseline : null,
            ];
        }

        return [
            'selected_semester' => $selectedSemester,
            'actual' => $actual,
            'baseline' => $baseline,
            'rate' => $rate,
            'columns' => $columns,
        ];
    }
}
