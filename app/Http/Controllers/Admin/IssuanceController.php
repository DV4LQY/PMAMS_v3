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
        $tokens = self::searchTokens($q);
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
            ->when($tokens !== [], function ($query) use ($tokens) {
                foreach ($tokens as $token) {
                    $query->where(function ($sub) use ($token) {
                        $like = "%{$token}%";

                        $sub->where('remarks', 'like', $like)
                            ->orWhereHas('device', function ($device) use ($like) {
                                $device->where('property_number', 'like', $like)
                                    ->orWhere('serial_number', 'like', $like)
                                    ->orWhere('computer_name', 'like', $like)
                                    ->orWhere('brand', 'like', $like)
                                    ->orWhere('model', 'like', $like)
                                    ->orWhereHas('type', fn ($type) => $type->where('name', 'like', $like));
                            })
                            ->orWhereHas('staff', function ($staff) use ($like) {
                                $staff->where('first_name', 'like', $like)
                                    ->orWhere('last_name', 'like', $like)
                                    ->orWhere('position', 'like', $like)
                                    ->orWhere('email', 'like', $like)
                                    ->orWhereHas('office', function ($office) use ($like) {
                                        $office->where('name', 'like', $like)
                                            ->orWhereHas('location', function ($location) use ($like) {
                                                $location->where('name', 'like', $like)
                                                    ->orWhere('code', 'like', $like);
                                            });
                                    });
                            });
                    });
                }
            });
    }

    private static function searchTokens(string $value): array
    {
        return collect(preg_split('/\s+/', strtolower(trim($value)), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $token) => trim($token))
            ->filter(fn (string $token) => $token !== '')
            ->take(5)
            ->values()
            ->all();
    }
}
