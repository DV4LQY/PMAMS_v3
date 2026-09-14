<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\DeviceMaintenanceRecord;
use App\Models\DeviceType;
use App\Models\MaintenanceAttentionSnapshot;
use App\Models\MaintenancePlanSchedule;
use App\Models\Staff;
use App\Services\MaintenanceAttentionService;
use Carbon\Carbon;
class DashboardController extends Controller
{
    public function index(MaintenanceAttentionService $maintenanceAttentionService)
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

        // Keep maintenance coverage in one semiannual stacked-bar dataset.
        // Each equipment type gets its own color in the chart. A solid
        // segment means Maintained and a translucent segment means Not
        // Maintained for that type/window. Condemned equipment is excluded
        // from both segments so the chart remains actionable and its totals
        // reconcile with the active equipment inventory.
        $eligibleMaintenanceDevices = Device::query()
            ->where(function ($query) {
                $query->whereNull('condition')->orWhere('condition', '<>', 'condemned');
            })
            ->with([
                'type:id,name',
                'parentProperty:id,property_number,last_maintenance_date',
            ])
            ->get(['id', 'device_type_id', 'part_of_property_number', 'last_maintenance_date']);

        $eligibleMaintenanceCount = $eligibleMaintenanceDevices->count();
        $eligibleMaintenanceIds = $eligibleMaintenanceDevices->pluck('id');
        $maintenanceTypeDeviceIds = $eligibleMaintenanceDevices
            ->groupBy(fn (Device $device) => $device->type?->name ?? 'Unknown')
            ->map(fn ($devices) => $devices->pluck('id')->values());

        $semiannualPeriod = static function ($value): string {
            $date = $value instanceof Carbon ? $value : Carbon::parse($value);

            return $date->format('Y') . ' ' . ($date->month <= 6 ? 'Jan-Jun' : 'Jul-Dec');
        };

        $maintenanceSourceIds = $eligibleMaintenanceIds
            ->merge($eligibleMaintenanceDevices->map(fn (Device $device) => $device->parentProperty?->id))
            ->filter()
            ->unique()
            ->values();

        // A report row is Maintained when its own or inherited maintenance
        // history/date is available. Keep a set of eligible device ids per
        // window so linked peripherals inherit the same parent checklist as
        // All Assets, even when the child has no separate record of its own.
        $maintenanceDeviceIdsByPeriod = [];
        $rememberMaintenance = function ($value, int $deviceId) use (&$maintenanceDeviceIdsByPeriod, $semiannualPeriod): void {
            if (! filled($value)) {
                return;
            }

            $period = $semiannualPeriod($value);
            $maintenanceDeviceIdsByPeriod[$period] ??= [];
            $maintenanceDeviceIdsByPeriod[$period][$deviceId] = true;
        };

        foreach ($eligibleMaintenanceDevices as $device) {
            $rememberMaintenance($device->last_maintenance_date, (int) $device->id);
            $rememberMaintenance($device->parentProperty?->last_maintenance_date, (int) $device->id);
        }

        $maintenanceRecords = DeviceMaintenanceRecord::query()
            ->whereNotNull('maintenance_date')
            ->whereIn('device_id', $maintenanceSourceIds)
            ->get(['device_id', 'maintenance_date'])
            ->all();

        $eligibleDeviceIds = $eligibleMaintenanceIds->mapWithKeys(fn ($id) => [(int) $id => true]);
        $childrenByParentId = $eligibleMaintenanceDevices
            ->filter(fn (Device $device) => $device->parentProperty?->id)
            ->groupBy(fn (Device $device) => (int) $device->parentProperty->id);

        foreach ($maintenanceRecords as $record) {
            $recordDeviceId = (int) $record->device_id;

            if ($eligibleDeviceIds->has($recordDeviceId)) {
                $rememberMaintenance($record->maintenance_date, $recordDeviceId);
            }

            foreach ($childrenByParentId->get($recordDeviceId, collect()) as $child) {
                $rememberMaintenance($record->maintenance_date, (int) $child->id);
            }
        }

        $maintenanceDeviceIdsByPeriod = collect($maintenanceDeviceIdsByPeriod)
            ->map(fn (array $deviceIds) => collect(array_keys($deviceIds)));

        // Always expose the current window, even when no checklist has been
        // saved yet, so the dashboard clearly shows the outstanding count.
        $currentMaintenancePeriod = $semiannualPeriod(now());
        $maintenancePeriods = $maintenanceDeviceIdsByPeriod->keys()
            ->push($currentMaintenancePeriod)
            ->unique()
            ->sort()
            ->values();

        $maintenanceCoverageSemiannually = $maintenancePeriods
            ->map(function (string $period) use ($maintenanceDeviceIdsByPeriod, $maintenanceTypeDeviceIds, $eligibleMaintenanceCount) {
                $maintainedIds = $maintenanceDeviceIdsByPeriod->get($period, collect());

                $types = $maintenanceTypeDeviceIds
                    ->map(function ($deviceIds) use ($maintainedIds) {
                        $maintained = $deviceIds->intersect($maintainedIds)->count();

                        return [
                            'maintained' => $maintained,
                            'not_maintained' => max($deviceIds->count() - $maintained, 0),
                        ];
                    });

                return [
                    'label' => $period,
                    'types' => $types->all(),
                    'total' => $eligibleMaintenanceCount,
                ];
            });

