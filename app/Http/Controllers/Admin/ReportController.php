<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AllAssetsExport;
use App\Exports\LinkedEquipmentMaintenanceExport;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Device;
use App\Models\DeviceMaintenanceRecord;
use App\Models\DeviceType;
use App\Models\Office;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.index');
    }

    public function assets(Request $request)
    {
        $loadReport = $this->shouldLoadReport($request, [
            'q',
            'type_id',
            'location_id',
            'college_id',
            'office_id',
        ]);

        $devices = $loadReport
            ? $this->filteredAssetsQuery($request)
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString()
            : $this->emptyReportPaginator($request);

        return view('admin.reports.assets', array_merge([
            'devices' => $devices,
            'loadReport' => $loadReport,
            'selectedTypeId' => $request->integer('type_id'),
            'selectedLocationId' => ($request->integer('location_id') ?: $request->integer('college_id')),
            'selectedCollegeId' => ($request->integer('location_id') ?: $request->integer('college_id')), // backward-compatible variable for existing report views,
            'selectedOfficeId' => $request->integer('office_id'),
            'q' => $request->string('q')->toString(),
        ], $this->filterOptions(($request->integer('location_id') ?: $request->integer('college_id')) ?: null)));
    }

    public function assetsExport(Request $request)
    {
        $filename = 'all-assets-' . now()->format('Y-m-d-His') . '.xlsx';

        return Excel::download(new AllAssetsExport($request->query()), $filename);
    }

    /**
     * Show linked equipment with its effective (inherited) maintenance date.
     *
     * A linked peripheral normally inherits the date from its standalone
     * parent checklist, so this report is intentionally device-based rather
     * than one-row-per-maintenance-record. That keeps one stable row per
     * current linked equipment while still allowing year/month filtering.
     */
    public function linkedEquipment(Request $request)
    {
        $loadReport = $this->shouldLoadReport($request, [
            'year',
            'month',
            'location_id',
            'location',
            'office_id',
            'q',
        ]);
        $filters = self::linkedEquipmentFilters($request);

        $devices = $loadReport
            ? self::linkedEquipmentQuery($filters)
                ->orderByRaw('COALESCE(part_of_property_number, property_number)')
                ->orderBy('property_number')
                ->paginate(25)
                ->withQueryString()
            : $this->emptyReportPaginator($request);

        $locationId = $filters['location_id'];

        return view('admin.reports.linked-equipment', [
            'devices' => $devices,
            'loadReport' => $loadReport,
            'filters' => $filters,
            'year' => $filters['year'],
            'month' => $filters['month'],
            'locationId' => $locationId,
            'officeId' => $filters['office_id'],
            'q' => $filters['q'],
            'locations' => Location::query()->orderBy('name')->get(['id', 'name', 'code']),
            'offices' => $locationId
                ? Office::query()->where('location_id', $locationId)->orderBy('name')->get(['id', 'location_id', 'name'])
                : collect(),
        ]);
    }

    /** Download the same linked-equipment result set as a formatted workbook. */
    public function linkedEquipmentExport(Request $request)
    {
        $filters = self::linkedEquipmentFilters($request);
        $year = $filters['year'] ?: 'all-years';
        $month = $filters['month'] ? str_pad((string) $filters['month'], 2, '0', STR_PAD_LEFT) : 'all-months';
        $filename = "linked-equipment-maintenance-{$year}-{$month}-" . now()->format('Y-m-d-His') . '.xlsx';

        return Excel::download(new LinkedEquipmentMaintenanceExport($filters), $filename);
    }

    public function accounts(Request $request)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $role = $request->query('role');
        $q = $request->string('q')->toString();

        if (! in_array($role, ['super_admin', 'admin', 'unit_head', 'custodian'], true)) {
            $role = null;
        }

        $users = User::query()
            ->when($role, fn ($query) => $query->where('role', $role))
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('role', 'like', "%{$q}%");
                });
            })
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.reports.accounts', [
            'users' => $users,
            'role' => $role,
            'q' => $q,
            'superAdminCount' => User::where('role', User::ROLE_SUPER_ADMIN)->count(),
            'adminCount' => User::where('role', 'admin')->count(),
            'unitHeadCount' => User::where('role', User::ROLE_UNIT_HEAD)->count(),
            'custodianCount' => User::where('role', 'custodian')->count(),
        ]);
    }

    public function checkedEquipment(Request $request)
    {
        $loadReport = $this->shouldLoadReport($request, [
            'checker_id',
            'admin_id',
            'type_id',
            'location_id',
            'office_id',
            'date_from',
            'date_to',
            'q',
        ]);
        $canViewAllCheckedReports = $this->canViewAllCheckedReports();
        $checkerId = $request->integer('checker_id') ?: $request->integer('admin_id') ?: null;
        if (! $canViewAllCheckedReports) {
            $checkerId = (int) auth()->id();
        }
        $typeId = $request->integer('type_id') ?: null;
        $locationId = $request->integer('location_id') ?: null;
        // Offices are scoped to the selected location. Ignore a stale office
        // value when the location filter has been cleared.
        $officeId = $locationId ? ($request->integer('office_id') ?: null) : null;
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $q = $request->string('q')->toString();

        $records = $loadReport
            ? $this->checkedEquipmentQuery($request)
                ->orderByDesc('maintenance_date')
                ->orderByDesc('id')
                // Keep the checked-equipment table readable and expose
                // pagination for normal report result sets.
                ->paginate(10)
                ->withQueryString()
            : $this->emptyReportPaginator($request);

        $checkerSummary = $loadReport
            ? DeviceMaintenanceRecord::query()
                ->selectRaw('checked_by, COUNT(*) as total')
                ->whereNotNull('checked_by')
                ->when(! $canViewAllCheckedReports, fn ($query) => $query->where('checked_by', auth()->id()))
                ->with('checkedBy')
                ->groupBy('checked_by')
                ->orderByDesc('total')
                ->get()
            : collect();

        return view('admin.reports.checked-equipment', [
            'records' => $records,
            'loadReport' => $loadReport,
            'adminSummary' => $checkerSummary,
            'checkerSummary' => $checkerSummary,
            'adminUsers' => $canViewAllCheckedReports ? User::orderBy('name')->get() : User::whereKey(auth()->id())->get(),
            'checkerUsers' => $canViewAllCheckedReports ? User::orderBy('name')->get() : User::whereKey(auth()->id())->get(),
            'canViewAllCheckedReports' => $canViewAllCheckedReports,
            'types' => DeviceType::orderBy('name')->get(),
            'adminId' => $checkerId,
            'checkerId' => $checkerId,
            'typeId' => $typeId,
            'locations' => Location::orderBy('name')->get(),
            'locationId' => $locationId,
            'offices' => Office::with('location')
                ->when($locationId, fn ($query) => $query->where('location_id', $locationId))
                ->orderBy('name')
                ->get(),
            'officeId' => $officeId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'q' => $q,
        ]);
    }

    public function checkedEquipmentPdf(DeviceMaintenanceRecord $record)
    {
        abort_unless($this->canViewCheckedRecord($record), 403);

        $record->load([
            'device.type',
            'device.currentAssignment.staff.office.location',
            'device.currentAssignment.office.location',
            'device.currentAssignment.location',
            'staff',
            'office',
            'location',
            'checkedBy',
        ]);

        abort_if(! $record->device, 404);

        $unitHead = User::where('role', User::ROLE_UNIT_HEAD)->first();

        $pdf = Pdf::loadView('admin.reports.checked-equipment-pdf', [
            'record' => $record,
            'device' => $record->device,
            'unitHead' => $unitHead,
            'checklistItems' => $this->checklistItems(),
            'softwareItems' => $this->softwareItems(),
        ])->setPaper([0, 0, 612, 936], 'landscape');

        $propertyNumber = preg_replace('/[^A-Za-z0-9_-]+/', '-', $record->device->property_number ?? 'device');
        $date = $record->maintenance_date?->format('Y-m-d') ?? now()->format('Y-m-d');

        return $pdf->stream("maintenance-checklist-{$propertyNumber}-{$date}.pdf");
    }

    public function checkedEquipmentPreview(DeviceMaintenanceRecord $record)
    {
        abort_unless($this->canViewCheckedRecord($record), 403);
        $record->load(['device.type', 'staff', 'office', 'location', 'checkedBy', 'photos']);
        abort_if(! $record->device, 404);

        return view('admin.reports.checked-equipment-preview', [
            'record' => $record,
            'device' => $record->device,
            'checklistItems' => $this->checklistItems(),
            'softwareItems' => $this->softwareItems(),
        ]);
    }

    public function checkedEquipmentFilteredPdf(Request $request)
    {
        $records = $this->checkedEquipmentQuery($request)
            ->orderBy('maintenance_date')
            ->orderBy('id')
            ->get();

        if ($records->isEmpty()) {
            return back()->withErrors([
                'report' => 'No maintenance checklist records match the selected filters.',
            ]);
        }

        $unitHead = User::where('role', User::ROLE_UNIT_HEAD)->first();

        $pdf = Pdf::loadView('admin.reports.checked-equipment-pdf', [
            'records' => $records,
            'unitHead' => $unitHead,
            'checklistItems' => $this->checklistItems(),
            'softwareItems' => $this->softwareItems(),
        ])->setPaper([0, 0, 612, 936], 'landscape');

        $datePart = collect([$request->query('date_from'), $request->query('date_to')])
            ->filter()
            ->join('-to-') ?: now()->format('Y-m-d');

        return $pdf->stream("maintenance-checklists-filtered-{$datePart}.pdf");
    }

    public function checkedEquipmentSelectedPdf(Request $request)
    {
        $data = $request->validate([
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer', 'exists:device_maintenance_records,id'],
        ], [
            'record_ids.required' => 'Please select at least one checked equipment record to print.',
            'record_ids.min' => 'Please select at least one checked equipment record to print.',
        ]);

        $records = DeviceMaintenanceRecord::query()
            ->with([
                'device.type',
                'device.currentAssignment.staff.office.location',
                'device.currentAssignment.office.location',
                'device.currentAssignment.location',
                'staff',
                'office',
                'location',
                'checkedBy',
            ])
            ->whereHas('device')
            ->whereNotNull('checked_by')
            ->when(! $this->canViewAllCheckedReports(), fn ($query) => $query->where('checked_by', auth()->id()))
            ->whereIn('id', $data['record_ids'])
            ->orderBy('maintenance_date')
            ->orderBy('id')
            ->get();

        abort_if($records->isEmpty(), 404);

        $unitHead = User::where('role', User::ROLE_UNIT_HEAD)->first();

        $pdf = Pdf::loadView('admin.reports.checked-equipment-pdf', [
            'records' => $records,
            'unitHead' => $unitHead,
            'checklistItems' => $this->checklistItems(),
            'softwareItems' => $this->softwareItems(),
        ])->setPaper([0, 0, 612, 936], 'landscape');

        return $pdf->stream('maintenance-checklists-selected-' . now()->format('Y-m-d-His') . '.pdf');
    }

    public function checklist(Request $request)
    {
        $devices = $this->filteredAssetsQuery($request)
            ->orderBy('property_number')
            ->get();

        return view('admin.reports.checklist', array_merge([
            'devices' => $devices,
            'selectedTypeId' => $request->integer('type_id'),
            'selectedLocationId' => ($request->integer('location_id') ?: $request->integer('college_id')),
            'selectedCollegeId' => ($request->integer('location_id') ?: $request->integer('college_id')), // backward-compatible variable for existing report views,
            'selectedOfficeId' => $request->integer('office_id'),
            'q' => $request->string('q')->toString(),
            'generatedAt' => now(),
        ], $this->filterOptions(($request->integer('location_id') ?: $request->integer('college_id')) ?: null)));
    }

    /**
     * Normalize the linked-equipment report filters in one place so the HTML
     * report and its Excel export always use the same scope.
     *
     * The office filter is only valid under its selected Location. Clearing
     * the Location therefore also clears a stale office id, matching the
     * dependent filter behavior used by Equipment and other reports.
     */
    public static function linkedEquipmentFilters(Request|array $request): array
    {
        $input = $request instanceof Request ? $request->query() : $request;

        $year = is_scalar($input['year'] ?? null) ? (int) $input['year'] : 0;
        $year = $year >= 2000 && $year <= 2100 ? $year : null;

        $month = is_scalar($input['month'] ?? null) ? (int) $input['month'] : 0;
        $month = $month >= 1 && $month <= 12 ? $month : null;

        $locationId = is_scalar($input['location_id'] ?? null) ? (int) $input['location_id'] : 0;
        if (! $locationId && is_scalar($input['location'] ?? null)) {
            $locationId = (int) $input['location'];
        }
        if (! $locationId && is_scalar($input['college_id'] ?? null)) {
            $locationId = (int) $input['college_id'];
        }
        $locationId = $locationId > 0 ? $locationId : null;

        $officeId = is_scalar($input['office_id'] ?? null) ? (int) $input['office_id'] : 0;
        $officeId = $officeId > 0 ? $officeId : null;

        if (! $locationId) {
            $officeId = null;
        } elseif ($officeId && ! Office::query()
            ->whereKey($officeId)
            ->where('location_id', $locationId)
            ->exists()) {
            $officeId = null;
        }

        $q = is_scalar($input['q'] ?? null) ? trim((string) $input['q']) : '';
        if (mb_strlen($q) > 255) {
            $q = mb_substr($q, 0, 255);
        }

        return [
            'year' => $year,
            'month' => $month,
            'location_id' => $locationId,
            'office_id' => $officeId,
            'q' => $q,
        ];
    }

    /**
     * Build the current linked-equipment inventory query.
     *
     * Linked peripherals inherit a parent's checklist date into their own
     * last_maintenance_date. Parent history remains a fallback for older rows
     * that pre-date that synchronization, so date filters remain useful for
     * both current and legacy equipment.
     */
    public static function linkedEquipmentQuery(Request|array $request): Builder
    {
        $filters = self::linkedEquipmentFilters($request);
        $year = $filters['year'];
        $month = $filters['month'];
        $locationId = $filters['location_id'];
        $officeId = $filters['office_id'];
        $terms = self::linkedEquipmentSearchTerms($filters['q']);

        $query = Device::query()
            ->whereNotNull('part_of_property_number')
            ->where('part_of_property_number', '<>', '')
            ->with([
                'type',
                'deployedLocation',
                'deployedOffice.location',
                'currentAssignment.staff.office.location',
                'currentAssignment.office.location',
                'currentAssignment.location',
                'latestMaintenanceRecord',
                'parentProperty.type',
                'parentProperty.deployedLocation',
                'parentProperty.deployedOffice.location',
                'parentProperty.currentAssignment.staff.office.location',
                'parentProperty.currentAssignment.office.location',
                'parentProperty.currentAssignment.location',
                'parentProperty.latestMaintenanceRecord',
            ]);

        if ($year || $month) {
            $query->where(function (Builder $dateScope) use ($year, $month): void {
                $dateScope
                    ->where(function (Builder $deviceDate) use ($year, $month): void {
                        self::applyMaintenanceDateParts($deviceDate, $year, $month, 'last_maintenance_date');
                    })
                    ->orWhereHas('maintenanceRecords', function (Builder $recordQuery) use ($year, $month): void {
                        self::applyMaintenanceDateParts($recordQuery, $year, $month, 'maintenance_date');
                    })
                    ->orWhereHas('parentProperty', function (Builder $parentQuery) use ($year, $month): void {
                        $parentQuery
                            ->where(function (Builder $parentDate) use ($year, $month): void {
                                self::applyMaintenanceDateParts($parentDate, $year, $month, 'last_maintenance_date');
                            })
                            ->orWhereHas('maintenanceRecords', function (Builder $recordQuery) use ($year, $month): void {
                                self::applyMaintenanceDateParts($recordQuery, $year, $month, 'maintenance_date');
                            });
                    });
            });
        }

        if ($locationId) {
            $query->where(function (Builder $locationScope) use ($locationId): void {
                $locationScope
                    ->whereHas('currentAssignment', function (Builder $assignmentQuery) use ($locationId): void {
                        self::whereAssignmentMatchesLocation($assignmentQuery, $locationId);
                    })
                    ->orWhereHas('parentProperty.currentAssignment', function (Builder $assignmentQuery) use ($locationId): void {
                        self::whereAssignmentMatchesLocation($assignmentQuery, $locationId);
                    })
                    ->orWhereHas('deployedLocation', fn (Builder $locationQuery) => $locationQuery->whereKey($locationId))
                    ->orWhereHas('deployedOffice', fn (Builder $officeQuery) => $officeQuery->where('location_id', $locationId))
                    ->orWhereHas('parentProperty.deployedLocation', fn (Builder $locationQuery) => $locationQuery->whereKey($locationId))
                    ->orWhereHas('parentProperty.deployedOffice', fn (Builder $officeQuery) => $officeQuery->where('location_id', $locationId));
            });
        }

        if ($officeId) {
            $query->where(function (Builder $officeScope) use ($officeId): void {
                $officeScope
                    ->whereHas('currentAssignment', function (Builder $assignmentQuery) use ($officeId): void {
                        self::whereAssignmentMatchesOffice($assignmentQuery, $officeId);
                    })
                    ->orWhereHas('parentProperty.currentAssignment', function (Builder $assignmentQuery) use ($officeId): void {
                        self::whereAssignmentMatchesOffice($assignmentQuery, $officeId);
                    })
                    ->orWhere('office_deployed_id', $officeId)
                    ->orWhereHas('parentProperty', fn (Builder $parentQuery) => $parentQuery->where('office_deployed_id', $officeId));
            });
        }

        foreach ($terms as $term) {
            $like = "%{$term}%";

            $query->where(function (Builder $searchScope) use ($like): void {
                $searchScope
                    ->where('property_number', 'like', $like)
                    ->orWhere('part_of_property_number', 'like', $like)
                    ->orWhere('serial_number', 'like', $like)
                    ->orWhere('computer_name', 'like', $like)
                    ->orWhere('brand', 'like', $like)
                    ->orWhere('model', 'like', $like)
                    ->orWhere('network_device_type', 'like', $like)
                    ->orWhere('location_deployed', 'like', $like)
                    ->orWhere('mac_address', 'like', $like)
                    ->orWhere('maintenance_remarks', 'like', $like)
                    ->orWhereHas('type', fn (Builder $typeQuery) => $typeQuery->where('name', 'like', $like))
                    ->orWhereHas('deployedLocation', function (Builder $locationQuery) use ($like): void {
                        $locationQuery->where('name', 'like', $like)->orWhere('code', 'like', $like);
                    })
                    ->orWhereHas('deployedOffice', function (Builder $officeQuery) use ($like): void {
                        $officeQuery->where('name', 'like', $like)
                            ->orWhereHas('location', function (Builder $locationQuery) use ($like): void {
                                $locationQuery->where('name', 'like', $like)->orWhere('code', 'like', $like);
                            });
                    })
                    ->orWhereHas('currentAssignment', function (Builder $assignmentQuery) use ($like): void {
                        self::whereAssignmentMatchesSearch($assignmentQuery, $like);
                    })
                    ->orWhereHas('maintenanceRecords', function (Builder $recordQuery) use ($like): void {
                        $recordQuery->where('remarks', 'like', $like)
                            ->orWhere('corrective_action', 'like', $like);
                    })
                    ->orWhereHas('parentProperty', function (Builder $parentQuery) use ($like): void {
                        $parentQuery
                            ->where('property_number', 'like', $like)
                            ->orWhere('serial_number', 'like', $like)
                            ->orWhere('computer_name', 'like', $like)
                            ->orWhere('brand', 'like', $like)
                            ->orWhere('model', 'like', $like)
                            ->orWhere('network_device_type', 'like', $like)
                            ->orWhere('location_deployed', 'like', $like)
                            ->orWhere('mac_address', 'like', $like)
                            ->orWhere('maintenance_remarks', 'like', $like)
                            ->orWhereHas('type', fn (Builder $typeQuery) => $typeQuery->where('name', 'like', $like))
                            ->orWhereHas('deployedLocation', function (Builder $locationQuery) use ($like): void {
                                $locationQuery->where('name', 'like', $like)->orWhere('code', 'like', $like);
                            })
                            ->orWhereHas('deployedOffice', function (Builder $officeQuery) use ($like): void {
                                $officeQuery->where('name', 'like', $like)
                                    ->orWhereHas('location', function (Builder $locationQuery) use ($like): void {
                                        $locationQuery->where('name', 'like', $like)->orWhere('code', 'like', $like);
                                    });
                            })
                            ->orWhereHas('currentAssignment', function (Builder $assignmentQuery) use ($like): void {
                                self::whereAssignmentMatchesSearch($assignmentQuery, $like);
                            })
                            ->orWhereHas('maintenanceRecords', function (Builder $recordQuery) use ($like): void {
                                $recordQuery->where('remarks', 'like', $like)
                                    ->orWhere('corrective_action', 'like', $like);
                            });
                    });
            });
        }

        return $query;
    }

    /** Build the values shown for one linked-equipment report row. */
    public static function linkedEquipmentRow(Device $device): array
    {
        $parent = $device->parentProperty;
        $childAssignment = $device->currentAssignment;
        $parentAssignment = $parent?->currentAssignment;
        $childStaff = $childAssignment?->staff;
        $parentStaff = $parentAssignment?->staff;
        $staff = $childStaff ?: $parentStaff;

        $office = $childAssignment?->office
            ?: $childStaff?->office
            ?: $parentAssignment?->office
            ?: $parentStaff?->office
            ?: $device->deployedOffice
            ?: $parent?->deployedOffice;

        $location = $childAssignment?->location
            ?: $childAssignment?->office?->location
            ?: $childStaff?->office?->location
            ?: $parentAssignment?->location
            ?: $parentAssignment?->office?->location
            ?: $parentStaff?->office?->location
            ?: $device->deployedLocation
            ?: $device->deployedOffice?->location
            ?: $parent?->deployedLocation
            ?: $parent?->deployedOffice?->location;

        $staffName = $staff
            ? trim(($staff->last_name ?? '') . ', ' . ($staff->first_name ?? ''))
            : ($childAssignment?->location || $parentAssignment?->location ? 'Location assignment' : null);

        $maintenanceDate = $device->effectiveLastMaintenanceDate();
        $maintenanceRemarks = filled($device->maintenance_remarks)
            ? $device->maintenance_remarks
            : ($device->latestMaintenanceRecord?->remarks
                ?: $parent?->maintenance_remarks
                ?: $parent?->latestMaintenanceRecord?->remarks);

        return [
            'device' => $device,
            'parent' => $parent,
            'property_number' => $device->property_number,
            'parent_property_number' => $device->part_of_property_number ?: $parent?->property_number,
            'equipment_type' => $device->type?->name,
            'serial_number' => $device->serial_number,
            'brand_model' => trim(($device->brand ?? '') . ' ' . ($device->model ?? '')),
            'maintenance_date' => $maintenanceDate,
            'maintenance_source' => $parent ? 'Parent checklist (inherited)' : 'Equipment record',
            'staff_name' => $staffName,
            'office_name' => $office?->name,
            'location_name' => $location?->name,
            'condition' => $device->condition,
            'status' => $device->status,
            'maintenance_remarks' => $maintenanceRemarks,
        ];
    }

    private static function linkedEquipmentSearchTerms(string $query): array
    {
        return collect(preg_split('/\s+/', strtolower(trim($query)), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $term) => trim($term))
            ->filter(fn (string $term) => $term !== '')
            ->take(5)
            ->values()
            ->all();
    }

    private static function applyMaintenanceDateParts(Builder $query, ?int $year, ?int $month, string $column): void
    {
        if ($year) {
            $query->whereYear($column, $year);
        }

        if ($month) {
            $query->whereMonth($column, $month);
        }
    }

    private static function whereAssignmentMatchesLocation(Builder $query, int $locationId): void
    {
        $query->where(function (Builder $scope) use ($locationId): void {
            $scope
                ->where('location_id', $locationId)
                ->orWhereHas('office', fn (Builder $officeQuery) => $officeQuery->where('location_id', $locationId))
                ->orWhereHas('staff.office', fn (Builder $officeQuery) => $officeQuery->where('location_id', $locationId));
        });
    }

    private static function whereAssignmentMatchesOffice(Builder $query, int $officeId): void
    {
        $query->where(function (Builder $scope) use ($officeId): void {
            $scope
                ->where('office_id', $officeId)
                ->orWhereHas('staff', fn (Builder $staffQuery) => $staffQuery->where('office_id', $officeId));
        });
    }

    private static function whereAssignmentMatchesSearch(Builder $query, string $like): void
    {
        $query->where(function (Builder $scope) use ($like): void {
            $scope
                ->whereHas('location', function (Builder $locationQuery) use ($like): void {
                    $locationQuery->where('name', 'like', $like)->orWhere('code', 'like', $like);
                })
                ->orWhereHas('office', function (Builder $officeQuery) use ($like): void {
                    $officeQuery->where('name', 'like', $like)
                        ->orWhereHas('location', function (Builder $locationQuery) use ($like): void {
                            $locationQuery->where('name', 'like', $like)->orWhere('code', 'like', $like);
                        });
                })
                ->orWhereHas('staff', function (Builder $staffQuery) use ($like): void {
                    $staffQuery
                        ->where(function (Builder $nameQuery) use ($like): void {
                            $nameQuery->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like);
                        })
                        ->orWhere('email', 'like', $like)
                        ->orWhere('position', 'like', $like)
                        ->orWhereHas('office', function (Builder $officeQuery) use ($like): void {
                            $officeQuery->where('name', 'like', $like)
                                ->orWhereHas('location', function (Builder $locationQuery) use ($like): void {
                                    $locationQuery->where('name', 'like', $like)->orWhere('code', 'like', $like);
                                });
                        });
                });
        });
    }

    private function checkedEquipmentQuery(Request $request)
    {
        $checkerId = $request->integer('checker_id') ?: $request->integer('admin_id') ?: null;
        $typeId = $request->integer('type_id') ?: null;
        $locationId = $request->integer('location_id') ?: null;
        $officeId = $locationId ? ($request->integer('office_id') ?: null) : null;
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $q = $request->string('q')->toString();

        return DeviceMaintenanceRecord::query()
            ->with([
                'device.type',
                'device.currentAssignment.staff.office.location',
                'device.currentAssignment.office.location',
                'device.currentAssignment.location',
                'staff',
                'office',
                'location',
                'checkedBy',
            ])
            ->whereHas('device')
            ->whereNotNull('checked_by')
            ->when(! $this->canViewAllCheckedReports(), fn ($query) => $query->where('checked_by', auth()->id()))
            ->when($checkerId, fn ($query) => $query->where('checked_by', $checkerId))
            ->when($typeId, function ($query) use ($typeId) {
                $query->whereHas('device', fn ($deviceQuery) => $deviceQuery->where('device_type_id', $typeId));
            })
            ->when($locationId, function ($query) use ($locationId) {
                $query->where(function ($locationQuery) use ($locationId) {
                    $locationQuery->where('location_id', $locationId)
                        ->orWhere(function ($legacyQuery) use ($locationId) {
                            $legacyQuery->whereNull('location_id')
                                ->where(function ($locationSourceQuery) use ($locationId) {
                                    $locationSourceQuery
                                        ->whereHas('office', fn ($officeQuery) => $officeQuery->where('location_id', $locationId))
                                        ->orWhereHas('device.currentAssignment', function ($assignmentQuery) use ($locationId) {
                                            $assignmentQuery->where('location_id', $locationId)
                                                ->orWhereHas('staff.office', function ($officeQuery) use ($locationId) {
                                                    $officeQuery->where('location_id', $locationId);
                                                });
                                        });
                                });
                        });
                });
            })
            ->when($officeId, function ($query) use ($officeId) {
                $query->where(function ($officeQuery) use ($officeId) {
                    // Newer checklist rows keep the office snapshot on the
                    // history record. Legacy rows fall back to the current
                    // assignment or the saved staff member's office.
                    $officeQuery->where('office_id', $officeId)
                        ->orWhere(function ($legacyQuery) use ($officeId) {
                            $legacyQuery->whereNull('office_id')
                                ->where(function ($assignmentOrStaffQuery) use ($officeId) {
                                    $assignmentOrStaffQuery
                                        ->whereHas('device.currentAssignment', function ($assignmentQuery) use ($officeId) {
                                            $assignmentQuery->where('office_id', $officeId)
                                                ->orWhereHas('staff', fn ($staffQuery) => $staffQuery->where('office_id', $officeId));
                                        })
                                        ->orWhereHas('staff', fn ($staffQuery) => $staffQuery->where('office_id', $officeId));
                                });
                        });
                });
            })
            ->when($dateFrom, fn ($query) => $query->whereDate('maintenance_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('maintenance_date', '<=', $dateTo))
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('remarks', 'like', "%{$q}%")
                        ->orWhere('corrective_action', 'like', "%{$q}%")
                        ->orWhere('maintenance_type', 'like', "%{$q}%")
                        ->orWhereHas('device', function ($deviceQuery) use ($q) {
                            $deviceQuery->where('property_number', 'like', "%{$q}%")
                                ->orWhere('serial_number', 'like', "%{$q}%")
                                ->orWhere('brand', 'like', "%{$q}%")
                                ->orWhere('model', 'like', "%{$q}%");
                        });
                });
            });
    }

    /**
     * Unit heads and custodians can review every checklist. Regular admin
     * accounts are restricted to the checklists they personally submitted.
     */
    private function canViewAllCheckedReports(): bool
    {
        $user = auth()->user();

        return $user && ($user->isSuperAdmin() || $user->isUnitHead() || $user->isCustodian());
    }

    private function canViewCheckedRecord(DeviceMaintenanceRecord $record): bool
    {
        return $this->canViewAllCheckedReports()
            || (int) $record->checked_by === (int) auth()->id();
    }

    public static function assetsQuery(Request|array $request)
    {
        $input = $request instanceof Request ? $request->query() : $request;
        $typeId = (int) ($input['type_id'] ?? 0) ?: null;
        $locationId = ((int) ($input['location_id'] ?? 0) ?: (int) ($input['college_id'] ?? 0)) ?: null;
        $officeId = (int) ($input['office_id'] ?? 0) ?: null;
        $q = trim((string) ($input['q'] ?? ''));

        return Device::query()
            ->with([
                'type',
                'currentAssignment.staff.office.location',
                'currentAssignment.office.location',
                'currentAssignment.location',
                'latestMaintenanceRecord.checkedBy',
                'parentProperty',
            ])
            ->when($typeId, fn ($query) => $query->where('device_type_id', $typeId))
            ->when($locationId, function ($query) use ($locationId) {
                $query->whereHas('currentAssignment', function ($assignmentQuery) use ($locationId) {
                    $assignmentQuery->where('location_id', $locationId)
                        ->orWhereHas('office', function ($officeQuery) use ($locationId) {
                            $officeQuery->where('location_id', $locationId);
                        })
                        ->orWhereHas('staff.office', function ($officeQuery) use ($locationId) {
                            $officeQuery->where('location_id', $locationId);
                        });
                });
            })
            ->when($officeId, function ($query) use ($officeId) {
                $query->whereHas('currentAssignment', function ($assignmentQuery) use ($officeId) {
                    $assignmentQuery->where('office_id', $officeId)
                        ->orWhereHas('staff', function ($staffQuery) use ($officeId) {
                            $staffQuery->where('office_id', $officeId);
                        });
                });
            })
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('property_number', 'like', "%{$q}%")
                        ->orWhere('serial_number', 'like', "%{$q}%")
                        ->orWhere('brand', 'like', "%{$q}%")
                        ->orWhere('model', 'like', "%{$q}%")
                        ->orWhere('computer_name', 'like', "%{$q}%")
                        ->orWhere('mac_address', 'like', "%{$q}%");
                });
            });
    }

    private function filteredAssetsQuery(Request $request)
    {
        return self::assetsQuery($request);
    }

    private function filterOptions(?int $locationId = null): array
    {
        return [
            'types' => DeviceType::orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(),
            'colleges' => Location::orderBy('name')->get(), // backward-compatible variable for existing report views,
            'offices' => Office::with('location')
                ->when($locationId, fn ($query) => $query->where('location_id', $locationId))
                ->orderBy('name')
                ->get(),
        ];
    }

    /**
     * Reports deliberately start with no result query. A filter submission or
     * an explicit Reset (?load=1) opts in to loading the report data.
     */
    private function shouldLoadReport(Request $request, array $filterKeys): bool
    {
        return $request->boolean('load')
            || collect($filterKeys)->contains(fn (string $key) => $request->query->has($key));
    }

    private function emptyReportPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            [],
            0,
            25,
            1,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function checklistItems(): array
    {
        return [
            'system_unit_power_on' => [
                'group' => 'System Unit',
                'label' => 'Check for power on',
            ],
            'monitor_display' => [
                'group' => 'Monitor',
                'label' => 'Check display',
            ],
            'keyboard_keys' => [
                'group' => 'Keyboard',
                'label' => 'Check for keys',
            ],
            'mouse_buttons' => [
                'group' => 'Mouse',
                'label' => 'Check mouse left/right buttons',
            ],
            'avr_ups_power_recovery' => [
                'group' => 'AVR/UPS',
                'label' => 'Check for power recovery',
            ],
            'printer_printout' => [
                'group' => 'Printer',
                'label' => 'Check printout',
            ],
        ];
    }

    private function softwareItems(): array
    {
        return [
            'setup_antivirus' => 'Setup Anti-Virus',
            'system_scan_removal' => 'System Scan and Removal of Malicious Software',
        ];
    }
}
