<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Device;
use App\Models\DeviceMaintenanceRecord;
use App\Models\DeviceType;
use App\Models\Office;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.index');
    }

    public function assets(Request $request)
    {
        $devicesQuery = $this->filteredAssetsQuery($request);

        $devices = $devicesQuery
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.reports.assets', array_merge([
            'devices' => $devices,
            'selectedTypeId' => $request->integer('type_id'),
            'selectedLocationId' => ($request->integer('location_id') ?: $request->integer('college_id')),
            'selectedCollegeId' => ($request->integer('location_id') ?: $request->integer('college_id')), // backward-compatible variable for existing report views,
            'selectedOfficeId' => $request->integer('office_id'),
            'q' => $request->string('q')->toString(),
        ], $this->filterOptions(($request->integer('location_id') ?: $request->integer('college_id')) ?: null)));
    }

    public function accounts(Request $request)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $role = $request->query('role');
        $q = $request->string('q')->toString();

        if (! in_array($role, ['admin', 'custodian'], true)) {
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
            'adminCount' => User::where('role', 'admin')->count(),
            'custodianCount' => User::where('role', 'custodian')->count(),
        ]);
    }

    public function checkedEquipment(Request $request)
    {
        $checkerId = $request->integer('checker_id') ?: $request->integer('admin_id') ?: null;
        $typeId = $request->integer('type_id') ?: null;
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $q = $request->string('q')->toString();

        $records = $this->checkedEquipmentQuery($request)
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $checkerSummary = DeviceMaintenanceRecord::query()
            ->selectRaw('checked_by, COUNT(*) as total')
            ->whereNotNull('checked_by')
            ->with('checkedBy')
            ->groupBy('checked_by')
            ->orderByDesc('total')
            ->get();

        return view('admin.reports.checked-equipment', [
            'records' => $records,
            'adminSummary' => $checkerSummary,
            'checkerSummary' => $checkerSummary,
            'adminUsers' => User::orderBy('name')->get(),
            'checkerUsers' => User::orderBy('name')->get(),
            'types' => DeviceType::orderBy('name')->get(),
            'adminId' => $checkerId,
            'checkerId' => $checkerId,
            'typeId' => $typeId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'q' => $q,
        ]);
    }

    public function checkedEquipmentPdf(DeviceMaintenanceRecord $record)
    {
        $record->load([
            'device.type',
            'device.currentAssignment.staff.office.location',
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
                'checkedBy',
            ])
            ->whereNotNull('checked_by')
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
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $q = $request->string('q')->toString();

        return DeviceMaintenanceRecord::query()
            ->with([
                'device.type',
                'device.currentAssignment.staff.office.location',
                'checkedBy',
            ])
            ->whereNotNull('checked_by')
            ->when($checkerId, fn ($query) => $query->where('checked_by', $checkerId))
            ->when($typeId, function ($query) use ($typeId) {
                $query->whereHas('device', fn ($deviceQuery) => $deviceQuery->where('device_type_id', $typeId));
            })
            ->when($dateFrom, fn ($query) => $query->whereDate('maintenance_date', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('maintenance_date', '<=', $dateTo))
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('remarks', 'like', "%{$q}%")
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

    private function filteredAssetsQuery(Request $request)
    {
        $typeId = $request->integer('type_id') ?: null;
        $locationId = ($request->integer('location_id') ?: $request->integer('college_id')) ?: null;
        $officeId = $request->integer('office_id') ?: null;
        $q = $request->string('q')->toString();

        return Device::query()
            ->with([
                'type',
                'currentAssignment.staff.office.location',
                'latestMaintenanceRecord.checkedBy',
            ])
            ->when($typeId, fn ($query) => $query->where('device_type_id', $typeId))
            ->when($locationId, function ($query) use ($locationId) {
                $query->whereHas('currentAssignment.staff.office', function ($officeQuery) use ($locationId) {
                    $officeQuery->where('location_id', $locationId);
                });
            })
            ->when($officeId, function ($query) use ($officeId) {
                $query->whereHas('currentAssignment.staff', function ($staffQuery) use ($officeId) {
                    $staffQuery->where('office_id', $officeId);
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

    private function checklistItems(): array
    {
        return [
            'system_unit_power_on' => [
                'group' => 'System Unit',
                'label' => 'Check for<br>power on',
            ],
            'monitor_display' => [
                'group' => 'Monitor',
                'label' => 'Check<br>display',
            ],
            'keyboard_keys' => [
                'group' => 'Keyboard',
                'label' => 'Check for<br>keys',
            ],
            'mouse_buttons' => [
                'group' => 'Mouse',
                'label' => 'Check<br>mouse<br>left/right<br>buttons',
            ],
            'avr_ups_power_recovery' => [
                'group' => 'AVR/UPS',
                'label' => 'Check for<br>power<br>recovery',
            ],
            'printer_printout' => [
                'group' => 'Printer',
                'label' => 'Check<br>printout',
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
