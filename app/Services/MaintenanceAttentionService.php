<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\DeviceMaintenanceRecord;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds a local, explainable maintenance-priority list.
 *
 * This uses only the inventory and maintenance history already stored by
 * PMAMS. The Super Admin can select deterministic Laravel rules, the optional
 * local model, or a hybrid. No external AI service is called, so the service
 * remains usable in offline XAMPP installs.
 */
class MaintenanceAttentionService
{
    public const MODE_SETTING_KEY = 'maintenance_attention_mode';

    public const MODES = ['rules', 'ai', 'hybrid'];

    /**
     * ICT equipment is considered old only after the five-year useful-life
     * period has elapsed. Keep this threshold shared by the rules and the
     * model-training labels so both modes make the same recommendation.
     */
    public const OLD_EQUIPMENT_AGE_YEARS = 6;

    /** Version recorded in local model metadata when its labels are current. */
    public const AI_RULES_VERSION = 'age-threshold-6';

    public function __construct(private readonly LocalMaintenanceModelService $localModel)
    {
    }

    /**
     * Return equipment ordered by the likelihood that it needs attention at
     * the next preventive-maintenance cycle.
     */
    public function recommendations(?string $mode = null, bool $includeModelFeatures = false): Collection
    {
        $mode = self::normalizeMode($mode ?? SystemSetting::getValue(self::MODE_SETTING_KEY, 'hybrid'));

        $devices = Device::query()
            ->with([
                'type',
                'latestMaintenanceRecord',
                'currentAssignment.staff.office.location',
                'currentAssignment.staff.office.responsibleStaff',
                'currentAssignment.office.location',
                'currentAssignment.office.responsibleStaff',
                'currentAssignment.location',
                'deployedLocation',
                'deployedOffice.location',
                'deployedOffice.responsibleStaff',
            ])
            // Maintenance attention covers computer workstations and the
            // monitor/printer/UPS records that carry their own condition/status.
            // Other peripherals remain outside this advisory report.
            ->whereHas('type', function ($query) {
                $query->whereIn('name', ['Desktop', 'Laptop', 'Printer', 'Monitor', 'UPS']);
            })
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

        $scored = $devices
            ->map(function (Device $device) use ($recentRecords, $transferCounts) {
                return $this->score(
                    $device,
                    $recentRecords->get($device->getKey(), collect()),
                    (int) $transferCounts->get($device->getKey(), 0)
                );
            });

        // Training requests the feature vectors without spawning inference on
        // the previous model first. In normal operation the Super Admin may
        // choose Laravel rules, local AI, or a hybrid of both. A missing model
        // always falls back to the visible Laravel rules so the page remains
        // usable on a fresh/offline XAMPP installation.
        $predictions = $includeModelFeatures || $mode === 'rules'
            ? []
            : $this->localModel->predict(
                $scored->map(fn (array $item): array => $item['ai_features'])->values()->all()
            );

        $scored = $scored->map(function (array $item, int $index) use ($predictions, $mode, $includeModelFeatures): array {
            $item['ai_recommended'] = false;
            $item['recommendation_source'] = 'Laravel rules';

            if ($includeModelFeatures) {
                return $item;
            }

            if ($predictions !== [] && array_key_exists($index, $predictions)) {
                $probability = isset($predictions[$index])
                    ? max(0, min(1, (float) $predictions[$index]))
                    : null;

                if ($probability === null) {
                    return $item;
                }

                $item['ai_probability'] = $probability;
                $item['ai_score'] = (int) round($probability * 100);
                $item['ai_recommended'] = $probability >= 0.70;

                if ($mode === 'ai') {
                    $item['score'] = $item['ai_score'];
                    $item['priority'] = $this->priorityForScore($item['score']);
                    $item['recommendation_source'] = 'Local AI';
                    $item['reasons'] = $item['ai_recommended']
                        ? ['Local AI recommends maintenance attention (' . (int) round($probability * 100) . '% confidence)']
                        : ['Local AI found no high-confidence attention signal (' . (int) round($probability * 100) . '% confidence)'];
                } else {
                    // Hybrid keeps the existing rule score and only lets AI
                    // raise the priority. This avoids hiding auditable rules.
                    $item['score'] = max($item['score'], $item['ai_score']);
                    $item['priority'] = $this->priorityForScore($item['score']);
                    $item['recommendation_source'] = $item['ai_recommended']
                        ? 'Laravel rules + Local AI'
                        : 'Laravel rules';

                    if ($item['ai_recommended']) {
                        $item['reasons'][] = 'Local AI recommends attention (' . (int) round($probability * 100) . '% confidence)';
                    }
                }

                return $item;
            }

            if ($mode === 'ai' && $predictions === []) {
                $item['recommendation_source'] = 'Laravel fallback';
                $item['reasons'][] = 'Local AI model unavailable; Laravel rules shown';
            }

            return $item;
        });

        return $scored
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
            ->map(function (array $item) use ($includeModelFeatures): array {
                // Feature vectors are for the model bridge only and should
                // never be exposed as part of the view data.
                if (! $includeModelFeatures) {
                    unset($item['ai_features']);
                }

                return $item;
            })
            ->values();
    }

