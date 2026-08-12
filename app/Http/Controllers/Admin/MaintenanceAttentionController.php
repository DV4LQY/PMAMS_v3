<?php

namespace App\Http\Controllers\Admin;

use App\Exports\MaintenanceAttentionExport;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\MaintenancePlanSchedule;
use App\Models\Office;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\MaintenanceAttentionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class MaintenanceAttentionController extends Controller
{
    public function index(Request $request, MaintenanceAttentionService $maintenanceAttentionService)
    {
        $perPage = min(max((int) $request->integer('per_page', 10), 5), 50);
        // Location and office values come from several historical sources
        // (assignments, deployed references, and imported text). Normalize
        // whitespace/case at the filter boundary so a visually identical
        // value never drops a device from the result count.
        $location = $this->normalizeName((string) $request->query('location', ''));
        $office = $this->normalizeName((string) $request->query('office', ''));
        $attention = trim((string) $request->query('attention', ''));
        $priorityOptions = ['Critical', 'High', 'Medium', 'Low'];
        $priority = $this->filterKey((string) $request->query('priority', ''));
        $priority = in_array($priority, array_map([$this, 'filterKey'], $priorityOptions), true) ? $priority : '';
        $equipmentTypeOptions = ['Desktop', 'Laptop', 'Printer', 'Monitor', 'UPS'];
        $conditionOptions = ['serviceable', 'unserviceable', 'condemned'];
        $statusOptions = ['available', 'issued', 'repair', 'not_in_use'];
        $equipmentTypes = $this->normalizeMultiValue($request->query('equipment_types', []));
        $equipmentTypes = array_values(array_intersect(
            $equipmentTypes,
            array_map([$this, 'filterKey'], $equipmentTypeOptions)
        ));
        $condition = $this->filterKey((string) $request->query('condition', ''));
        $condition = in_array($condition, $conditionOptions, true) ? $condition : '';
        $status = $this->filterKey((string) $request->query('status', ''));
        $status = in_array($status, $statusOptions, true) ? $status : '';
        $q = trim((string) $request->query('q', ''));
        $year = $this->validMaintenanceYear($request->query('year'));
        $semester = $this->validMaintenanceSemester($request->query('semester'));
        // A semester has meaning only together with its calendar year. Treat
        // a manually crafted semester-only URL as an unfiltered period rather
        // than exporting rows from every year with a misleading label.
        if ($year === null) {
            $semester = null;
        }
        // Keep the page lightweight until the user actually executes a
        // search/filter or presses Reset. Empty query-string keys (for
        // example `?q=` left by a browser or SPA link) must not trigger the
        // recommendation scan.
        $loaded = $request->has('reset')
            || $q !== ''
            || $location !== ''
            || $office !== ''
            || $attention !== ''
            || $priority !== ''
            || $equipmentTypes !== []
            || $condition !== ''
            || $status !== ''
            || $year !== null
            || $semester !== null;
        $mode = MaintenanceAttentionService::normalizeMode(
            (string) SystemSetting::getValue(MaintenanceAttentionService::MODE_SETTING_KEY, 'hybrid')
        );
        $aiMetadata = null;
        $aiTrainedAt = null;
        $metadataPath = (string) config('maintenance.attention_ai.metadata');
        if (is_file($metadataPath)) {
            try {
                $metadata = json_decode((string) file_get_contents($metadataPath), true, 512, JSON_THROW_ON_ERROR);
                $aiMetadata = is_array($metadata) ? $metadata : null;

                if (is_array($aiMetadata) && filled($aiMetadata['trained_at'] ?? null)) {
                    $aiTrainedAt = CarbonImmutable::parse((string) $aiMetadata['trained_at'])
                        ->setTimezone((string) config('app.timezone', 'UTC'));
                }
            } catch (\Throwable) {
                // A damaged metadata file must not prevent the rules page
                // from loading; the model status is simply omitted.
            }
        }

        // Older model artifacts may predate the trained_at metadata field.
        // Use the artifact timestamp as a safe fallback so the page still
        // tells administrators when the currently loaded model was produced.
        if ($aiTrainedAt === null) {
            $modelPath = (string) config('maintenance.attention_ai.model');
            $modelTimestamp = is_file($modelPath) ? @filemtime($modelPath) : false;
            if (is_int($modelTimestamp)) {
                $aiTrainedAt = CarbonImmutable::createFromTimestamp(
                    $modelTimestamp,
                    (string) config('app.timezone', 'UTC')
                );
            }
        }
        $locations = Location::query()
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name): string => $this->normalizeName((string) $name))
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $officeRecords = Office::query()
            ->with('location:id,name')
            ->orderBy('name')
            ->get(['id', 'location_id', 'name']);
        $officeOptionsByLocation = $officeRecords
            ->filter(fn ($officeRecord) => filled($officeRecord->location?->name))
            ->groupBy(fn ($officeRecord) => $this->normalizeName((string) $officeRecord->location->name))
            ->map(fn ($items) => $items->pluck('name')
                ->map(fn ($name): string => $this->normalizeName((string) $name))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all())
            ->all();
        $offices = $officeRecords
            ->when($location !== '', fn ($items) => $items->filter(
                fn ($availableOffice) => $this->sameName($availableOffice->location?->name, $location)
            ))
            ->pluck('name')
            ->map(fn ($name): string => $this->normalizeName((string) $name))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $allRecommendations = $loaded
            ? $this->attachPmPlanSchedules($maintenanceAttentionService->recommendations($mode))
            : collect();

        if ($loaded) {
            $locations = $locations
                ->merge($allRecommendations->pluck('location_name')->filter())
                ->unique()
                ->sort()
                ->values();

            // Some imported/legacy equipment has a registered deployment
            // reference even when no Office row exists in the office master
            // list. Include those pairs in the dependent Office options so a
            // selected Location does not hide valid recommendation rows.
            $allRecommendations
                ->filter(fn (array $item): bool => filled($item['location_name'] ?? null)
                    && filled($item['office_name'] ?? null))
                ->groupBy(fn (array $item): string => $this->normalizeName((string) $item['location_name']))
                ->each(function (Collection $items, string $recommendationLocation) use (&$officeOptionsByLocation): void {
                    $mapKey = collect(array_keys($officeOptionsByLocation))
                        ->first(fn (string $key): bool => $this->sameName($key, $recommendationLocation))
                        ?: $recommendationLocation;
                    $existing = $officeOptionsByLocation[$mapKey] ?? [];
                    $officeOptionsByLocation[$mapKey] = collect($existing)
                        ->merge($items->pluck('office_name')->map(fn ($name) => $this->normalizeName((string) $name)))
                        ->filter()
                        ->unique()
                        ->sort()
                        ->values()
                        ->all();
                });

            $recommendationOffices = $allRecommendations
                ->when($location !== '', fn ($items) => $items->filter(
                    fn (array $item): bool => $this->sameName($item['location_name'] ?? '', $location)
                ))
                ->pluck('office_name')
                ->filter();
            $offices = $offices
                ->merge($recommendationOffices)
                ->unique()
                ->sort()
                ->values();
        }

        // Prevent a stale office query value from filtering against a
        // different location after the parent location changes.
        if ($office !== '' && ! $offices->contains(
            fn ($availableOffice): bool => $this->sameName((string) $availableOffice, $office)
        )) {
            $office = '';
        }

        $filteredRecommendations = $this->filterRecommendations(
            $allRecommendations,
            $location,
            $office,
            $q,
            $attention,
            $equipmentTypes,
            $condition,
            $status,
            $priority
        );
        $filteredRecommendations = $this->filterByMaintenancePeriod($filteredRecommendations, $year, $semester);
        $filteredRecommendations = $this->prepareReportRows($filteredRecommendations);
        $reviewCount = $filteredRecommendations->where('score', '>=', 25)->count();
        $page = LengthAwarePaginator::resolveCurrentPage('page');

        $recommendations = new LengthAwarePaginator(
            $filteredRecommendations->forPage($page, $perPage)->values(),
            $filteredRecommendations->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => $request->query(),
            ]
        );
        $selectedYear = $year ?? (int) now()->year;
        $selectedSemester = $semester ?? (now()->month <= 6 ? 1 : 2);

        return view('admin.maintenance-attention.index', compact(
            'recommendations',
            'reviewCount',
            'perPage',
            'locations',
            'offices',
            'officeOptionsByLocation',
            'location',
            'office',
            'attention',
            'priority',
            'priorityOptions',
            'equipmentTypes',
            'equipmentTypeOptions',
            'condition',
            'conditionOptions',
            'status',
            'statusOptions',
            'q',
            'year',
            'semester',
            'selectedYear',
            'selectedSemester',
            'loaded',
            'mode',
            'aiMetadata',
            'aiTrainedAt'
        ));
    }

    /**
     * Export the currently filtered Maintenance Attention recommendations as
     * a landscape PDF. The report is deliberately generated from the same
     * collection used by the page so Location/Office and text filters stay in
     * sync and pagination never limits the export.
     */
    public function exportPdf(Request $request, MaintenanceAttentionService $maintenanceAttentionService)
    {
        $data = $this->exportData($request, $maintenanceAttentionService);

        return Pdf::loadView('admin.reports.maintenance-attention-pdf', $data)
            ->setPaper([0, 0, 936, 612])
            ->stream('maintenance-attention-' . now()->format('Ymd-His') . '.pdf');
    }

    /**
     * Export the currently filtered Maintenance Attention recommendations as
     * an XLSX workbook. All matching records are exported, not only the
     * visible page.
     */
    public function exportExcel(Request $request, MaintenanceAttentionService $maintenanceAttentionService)
    {
        $data = $this->exportData($request, $maintenanceAttentionService);
        $response = Excel::download(
            new MaintenanceAttentionExport($data),
            'maintenance-attention-' . now()->format('Ymd-His') . '.xlsx'
        );

        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        return $response;
    }

    /**
     * Prepare the shared export payload. This intentionally does not use the
     * page's paginator: exports must include every record matching the
     * selected filters.
     */
    private function exportData(Request $request, MaintenanceAttentionService $maintenanceAttentionService): array
    {
        $location = $this->normalizeName((string) $request->query('location', ''));
        $office = $this->normalizeName((string) $request->query('office', ''));
        $attention = trim((string) $request->query('attention', ''));
        $priorityOptions = ['Critical', 'High', 'Medium', 'Low'];
        $priority = $this->filterKey((string) $request->query('priority', ''));
        $priority = in_array($priority, array_map([$this, 'filterKey'], $priorityOptions), true) ? $priority : '';
        $equipmentTypes = $this->normalizeMultiValue($request->query('equipment_types', []));
        $equipmentTypes = array_values(array_intersect(
            $equipmentTypes,
            array_map([$this, 'filterKey'], ['Desktop', 'Laptop', 'Printer', 'Monitor', 'UPS'])
        ));
        $condition = $this->filterKey((string) $request->query('condition', ''));
        $condition = in_array($condition, ['serviceable', 'unserviceable', 'condemned'], true) ? $condition : '';
        $status = $this->filterKey((string) $request->query('status', ''));
        $status = in_array($status, ['available', 'issued', 'repair', 'not_in_use'], true) ? $status : '';
        $q = trim((string) $request->query('q', ''));
        $year = $this->validMaintenanceYear($request->query('year'));
        $semester = $this->validMaintenanceSemester($request->query('semester'));
        if ($year === null) {
            $semester = null;
        }
        $mode = MaintenanceAttentionService::normalizeMode(
            (string) SystemSetting::getValue(MaintenanceAttentionService::MODE_SETTING_KEY, 'hybrid')
        );

        $rows = $this->filterRecommendations(
            $this->attachPmPlanSchedules($maintenanceAttentionService->recommendations($mode)),
            $location,
            $office,
            $q,
            $attention,
            $equipmentTypes,
            $condition,
            $status,
            $priority
        );
        $rows = $this->filterByMaintenancePeriod($rows, $year, $semester);
        $rows = $this->prepareReportRows($rows);

        return [
            'rows' => $rows,
            'filters' => [
                'location' => $location,
                'office' => $office,
                'attention' => $attention,
                'priority' => $priority,
                'equipment_types' => $equipmentTypes,
                'condition' => $condition,
                'status' => $status,
                'q' => $q,
                'year' => $year,
                'semester' => $semester,
            ],
            'mode' => $mode,
            'generatedAt' => now(),
            // Resolve the acknowledgement signer from the same filtered rows
            // that are exported. This keeps the Dean/Head of Unit in sync
            // when the location, office, search, or attention filter changes.
            'signatories' => $this->signatories($request, $rows),
            'logoPath' => public_path('images/catsu-logo.png'),
            'isoLogoPath' => public_path('images/iso-9001-2015.jpg'),
        ];
    }

    /**
     * Resolve report signatories from registered accounts. Signatures are
     * represented by a clearly labelled blank line above each printed name;
     * this avoids inventing a signature image where the account has none.
     */
    private function signatories(Request $request, ?Collection $rows = null): array
    {
        $currentUser = $request->user();
        $admin = $currentUser?->role === User::ROLE_ADMIN
            ? $currentUser
            : User::query()->where('role', User::ROLE_ADMIN)->orderBy('id')->first();

        $location = $this->normalizeName((string) $request->query('location', ''));
        $office = $this->normalizeName((string) $request->query('office', ''));

        // Staff assignment is office-scoped. Build the office set from the
        // exported rows first so text/attention filters also affect the
        // acknowledgement signer. When there are several different heads,
        // leave the name blank rather than displaying an unrelated account.
        $officeQuery = Office::query()->with(['location', 'responsibleStaff']);
        $rowPairs = collect($rows instanceof Collection ? $rows : [])->map(
            fn (array $row): array => [
                trim((string) ($row['location_name'] ?? '')),
                trim((string) ($row['office_name'] ?? '')),
            ]
        )->filter(fn (array $pair): bool => $pair[0] !== '' || $pair[1] !== '')
            ->unique(fn (array $pair): string => $pair[0] . "\0" . $pair[1])
            ->values();

        if ($rowPairs->isNotEmpty()) {
            $officeQuery->where(function ($query) use ($rowPairs) {
                foreach ($rowPairs as $index => [$rowLocation, $rowOffice]) {
                    $clause = fn ($officeQuery) => $officeQuery
                        ->when($rowOffice !== '', fn ($officeQuery) => $officeQuery->where('name', $rowOffice))
                        ->when($rowLocation !== '', fn ($officeQuery) => $officeQuery->whereHas(
                            'location',
                            fn ($locationQuery) => $locationQuery->where('name', $rowLocation)
                        ));

                    $index === 0 ? $query->where($clause) : $query->orWhere($clause);
                }
            });
        } elseif ($office !== '' || $location !== '') {
            $officeQuery
                ->when($office !== '', fn ($query) => $query->where('name', $office))
                ->when($location !== '', fn ($query) => $query->whereHas(
                    'location',
                    fn ($locationQuery) => $locationQuery->where('name', $location)
                ));
        } else {
            // An unfiltered report can contain many offices and therefore has
            // no single valid Dean/Head of Unit for its acknowledgement line.
            $officeQuery->whereRaw('1 = 0');
        }

        $offices = $officeQuery->get();
        $responsibleStaff = $offices
            ->pluck('responsibleStaff')
            ->filter()
            ->unique('id')
            ->values();
        $head = $responsibleStaff->count() === 1 ? $responsibleStaff->first() : null;
        $headOffice = $head
            ? $offices->first(fn (Office $availableOffice): bool => (bool) ($availableOffice->responsibleStaff?->is($head)))
            : null;
        $headTitle = ($headOffice ?: ($offices->count() === 1 ? $offices->first() : null))?->responsibleTitle();
        if (! $headTitle) {
            $headTitle = str_contains(strtolower($location), 'college') ? 'Dean' : 'Head of Unit';
        }

        // The certifying signer is the configured Unit Head account. Prefer
        // that role so a similarly titled Admin account cannot be selected by
        // accident; the position-based fallback keeps older installations
        // working when the role was not yet assigned.
        $itOfficer = User::query()
            ->where('role', User::ROLE_UNIT_HEAD)
            ->whereNotNull('position')
            ->where('position', '<>', '')
            ->orderBy('id')
            ->first()
            ?: User::query()
            ->where(function ($query) {
                $query->where('position', 'like', '%Information Technology Officer I%')
                    ->orWhere('position', 'like', '%IT Officer I%')
                    ->orWhere('position', 'like', '%IT Officer - I%');
            })
            ->orderBy('id')
            ->first();

        return [
            'head' => $head,
            'head_title' => $headTitle,
            'admin' => $admin,
            'it_officer' => $itOfficer ?: $head,
        ];
    }

    /**
     * Apply the canonical page filters to a recommendation collection.
     */
    private function filterRecommendations(
        Collection $items,
        string $location,
        string $office,
        string $q,
        string $attention,
        array $equipmentTypes = [],
        string $condition = '',
        string $status = '',
        string $priority = ''
    ): Collection {
        return $items
            ->when($location !== '', fn ($available) => $available->filter(
                fn (array $item): bool => $this->sameName($item['location_name'] ?? '', $location)
            ))
            ->when($office !== '', fn ($available) => $available->filter(
                fn (array $item): bool => $this->sameName($item['office_name'] ?? '', $office)
            ))
            ->when($equipmentTypes !== [], fn ($available) => $available->filter(
                function (array $item) use ($equipmentTypes): bool {
                    // Prefer the canonical device type relation. The fallback
                    // keeps older recommendation payloads filterable when the
                    // display label is the only value available.
                    $typeName = $item['device']?->type?->name
                        ?: ($item['equipment_type'] ?? '');

                    return in_array($this->filterKey((string) $typeName), $equipmentTypes, true);
                }
            ))
            ->when($condition !== '', fn ($available) => $available->filter(
                fn (array $item): bool => $this->filterKey((string) ($item['condition'] ?? '')) === $condition
            ))
            ->when($status !== '', fn ($available) => $available->filter(
                fn (array $item): bool => $this->filterKey((string) ($item['status'] ?? '')) === $status
            ))
            ->when($priority !== '', fn ($available) => $available->filter(
                fn (array $item): bool => $this->filterKey((string) ($item['priority'] ?? '')) === $priority
            ))
            ->when($q !== '', function (Collection $available) use ($q) {
                $needle = strtolower($q);

                return $available->filter(function (array $item) use ($needle): bool {
                    $device = $item['device'];
                    $haystack = strtolower(implode(' ', array_filter([
                        $device->property_number,
                        $device->part_of_property_number,
                        $device->serial_number,
                        $device->brand,
                        $device->model,
                        $device->computer_name,
                        $device->mac_address,
                        $device->type?->name,
                        $item['location_name'] ?? null,
                        $item['office_name'] ?? null,
                        $item['condition'] ?? null,
                        $item['status'] ?? null,
                        $item['checklist_remarks'] ?? null,
                    ])));

                    return str_contains($haystack, $needle);
                });
            })
            ->when($attention !== '', fn ($available) => $available->filter(
                fn (array $item) => in_array($attention, $item['attention_flags'] ?? [], true)
            ))
            ->values();
    }

    /**
     * Normalize values used by the maintenance-attention report outputs.
     *
     * Unserviceable equipment is only reported with operational statuses that
     * are meaningful for follow-up (Available, Repair, or Not in Use). Any
     * other status is left blank instead of presenting a misleading issued
     * state. AI provenance/confidence text is deliberately excluded from the
     * recommendation column; the report remains an actionable, auditable
     * summary while the selected engine is still available on the page.
     */
    private function prepareReportRows(Collection $rows): Collection
    {
        $allowedUnserviceableStatuses = ['available', 'repair', 'not_in_use'];

        return $rows->map(function (array $row) use ($allowedUnserviceableStatuses): array {
            $condition = $this->filterKey((string) ($row['condition'] ?? ''));
            $status = $this->filterKey((string) ($row['status'] ?? ''));

            $row['report_status'] = $condition === 'unserviceable'
                && ! in_array($status, $allowedUnserviceableStatuses, true)
                ? ''
                : $status;

            $row['report_reasons'] = collect($row['reasons'] ?? [])
                ->map(fn ($reason) => trim((string) $reason))
                ->filter()
                ->reject(fn (string $reason): bool => preg_match(
                    '/\b(?:local\s+ai|ai\s+recommended|confidence)\b/i',
                    $reason
                ) === 1)
                ->values()
                ->all();

            if ($row['report_reasons'] === []) {
                $row['report_reasons'] = ['Review equipment condition and maintenance history'];
            }

            return $row;
        })->values();
    }

    /**
     * Attach the currently applicable published PM Plan window to each
     * recommendation. Office schedules take precedence over a location-wide
     * schedule, and the active window is preferred over the nearest upcoming
     * or most recent historical plan.
     */
    private function attachPmPlanSchedules(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $schedules = MaintenancePlanSchedule::query()
            ->with(['location', 'office', 'latestOverride'])
            ->orderByDesc('schedule_month_from')
            ->get();
        $today = CarbonImmutable::today();

        return $rows->map(function (array $row) use ($schedules, $today): array {
            $device = $row['device'];
            $assignment = $device->currentAssignment;
            $officeId = $assignment?->office_id
                ?: $assignment?->staff?->office_id
                ?: $device->deployedOffice?->id;
            $locationId = $assignment?->location_id
                ?: $assignment?->office?->location_id
                ?: $assignment?->staff?->office?->location_id
                ?: $device->deployedOffice?->location_id
                ?: $device->deployedLocation?->id;
            $officeName = trim((string) ($row['office_name'] ?? ''));
            $locationName = trim((string) ($row['location_name'] ?? ''));

            // Prefer an office-specific plan. If that office has no plan,
            // fall back to the location-wide plan so every report row can
            // still show the PM Plan schedule inherited from its location.
            $officeMatches = $officeId
                ? $schedules->filter(fn (MaintenancePlanSchedule $schedule): bool =>
                    (int) $schedule->office_id === (int) $officeId
                )->values()
                : ($officeName !== ''
                    ? $schedules->filter(fn (MaintenancePlanSchedule $schedule): bool =>
                        $schedule->office_id
                        && strcasecmp((string) $schedule->office?->name, $officeName) === 0
                        && ($locationName === '' || strcasecmp((string) $schedule->location?->name, $locationName) === 0)
                    )->values()
                    : collect());
            $matches = $officeMatches->isNotEmpty()
                ? $officeMatches
                : $schedules->filter(fn (MaintenancePlanSchedule $schedule): bool =>
                    ! $schedule->office_id
                    && (($locationId && (int) $schedule->location_id === (int) $locationId)
                        || ($locationName !== '' && strcasecmp((string) $schedule->location?->name, $locationName) === 0))
                )->values();

            $schedule = $matches
                ->filter(function (MaintenancePlanSchedule $candidate) use ($today): bool {
                    $from = $candidate->schedule_month_from ?: $candidate->scheduled_date;
                    $to = $candidate->schedule_month_to ?: $candidate->scheduled_date;

                    return $from && $to
                        && $from->startOfDay()->lte($today)
                        && $to->endOfMonth()->gte($today);
                })
                ->sortByDesc(fn (MaintenancePlanSchedule $candidate) =>
                    ($candidate->schedule_month_from ?: $candidate->scheduled_date)?->timestamp ?? 0
                )
                ->first()
                ?: $matches
                    ->filter(fn (MaintenancePlanSchedule $candidate) =>
                        ($candidate->schedule_month_from ?: $candidate->scheduled_date)?->startOfDay()->gte($today)
                    )
                    ->sortBy(fn (MaintenancePlanSchedule $candidate) =>
                        ($candidate->schedule_month_from ?: $candidate->scheduled_date)?->timestamp ?? PHP_INT_MAX
                    )
                    ->first()
                ?: $matches->first();

            $row['pm_schedule'] = $schedule ? $this->pmScheduleLabel($schedule) : '';
            $row['pm_schedule_windows'] = $matches
                ->flatMap(fn (MaintenancePlanSchedule $candidate): array => $this->pmScheduleWindows($candidate))
                ->values()
                ->all();

            return $row;
        })->values();
    }

    /**
     * Return original and override windows for period filtering. Keeping the
     * windows on each recommendation lets historical PM Plan periods remain
     * selectable even when the card displays the currently active schedule.
     *
     * @return array<int, array{from:string,to:string}>
     */
    private function pmScheduleWindows(MaintenancePlanSchedule $schedule): array
    {
        $windows = [];
        $addWindow = static function (mixed $from, mixed $to) use (&$windows): void {
            if (! $from) {
                return;
            }

            $fromDate = $from instanceof \Carbon\CarbonInterface
                ? $from->copy()->startOfDay()
                : CarbonImmutable::parse((string) $from)->startOfDay();
            $toDate = $to
                ? ($to instanceof \Carbon\CarbonInterface
                    ? $to->copy()->endOfDay()
                    : CarbonImmutable::parse((string) $to)->endOfDay())
                : $fromDate->endOfMonth();

            if ($toDate->lt($fromDate)) {
                [$fromDate, $toDate] = [$toDate->startOfDay(), $fromDate->endOfDay()];
            }

            $windows[] = ['from' => $fromDate->toDateString(), 'to' => $toDate->toDateString()];
        };

        $addWindow(
            $schedule->schedule_month_from ?: $schedule->scheduled_date,
            $schedule->schedule_month_to ?: $schedule->scheduled_date
        );

        if ($schedule->latestOverride) {
            $addWindow(
                $schedule->latestOverride->override_date ?: $schedule->latestOverride->override_month_from,
                $schedule->latestOverride->override_date ?: $schedule->latestOverride->override_month_to
            );
        }

        return $windows;
    }

    private function filterByMaintenancePeriod(Collection $items, ?int $year, ?int $semester): Collection
    {
        if ($year === null) {
            return $items;
        }

        $fromMonth = $semester === 2 ? 7 : 1;
        $toMonth = $semester === 1 ? 6 : 12;
        $periodFrom = CarbonImmutable::create($year, $fromMonth, 1)->startOfDay();
        $periodTo = CarbonImmutable::create($year, $toMonth, 1)->endOfMonth();

        return $items->filter(function (array $item) use ($periodFrom, $periodTo): bool {
            foreach (($item['pm_schedule_windows'] ?? []) as $window) {
                try {
                    $windowFrom = CarbonImmutable::parse((string) ($window['from'] ?? ''));
                    $windowTo = CarbonImmutable::parse((string) ($window['to'] ?? ''));
                } catch (\Throwable) {
                    continue;
                }

                if ($windowFrom->lte($periodTo) && $windowTo->gte($periodFrom)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    private function validMaintenanceYear(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $year = (int) $value;

        return $year >= 2000 && $year <= 2100 ? $year : null;
    }

    private function validMaintenanceSemester(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $semester = (int) $value;

        return in_array($semester, [1, 2], true) ? $semester : null;
    }

    private function pmScheduleLabel(MaintenancePlanSchedule $schedule): string
    {
        $from = $schedule->schedule_month_from ?: $schedule->scheduled_date;
        $to = $schedule->schedule_month_to ?: $schedule->scheduled_date;
        $original = $from && $to && $from->format('Y-m') !== $to->format('Y-m')
            ? $from->format('F Y') . ' - ' . $to->format('F Y')
            : ($from?->format('F Y') ?: '');

        $override = $schedule->latestOverride;
        if (! $override) {
            return $original;
        }

        $overrideFrom = $override->override_date ?: $override->override_month_from;
        $overrideTo = $override->override_date ?: $override->override_month_to;
        $overrideLabel = $overrideFrom && $overrideTo && $overrideFrom->format('Y-m') !== $overrideTo->format('Y-m')
            ? $overrideFrom->format('F Y') . ' - ' . $overrideTo->format('F Y')
            : ($overrideFrom?->format('m/d/Y') ?: '');

        if ($overrideLabel === '') {
            return $original;
        }

        // Keep the original PM Plan window visible, then show the temporary
        // override and its required reason so the report explains why the
        // due date differs from the published schedule.
        $reason = trim((string) ($override->reason ?? ''));
        $label = $original . "\nOverride: " . $overrideLabel;

        return $reason !== '' ? $label . "\nDue to: " . $reason : $label;
    }

    /**
     * Normalize a multi-select query value while accepting both repeated
     * query parameters and comma-separated links from older SPA URLs.
     *
     * @return array<int, string>
     */
    private function normalizeMultiValue(mixed $value): array
    {
        $values = is_array($value) ? $value : ($value === '' ? [] : explode(',', (string) $value));

        return collect($values)
            ->flatMap(fn ($item) => explode(',', (string) $item))
            ->map(fn ($item) => $this->filterKey((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function filterKey(string $value): string
    {
        return str_replace(['-', ' '], '_', strtolower(trim($value)));
    }

    /**
     * Normalize human-entered/imported location and office labels without
     * changing their display form. This prevents hidden tabs, repeated
     * spaces, or casing differences from making a valid filter miss rows.
     */
    private function normalizeName(?string $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim((string) $value));

        return is_string($normalized) ? $normalized : trim((string) $value);
    }

    private function sameName(?string $left, ?string $right): bool
    {
        $left = $this->normalizeName($left);
        $right = $this->normalizeName($right);

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($left, 'UTF-8') === mb_strtolower($right, 'UTF-8');
        }

        return strtolower($left) === strtolower($right);
    }

    /**
     * Change the recommendation engine globally. Only Super Admin may choose
     * the source because it affects what every reviewer sees.
     */
    public function updateMode(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'mode' => ['required', 'in:rules,ai,hybrid'],
        ]);

        SystemSetting::putValue(
            MaintenanceAttentionService::MODE_SETTING_KEY,
            MaintenanceAttentionService::normalizeMode($data['mode'])
        );

        return redirect()
            ->route('admin.maintenance-attention.index', ['reset' => 1])
            ->with('status', 'Maintenance attention recommendation mode updated.');
    }
}
