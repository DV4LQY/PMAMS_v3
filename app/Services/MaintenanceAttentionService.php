<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\DeviceMaintenanceRecord;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds a local, explainable maintenance-priority list.
 *
 * This deliberately uses only the inventory and maintenance history already
 * stored by PMAMS. It does not call an external AI service, so it also works
 * in an offline XAMPP installation and remains easy to audit.
 */
class MaintenanceAttentionService
{
    /**
     * Return equipment ordered by the likelihood that it needs attention at
     * the next preventive-maintenance cycle.
     */
    public function recommendations(): Collection
    {
        $devices = Device::query()
            ->with([
                'type',
                'latestMaintenanceRecord',
                'currentAssignment.staff.office.location',
                'currentAssignment.office.location',
                'currentAssignment.location',
                'deployedLocation',
                'deployedOffice.location',
            ])
            // Condemned equipment is already out of service and should not be
            // suggested for an upgrade or another maintenance cycle.
            ->where(function ($query) {
                $query->whereNull('condition')
                    ->orWhere('condition', '<>', 'condemned');
            })
            ->get();

        if ($devices->isEmpty()) {
            return collect();
        }

        $deviceIds = $devices->modelKeys();
        $historySince = Carbon::now()->subMonths(12)->startOfDay();

        $recentRecords = DeviceMaintenanceRecord::query()
            ->whereIn('device_id', $deviceIds)
            ->whereDate('maintenance_date', '>=', $historySince->toDateString())
            ->orderByDesc('maintenance_date')
            ->get(['device_id', 'maintenance_date', 'condition', 'remarks', 'corrective_action'])
            ->groupBy('device_id');

        $transferCounts = DeviceAssignment::query()
            ->whereIn('device_id', $deviceIds)
            ->whereNotNull('issued_at')
            ->whereDate('issued_at', '>=', $historySince->toDateString())
            ->selectRaw('device_id, COUNT(*) AS total')
            ->groupBy('device_id')
            ->pluck('total', 'device_id');

        return $devices
            ->map(function (Device $device) use ($recentRecords, $transferCounts) {
                return $this->score(
                    $device,
                    $recentRecords->get($device->getKey(), collect()),
                    (int) $transferCounts->get($device->getKey(), 0)
                );
            })
            ->sort(function (array $left, array $right) {
                $score = $right['score'] <=> $left['score'];
                if ($score !== 0) {
                    return $score;
                }

                $age = $right['age_years'] <=> $left['age_years'];
                if ($age !== 0) {
                    return $age;
                }

                // Older/unknown maintenance dates are shown first when the
                // score is otherwise tied.
                return $left['last_maintenance_sort'] <=> $right['last_maintenance_sort'];
            })
            ->values();
    }

