<?php

namespace App\Http\Controllers\Admin;

use App\Exports\IssuedEquipmentExport;
use App\Http\Controllers\Controller;
use App\Models\DeviceAssignment;
use App\Models\DeviceType;
use App\Models\Location;
use App\Models\Office;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class IssuanceController extends Controller
{
    public function index(Request $request)
    {
        $assignments = $this->issuedAssignmentsQuery($request)
            ->latest('issued_at')
            ->paginate(25)
            ->withQueryString();

        $locationId = ($request->integer('location_id') ?: $request->integer('college_id')) ?: null;

        return view('admin.issuance.index', [
            'assignments' => $assignments,
            'types' => DeviceType::orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(),
            'offices' => Office::with('location')
                ->when($locationId, fn ($query) => $query->where('location_id', $locationId))
                ->orderBy('name')
                ->get(),
            'q' => $request->string('q')->toString(),
            'selectedTypeId' => $request->integer('type_id') ?: null,
            'selectedLocationId' => $locationId,
            'selectedOfficeId' => $request->integer('office_id') ?: null,
        ]);
    }

    public function export(Request $request)
    {
        return Excel::download(
            new IssuedEquipmentExport($request->query()),
            'issued-equipment-' . now()->format('Y-m-d-His') . '.xlsx'
        );
    }

    public static function issuedAssignmentsQuery(Request|array $request): Builder
    {
        $input = $request instanceof Request ? $request->query() : $request;

        $q = trim((string) ($input['q'] ?? ''));
        $typeId = (int) ($input['type_id'] ?? 0) ?: null;
        $locationId = (int) (($input['location_id'] ?? null) ?: ($input['college_id'] ?? null)) ?: null;
        $officeId = (int) ($input['office_id'] ?? 0) ?: null;

        return DeviceAssignment::query()
            ->with([
                'device.type',
                'staff.office.location',
                'issuer',
            ])
            ->whereNull('returned_at')
            ->whereHas('device')
            ->whereHas('staff')
            ->when($typeId, function ($query) use ($typeId) {
                $query->whereHas('device', fn ($device) => $device->where('device_type_id', $typeId));
            })
            ->when($locationId, function ($query) use ($locationId) {
                $query->whereHas('staff.office', fn ($office) => $office->where('location_id', $locationId));
            })
            ->when($officeId, function ($query) use ($officeId) {
                $query->whereHas('staff', fn ($staff) => $staff->where('office_id', $officeId));
            })
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('remarks', 'like', "%{$q}%")
                        ->orWhereHas('device', function ($device) use ($q) {
                            $device->where('property_number', 'like', "%{$q}%")
                                ->orWhere('serial_number', 'like', "%{$q}%")
                                ->orWhere('computer_name', 'like', "%{$q}%")
                                ->orWhere('brand', 'like', "%{$q}%")
                                ->orWhere('model', 'like', "%{$q}%");
                        })
                        ->orWhereHas('staff', function ($staff) use ($q) {
                            $staff->where('first_name', 'like', "%{$q}%")
                                ->orWhere('last_name', 'like', "%{$q}%")
                                ->orWhere('position', 'like', "%{$q}%")
                                ->orWhere('email', 'like', "%{$q}%")
                                ->orWhereHas('office', function ($office) use ($q) {
                                    $office->where('name', 'like', "%{$q}%")
                                        ->orWhereHas('location', function ($location) use ($q) {
                                            $location->where('name', 'like', "%{$q}%")
                                                ->orWhere('code', 'like', "%{$q}%");
                                        });
                                });
                        });
                });
            });
    }
}