        // Provide a direct current-window comparison as well as the
        // historical semiannual chart above. This keeps every equipment type
        // visible while making the Maintained vs Not Maintained split easy to
        // compare at a glance. Condemned equipment remains excluded.
        $currentMaintenanceIds = $maintenanceDeviceIdsByPeriod
            ->get($currentMaintenancePeriod, collect())
            ->values();

        $maintenanceCoverageByType = $maintenanceTypeDeviceIds
            ->map(function ($deviceIds) use ($currentMaintenanceIds) {
                $maintained = $deviceIds->intersect($currentMaintenanceIds)->count();

                return [
                    'maintained' => $maintained,
                    'not_maintained' => max($deviceIds->count() - $maintained, 0),
                ];
            });

        // Count actual equipment transfers/reissues by the same semiannual
        // windows used by maintenance reporting. The first assignment is an
        // initial issuance; subsequent assignments for the same device are
        // transfers and are counted once per transfer event.
        $transferSemiannually = collect();
        $transferAssignments = DeviceAssignment::query()
            ->whereNotNull('issued_at')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('device_assignments as previous_assignment')
                    ->whereColumn('previous_assignment.device_id', 'device_assignments.device_id')
                    ->where(function ($previous) {
                        $previous->whereColumn('previous_assignment.issued_at', '<', 'device_assignments.issued_at')
                            ->orWhere(function ($sameTime) {
                                $sameTime->whereColumn('previous_assignment.issued_at', '=', 'device_assignments.issued_at')
                                    ->whereColumn('previous_assignment.id', '<', 'device_assignments.id');
                            });
                    });
            })
            ->get(['id', 'device_id', 'issued_at']);
        foreach ($transferAssignments as $assignment) {
            $date = $assignment->issued_at instanceof Carbon
                ? $assignment->issued_at
                : Carbon::parse($assignment->issued_at);
            $period = $date->format('Y') . ' ' . ($date->month <= 6 ? 'Jan-Jun' : 'Jul-Dec');
            $transferSemiannually->put($period, $transferSemiannually->get($period, 0) + 1);
        }
        $transferSemiannually = $transferSemiannually->sortKeys();

        // Preventive-maintenance status is calculated from the published PM
        // plans and their current checklist cycle, not from equipment status.
        // This keeps the dashboard aligned with the PM Plan page for the
        // signed-in user's assigned schedules.
        $maintenancePlanStatuses = collect([
            'Pending' => 0,
            'In Progress' => 0,
            'Completed' => 0,
        ]);

        // Local, explainable recommendations for the next PM cycle. This is
        // intentionally advisory: existing checklist/edit/reissue workflows
        // remain the approval point for any action.
        $maintenanceAttentionRecommendations = $maintenanceAttentionService->recommendations();
        $maintenanceAttentionCount = $maintenanceAttentionRecommendations
            ->where('score', '>=', 25)
            ->count();
        $maintenanceAttention = $maintenanceAttentionRecommendations->take(8);

        // Trend points are persisted monthly rather than reconstructed from
        // mutable inventory records. The current month is updated by the
        // scheduled snapshot command; prior months remain historical.
        $maintenanceAttentionSnapshots = MaintenanceAttentionSnapshot::query()
            ->whereDate('snapshot_month', '>=', now()->startOfMonth()->subMonths(11)->toDateString())
            ->orderBy('snapshot_month')
            ->get();

        $maintenancePlans = MaintenancePlanSchedule::query()
            ->visibleTo(auth()->user())
            ->with(['latestOverride', 'completion'])
            ->get();

        foreach ($maintenancePlans as $plan) {
            $targetDevices = Device::query()
                ->whereHas('type', fn ($query) => $query->whereIn('name', ['Desktop', 'Laptop']))
                // Match PM Plan progress: condemned devices remain visible in
                // inventory but are excluded from active maintenance targets.
                ->where(function ($query) {
                    $query->whereNull('condition')->orWhere('condition', '<>', 'condemned');
                })
                ->whereHas('currentAssignment', function ($query) use ($plan) {
                    if ($plan->office_id) {
                        $query->where(function ($assignment) use ($plan) {
                            $assignment->where('office_id', $plan->office_id)
                                ->orWhereHas('staff', fn ($staff) => $staff->where('office_id', $plan->office_id));
                        });
                    } else {
                        $query->where(function ($assignment) use ($plan) {
                            $assignment->where('location_id', $plan->location_id)
                                ->orWhereHas('office', fn ($office) => $office->where('location_id', $plan->location_id))
                                ->orWhereHas('staff.office', fn ($office) => $office->where('location_id', $plan->location_id));
                        });
                    }
                })
                ->pluck('id');

            $checked = 0;
            if ($targetDevices->isNotEmpty()) {
                $effectiveDate = $plan->effectiveDate();
                $checked = DeviceMaintenanceRecord::query()
                    ->whereIn('device_id', $targetDevices)
                    ->whereDate('maintenance_date', '>=', $effectiveDate->toDateString())
                    ->distinct('device_id')
                    ->count('device_id');
            }

            $status = $targetDevices->isNotEmpty() && $checked === $targetDevices->count()
                ? 'Completed'
                : ($checked > 0 ? 'In Progress' : 'Pending');
            $maintenancePlanStatuses->put(
                $status,
                $maintenancePlanStatuses->get($status, 0) + 1
            );
        }

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
            'maintenanceCoverageSemiannually',
            'maintenanceCoverageByType',
            'currentMaintenancePeriod',
            'transferSemiannually',
            'maintenancePlanStatuses',
            'maintenanceAttention',
            'maintenanceAttentionCount',
            'maintenanceAttentionSnapshots',
        ));
    }
}