    /**
     * Score one device using deterministic, visible rules.
     *
     * The score is advisory only. Existing edit, issue, reissue, and checklist
     * workflows remain the approval point for any action.
     */
    private function score(Device $device, Collection $records, int $transferCount): array
    {
        $score = 0;
        $reasons = [];
        $attentionFlags = [];
        $condition = $this->key($device->condition);
        $status = $this->key($device->status);
        $osVersion = strtolower(trim((string) $device->os_version));
        $memoryGb = $this->memoryGb($device);

        if ($condition === 'unserviceable') {
            $score += 35;
            $reasons[] = 'Equipment is marked unserviceable';
        }

        if ($status === 'repair') {
            $score += 25;
            $reasons[] = 'Equipment status is Repair';
        } elseif ($status === 'not_in_use') {
            $score += 15;
            $reasons[] = 'Equipment is marked Not in Use';
        }

        // Keep the upgrade advice deterministic and local: it is based only
        // on the recorded OS version and memory value, with no external AI
        // or online service involved.
        if (in_array($osVersion, ['windows 10', 'windows 11'], true)
            && $memoryGb !== null
            && $memoryGb <= 8) {
            $score += 20;
            $attentionFlags[] = 'ram_upgrade';
            $reasons[] = 'Upgrade RAM to at least 16 GB';
        } elseif (in_array($osVersion, ['windows 7', 'windows 8'], true)
            && $memoryGb !== null
            && $memoryGb <= 4) {
            $score += 20;
            $attentionFlags[] = 'ram_upgrade';
            $reasons[] = 'Upgrade RAM to at least 8 GB';
        }

        if ($this->key($device->os_license) === 'cracked') {
            $score += 20;
            $attentionFlags[] = 'cracked_license';
            $reasons[] = 'Procure a Genuine OS license';
        }

        if ($this->key($device->ms_office_license) === 'cracked') {
            $score += 20;
            $attentionFlags[] = 'cracked_license';
            $reasons[] = 'Procure a Genuine Microsoft Office suite';
        }

        $issueRecords = $records->filter(function (DeviceMaintenanceRecord $record) {
            $condition = $this->key($record->condition);
            $text = strtolower(trim(implode(' ', array_filter([
                $record->remarks,
                $record->corrective_action,
            ]))));

            return in_array($condition, ['unserviceable', 'condemned'], true)
                || preg_match('/not\s*ok|defective|repair|replace|upgrade/', $text) === 1;
        });

        if ($issueRecords->isNotEmpty()) {
            $score += min(25, $issueRecords->count() * 8);
            $reasons[] = $issueRecords->count() === 1
                ? 'A recent checklist recorded an issue'
                : $issueRecords->count() . ' recent checklists recorded issues';
        }

        $ageYears = 0;
        if ($device->date_acquired) {
            $acquired = $device->date_acquired instanceof Carbon
                ? $device->date_acquired
                : Carbon::parse($device->date_acquired);
            $ageYears = max(0, $acquired->diffInYears(Carbon::now()));

            if ($ageYears >= 7) {
                $score += 20;
                $attentionFlags[] = 'old_equipment';
                $reasons[] = 'Equipment is at least 7 years old';
            } elseif ($ageYears >= 5) {
                $score += 15;
                $attentionFlags[] = 'old_equipment';
                $reasons[] = 'Equipment is at least 5 years old';
            } elseif ($ageYears >= 3) {
                $score += 8;
                $reasons[] = 'Equipment is at least 3 years old';
            }
        }

        $lastMaintenance = $device->latestMaintenanceRecord?->maintenance_date
            ?? $device->last_maintenance_date;
        $lastMaintenance = $lastMaintenance
            ? ($lastMaintenance instanceof Carbon ? $lastMaintenance : Carbon::parse($lastMaintenance))
            : null;

        if (! $lastMaintenance) {
            $score += 20;
            $reasons[] = 'No maintenance date is recorded';
        } else {
            $daysSinceMaintenance = $lastMaintenance->diffInDays(Carbon::now());
            if ($daysSinceMaintenance > 365) {
                $score += 20;
                $reasons[] = 'Maintenance is more than 12 months overdue';
            } elseif ($daysSinceMaintenance > 180) {
                $score += 15;
                $reasons[] = 'Maintenance is more than 6 months overdue';
            } elseif ($daysSinceMaintenance > 90) {
                $score += 8;
                $reasons[] = 'Maintenance was more than 3 months ago';
            }
        }

        if ($records->count() >= 3) {
            $score += 10;
            $reasons[] = 'Repeated maintenance activity in the last 12 months';
        }

        if ($transferCount >= 3) {
            $score += 5;
            $reasons[] = 'Transferred three or more times in the last 12 months';
        }

        $score = min(100, $score);
        $locationDetails = $this->locationDetails($device);

        return [
            'device' => $device,
            'score' => $score,
            'priority' => $score >= 75 ? 'Critical' : ($score >= 50 ? 'High' : ($score >= 25 ? 'Medium' : 'Low')),
            'reasons' => $reasons ?: ['No urgent signal; monitor during the next PM cycle'],
            'attention_flags' => array_values(array_unique($attentionFlags)),
            'age_years' => $ageYears,
            'last_maintenance' => $lastMaintenance,
            // Used only as a deterministic tie-breaker; it is removed before
            // the collection is passed to a view.
            'last_maintenance_sort' => $lastMaintenance?->timestamp ?? 0,
            'location' => $locationDetails['label'],
            'location_name' => $locationDetails['name'],
        ];
    }

    /**
     * Parse common inventory memory values (for example "8 GB", "8GB RAM",
     * or "4096 MB") into gigabytes. Unknown/missing values are left unknown
     * so the service never invents an upgrade recommendation.
     */
    private function memoryGb(Device $device): ?float
    {
        $raw = trim((string) data_get($device->specs, 'memory', ''));
        if ($raw === '' || ! preg_match('/(\d+(?:\.\d+)?)\s*(tb|tib|gb|gib|mb|mib|g|m)?/i', $raw, $matches)) {
            return null;
        }

        $value = (float) $matches[1];
        $unit = strtolower($matches[2] ?? 'gb');

        return match (true) {
            in_array($unit, ['tb', 'tib'], true) => $value * 1024,
            in_array($unit, ['mb', 'mib', 'm'], true) => $value / 1024,
            default => $value,
        };
    }

    private function locationLabel(Device $device): string
    {
        return $this->locationDetails($device)['label'];
    }

    private function locationDetails(Device $device): array
    {
        $assignment = $device->currentAssignment;
        $office = $assignment?->office
            ?? $assignment?->staff?->office
            ?? $device->deployedOffice;
        $location = $assignment?->location
            ?? $office?->location
            ?? $device->deployedLocation;

        $locationName = $location?->name;
        $officeName = $office?->name;

        if ($locationName && $officeName) {
            return ['label' => $locationName . ' - ' . $officeName, 'name' => $locationName];
        }

        $name = $locationName ?: $officeName;

        return [
            'label' => $name ?: 'Location not assigned',
            'name' => $name ?: 'Location not assigned',
        ];
    }

    private function key(?string $value): string
    {
        return str_replace(['-', ' '], '_', strtolower(trim((string) $value)));
    }
}
