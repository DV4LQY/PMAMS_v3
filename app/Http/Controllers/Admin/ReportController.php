<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AllAssetsExport;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Device;
use App\Models\DeviceMaintenanceRecord;
use App\Models\DeviceType;
use App\Models\Office;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
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
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $q = $request->string('q')->toString();

        $records = $loadReport
            ? $this->checkedEquipmentQuery($request)
                ->orderByDesc('maintenance_date')
                ->orderByDesc('id')
                ->paginate(25)
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

    private function checkedEquipmentQuery(Request $request)
    {
        $checkerId = $request->integer('checker_id') ?: $request->integer('admin_id') ?: null;
        $typeId = $request->integer('type_id') ?: null;
        $locationId = $request->integer('location_id') ?: null;
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
                                ->whereHas('device.currentAssignment', function ($assignmentQuery) use ($locationId) {
                                    $assignmentQuery->where('location_id', $locationId)
                                        ->orWhereHas('staff.office', function ($officeQuery) use ($locationId) {
                                            $officeQuery->where('location_id', $locationId);
                                        });
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
