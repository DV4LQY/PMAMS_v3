<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\DeviceMaintenanceRecord;
use App\Models\DeviceType;
use App\Models\Staff;
use Carbon\Carbon;
class DashboardController extends Controller
{
    public function index()
    {
        $totalDevices = Device::count();
        $availableDevices = Device::where('status', 'available')->count();
        $issuedDevices = Device::where('status', 'issued')->count();
        $repairDevices = Device::where('status', 'repair')->count();
        $notInUseDevices = Device::where('status', 'not_in_use')->count();
        $serviceableDevices = Device::where('condition', 'serviceable')->count();
        $unserviceableDevices = Device::where('condition', 'unserviceable')->count();
        $condemnedDevices = Device::where('condition', 'condemned')->count();

        $recentIssuedDevices = DeviceAssignment::query()
            ->with([
                'device.type',
                'staff.office.college',
                'office.location',
                'location',
            ])
            ->whereNull('returned_at')
            ->latest('issued_at')
            ->take(5)
            ->get();

        $recentMaintenanceRecords = DeviceMaintenanceRecord::query()
            ->with([
                'device.type',
                'checkedBy',
            ])
            ->latest('maintenance_date')
            ->latest('id')
            ->take(5)
            ->get();

        $allowedTypes = [
            'Desktop', 'Laptop', 'Printer',
            'Monitor', 'UPS', 'AVR', 'Scanner', 'Network Device', 'Other',
        ];

        foreach ($allowedTypes as $typeName) {
            DeviceType::firstOrCreate(
                ['name' => $typeName],
                ['slug' => strtolower(str_replace(' ', '-', $typeName))]
            );
        }

        $types = DeviceType::whereIn('name', $allowedTypes)
            ->get()
            ->sortBy(function ($type) use ($allowedTypes) {
                return array_search($type->name, $allowedTypes);
            })
            ->values();

        // --- Chart data ---

        $devicesByCondition = [
            'Serviceable' => $serviceableDevices,
            'Unserviceable' => $unserviceableDevices,
            'Condemned' => $condemnedDevices,
        ];

        $devicesByAvailability = [
            'Available' => $availableDevices,
            'Issued' => $issuedDevices,
        ];

        // Operational status is separate from the equipment condition chart.
        $devicesByStatus = [
            'Available' => $availableDevices,
            'Issued' => $issuedDevices,
            'Repair' => $repairDevices,
            'Not in Use' => $notInUseDevices,
        ];

        $devicesByType = Device::selectRaw('device_type_id, count(*) as total')
            ->with('type')
            ->groupBy('device_type_id')
            ->get()
            ->mapWithKeys(fn($d) => [$d->type?->name ?? 'Unknown' => $d->total]);

        $devicesByOffice = DeviceAssignment::with(['staff.office', 'office'])
            ->whereNotNull('issued_at')
            ->whereNull('returned_at')
            ->get()
            ->groupBy(fn($a) => ($a->office ?: $a->staff?->office)?->name ?? 'No Office')
            ->map->count();

        $endUsersByLocation = Staff::query()
            ->where('is_active', true)
            ->with('office.location')
            ->get()
            ->groupBy(function (Staff $staff) {
                $location = $staff->office?->location;

                if (! $location) {
                    return 'No Location';
                }

                return $location->code
                    ? $location->code . ' - ' . $location->name
                    : $location->name;
            })
            ->map->count()
            ->sortDesc();

        $maintenanceSemiannually = DeviceMaintenanceRecord::query()
            ->whereNotNull('maintenance_date')
            ->get(['maintenance_date'])
            ->groupBy(function ($record) {
                $date = $record->maintenance_date instanceof Carbon
                    ? $record->maintenance_date
                    : Carbon::parse($record->maintenance_date);

                return $date->format('Y') . ' ' . ($date->month <= 6 ? 'Jan-Jun' : 'Jul-Dec');
            })
            ->sortKeys()
            ->map->count();

        return view('admin.dashboard', compact(
            'totalDevices',
            'availableDevices',
            'issuedDevices',
            'repairDevices',
            'notInUseDevices',
            'serviceableDevices',
            'unserviceableDevices',
            'condemnedDevices',
            'recentIssuedDevices',
            'recentMaintenanceRecords',
            'types',
            'devicesByCondition',
            'devicesByAvailability',
            'devicesByStatus',
            'devicesByType',
            'devicesByOffice',
            'endUsersByLocation',
            'maintenanceSemiannually',
        ));
    }
}