    /**
     * Build labels from the same auditable signals used by the page. This is
     * intentionally a bootstrap dataset, not a hidden source of truth.
     *
     * @return array<int, array<string, int|float>>
     */
    public function trainingRows(): array
    {
        return $this->recommendations('rules', true)
            ->map(function (array $item): array {
                $features = $item['ai_features'] ?? [];
                $positive = (int) ($features['is_unserviceable'] ?? 0) === 1
                    || (int) ($features['is_repair'] ?? 0) === 1
                    || (int) ($features['is_not_in_use'] ?? 0) === 1
                    || (int) ($features['recent_issue_count'] ?? 0) > 0
                    || (int) ($features['maintenance_overdue_days'] ?? 0) > 365
                    || (int) ($features['age_years'] ?? 0) >= self::OLD_EQUIPMENT_AGE_YEARS;

                return $features + ['label' => $positive ? 1 : 0];
            })
            ->values()
            ->all();
    }

    public static function normalizeMode(?string $mode): string
    {
        $mode = strtolower(trim((string) $mode));

        return in_array($mode, self::MODES, true) ? $mode : 'hybrid';
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
        $typeName = $this->key($device->type?->name);
        $isComputer = in_array($typeName, ['desktop', 'laptop'], true);
        $osVersion = strtolower(trim((string) $device->os_version));
        $memoryGb = $this->memoryGb($device);
        $storage = strtolower(trim((string) data_get($device->specs, 'storage', '')));

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
        if ($isComputer && in_array($osVersion, ['windows 10', 'windows 11'], true)
            && $memoryGb !== null
            && $memoryGb <= 8) {
            $score += 20;
            $attentionFlags[] = 'ram_upgrade';
            $reasons[] = 'Upgrade RAM to at least 16 GB';
        } elseif ($isComputer && in_array($osVersion, ['windows 7', 'windows 8'], true)
            && $memoryGb !== null
            && $memoryGb <= 4) {
            $score += 20;
            $attentionFlags[] = 'ram_upgrade';
            $reasons[] = 'Upgrade RAM to at least 8 GB';
        }

        // Mechanical hard drives are a predictable performance bottleneck
        // for desktop equipment. Recommend an SSD flash-storage upgrade only
        // when the inventory explicitly records an HDD.
        if ($isComputer && $typeName === 'desktop'
            && preg_match('/\bhdd\b/i', $storage) === 1) {
            $score += 15;
            $attentionFlags[] = 'hdd_upgrade';
            $reasons[] = 'Upgrade HDD storage to SSD flash storage';
        }

        if ($isComputer && $this->key($device->os_license) === 'cracked') {
            $score += 20;
            $attentionFlags[] = 'cracked_os';
            $reasons[] = 'Procure a Genuine OS license';
        }

        if ($isComputer && $this->key($device->ms_office_license) === 'cracked') {
            $score += 20;
            $attentionFlags[] = 'cracked_ms_office';
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

            if ($ageYears >= self::OLD_EQUIPMENT_AGE_YEARS) {
                $score += 20;
                $attentionFlags[] = 'old_equipment';
                $reasons[] = 'Equipment is at least ' . self::OLD_EQUIPMENT_AGE_YEARS . ' years old';
            }
        }

        $lastMaintenance = $device->latestMaintenanceRecord?->maintenance_date
            ?? $device->last_maintenance_date;
        $checklistRecord = $device->latestMaintenanceRecord;
        $checklistCondition = $this->key($checklistRecord?->condition);
        $displayCondition = $checklistCondition !== ''
            ? $checklistCondition
            : $condition;
        $lastMaintenance = $lastMaintenance
            ? ($lastMaintenance instanceof Carbon ? $lastMaintenance : Carbon::parse($lastMaintenance))
            : null;

        $daysSinceMaintenance = null;
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
            'equipment_type' => $device->type?->name ?: '',
            'condition' => $displayCondition,
            'status' => $status,
            'checklist_condition' => $checklistCondition,
            'checklist_remarks' => trim((string) ($checklistRecord?->remarks ?? '')),
            'age_years' => $ageYears,
            'last_maintenance' => $lastMaintenance,
            // Used only as a deterministic tie-breaker; it is removed before
            // the collection is passed to a view.
            'last_maintenance_sort' => $lastMaintenance?->timestamp ?? 0,
            'location' => $locationDetails['label'],
            'location_name' => $locationDetails['name'],
            'office_name' => $locationDetails['office_name'],
            'responsible_name' => $locationDetails['responsible_name'],
            'responsible_title' => $locationDetails['responsible_title'],
            'ai_features' => [
                // Only Desktop/Laptop rows participate in hardware/license
                // signals. Printer/Monitor/UPS recommendations are based on
                // condition, status, age, and maintenance history instead.
                'memory_gb' => $isComputer ? ($memoryGb ?? 0) : 0,
                'memory_known' => $isComputer && $memoryGb !== null ? 1 : 0,
                'has_hdd' => $isComputer && preg_match('/\bhdd\b/i', $storage) === 1 ? 1 : 0,
                // Peripheral AI rows intentionally carry no computer-spec
                // signals. Their prediction can use condition/status and the
                // common maintenance history signals only.
                'is_desktop' => $typeName === 'desktop' ? 1 : 0,
                'is_unserviceable' => $condition === 'unserviceable' ? 1 : 0,
                'is_repair' => $status === 'repair' ? 1 : 0,
                'is_not_in_use' => $status === 'not_in_use' ? 1 : 0,
                'os_cracked' => $isComputer && $this->key($device->os_license) === 'cracked' ? 1 : 0,
                'ms_office_cracked' => $isComputer && $this->key($device->ms_office_license) === 'cracked' ? 1 : 0,
                'recent_issue_count' => $issueRecords->count(),
                'transfer_count' => $transferCount,
                'age_years' => $ageYears,
                'maintenance_overdue_days' => $daysSinceMaintenance ?? 0,
                'maintenance_missing' => $lastMaintenance ? 0 : 1,
            ],
        ];
    }

    private function priorityForScore(int $score): string
    {
        return $score >= 75 ? 'Critical' : ($score >= 50 ? 'High' : ($score >= 25 ? 'Medium' : 'Low'));
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
        // Resolve the canonical pair from the active assignment first, then
        // the registered deployment references. Imported/legacy records can
        // have only one of the assignment foreign keys populated, so each
        // relation is considered independently before falling back.
        $assignmentOffice = $assignment?->office
            ?: $assignment?->staff?->office;
        $assignmentLocation = $assignment?->location
            ?: $assignmentOffice?->location
            ?: $assignment?->staff?->office?->location;
        // Never combine an active assignment's location with a stale
        // deployment office. Assignment data is authoritative while active;
        // deployment references are only a fallback for unassigned devices.
        $office = $assignmentOffice ?: ($assignment ? null : $device->deployedOffice);
        $location = $assignmentLocation
            ?: ($assignment
                ? null
                : ($office?->location
                    ?: $device->deployedLocation
                    ?: $device->deployedOffice?->location));

        $locationName = $this->normalizeName($location?->name);
        $officeName = $this->normalizeName($office?->name);
        $responsible = $office?->responsibleStaff;
        $responsibleName = $responsible?->display_name;
        $responsibleTitle = $responsible ? $office->responsibleTitle() : null;

        if ($locationName && $officeName) {
            return [
                'label' => $locationName . ' - ' . $officeName,
                'name' => $locationName,
                'office_name' => $officeName,
                'responsible_name' => $responsibleName,
                'responsible_title' => $responsibleTitle,
            ];
        }

        $name = $locationName ?: $officeName;

        return [
            'label' => $name ?: 'Location not assigned',
            'name' => $name ?: 'Location not assigned',
            'office_name' => $officeName ?: 'Office not assigned',
            'responsible_name' => $responsibleName,
            'responsible_title' => $responsibleTitle,
        ];
    }

    private function normalizeName(?string $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim((string) $value));

        return is_string($normalized) ? $normalized : trim((string) $value);
    }

    private function key(?string $value): string
    {
        return str_replace(['-', ' '], '_', strtolower(trim((string) $value)));
    }
}
