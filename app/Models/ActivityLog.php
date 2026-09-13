<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record an activity log entry.
     *
     * Usage:
     *   ActivityLog::record('created', 'Created college "..."', $college, ActivityLog::diff([], $newAttributes));
     *   ActivityLog::record('updated', 'Updated college "..."', $college, ActivityLog::diff($before, $after));
     *   ActivityLog::record('deleted', 'Deleted college "..."', null, ActivityLog::diff($before, []));
     */
    public static function record(string $action, string $description, $subject = null, ?array $payload = null): self
    {
        $user = auth()->user();

        return self::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->id,
            'description' => $description,
            'changes' => $payload,
        ]);
    }

    public static function buildChanges(array $before, array $after): array
    {
        $changes = [];

        foreach ($before as $field => $oldValue) {
            $newValue = $after[$field] ?? null;

            if ($oldValue != $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    public static function makePayload(array $summary = [], array $changes = []): ?array
    {
        if (empty($summary) && empty($changes)) {
            return null;
        }

        /*
         * Bulk payloads are already in their final structure.
         */
        if (
            isset($summary['bulk']) &&
            isset($summary['items'])
        ) {
            return $summary;
        }

        $normalizedSummary = [];

        foreach ($summary as $field => $value) {
            $normalizedSummary[$field] = [
                'value' => $value,
                'is_new' => array_key_exists($field, $changes),
            ];
        }

        return [
            'summary' => $normalizedSummary,
            'changes' => $changes,
        ];
    }

    public function getSummaryAttribute(): array
    {
        $changes = $this->getAttribute('changes') ?? [];

        // Bulk logs don't have a summary section.
        if (!empty($changes['bulk'])) {
            return [];
        }

        return $changes['summary'] ?? [];
    }

    public function getFieldChangesAttribute(): array
    {
        $changes = $this->getAttribute('changes') ?? [];

        // Bulk logs don't use field changes.
        if (!empty($changes['bulk'])) {
            return [];
        }

        if (isset($changes['changes'])) {
            return $changes['changes'];
        }

        return $changes ?? [];
    }

    public function getIsBulkAttribute(): bool
    {
        $changes = $this->getAttribute('changes') ?? [];

        return !empty($changes['bulk']);
    }

    public function getBulkItemsAttribute(): array
    {
        $changes = $this->getAttribute('changes') ?? [];

        return $changes['items'] ?? [];
    }

    /**
     * Legacy record/subject type names, mapped to their current name.
     * Add an entry here whenever a record type is renamed, so that old
     * activity log rows keep displaying and filtering correctly under
     * the new name instead of fragmenting into a separate entry.
     */
    public const TYPE_ALIASES = [
        'College' => 'Location',
        'Device' => 'Equipment',
        'MaintenancePlanSchedule' => 'PM Plan',
    ];

    public static function canonicalType(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        return self::TYPE_ALIASES[$type] ?? $type;
    }

    /**
     * Resolve the account responsible for deleting the supplied soft-deleted
     * records. The result is keyed by the ActivityLog subject type and record
     * id so recycle-bin screens can render the value without querying once per
     * table row. Bulk deletion entries are also inspected when their payload
     * contains the affected record ids.
     *
     * @param  array<string, iterable<mixed>>  $groups
     * @return array<string, array<int, string>>
     */
    public static function deletedByFor(array $groups): array
    {
        $idsByType = [];
        $subjectTypesByType = [];

        foreach ($groups as $subjectType => $records) {
            $subjectType = (string) $subjectType;
            $ids = collect($records)
                ->map(function ($record) {
                    if (is_object($record)) {
                        return method_exists($record, 'getKey')
                            ? $record->getKey()
                            : data_get($record, 'id');
                    }

                    return data_get($record, 'id', $record);
                })
                ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($ids->isEmpty()) {
                continue;
            }

            $idsByType[$subjectType] = ($idsByType[$subjectType] ?? collect())
                ->merge($ids)
                ->unique()
                ->values();

            $subjectTypesByType[$subjectType] = [$subjectType];
            // Older location activity rows used the former College subject
            // name. Keep them visible under the current Location section.
            if ($subjectType === 'Location') {
                $subjectTypesByType[$subjectType][] = 'College';
            }
        }

        if ($idsByType === []) {
            return [];
        }

        $result = [];
        $query = self::query()
            ->select(['subject_type', 'subject_id', 'user_name', 'created_at'])
            ->where('action', 'deleted')
            ->whereNotNull('subject_id')
            ->where(function ($subjectQuery) use ($idsByType, $subjectTypesByType): void {
                foreach ($idsByType as $subjectType => $ids) {
                    $subjectQuery->orWhere(function ($match) use ($subjectTypesByType, $subjectType, $ids): void {
                        $match
                            ->whereIn('subject_type', $subjectTypesByType[$subjectType])
                            ->whereIn('subject_id', $ids->all());
                    });
                }
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        foreach ($query as $log) {
            $subjectType = $log->subject_type === 'College'
                ? 'Location'
                : (string) $log->subject_type;
            $subjectId = (int) $log->subject_id;

            if (! isset($idsByType[$subjectType])
                || ! $idsByType[$subjectType]->contains($subjectId)
                || isset($result[$subjectType][$subjectId])) {
                continue;
            }

            $result[$subjectType][$subjectId] = $log->user_name ?: 'System';
        }

        $bulkRecordTypes = [
            'User' => ['User'],
            'Device' => ['Equipment', 'Device'],
            'MaintenancePlanSchedule' => ['PM Plan', 'MaintenancePlanSchedule'],
            'Location' => ['Location', 'College'],
            'Office' => ['Office'],
            'Staff' => ['Staff'],
            'DeviceMaintenanceRecord' => ['Checklist', 'DeviceMaintenanceRecord'],
        ];
        $recordTypes = collect(array_keys($idsByType))
            ->flatMap(fn ($subjectType) => $bulkRecordTypes[$subjectType] ?? [])
            ->unique()
            ->values();

        if ($recordTypes->isEmpty()) {
            return $result;
        }

        // Bulk logs have no subject_id. Filter by their small record-type
        // discriminator, then map the ids stored in the payload to the rows
        // currently visible in the recovery screen.
        $bulkLogs = self::query()
            ->select(['user_name', 'changes', 'created_at'])
            ->where('action', 'deleted')
            ->whereNull('subject_id')
            ->where(function ($bulkQuery) use ($recordTypes): void {
                foreach ($recordTypes as $recordType) {
                    $bulkQuery->orWhere('changes->record_type', $recordType);
                }
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $subjectTypeByRecordType = [];
        foreach ($bulkRecordTypes as $subjectType => $types) {
            foreach ($types as $recordType) {
                $subjectTypeByRecordType[$recordType] = $subjectType;
            }
        }

        foreach ($bulkLogs as $log) {
            $changes = is_array($log->changes) ? $log->changes : [];
            $recordType = (string) ($changes['record_type'] ?? '');
            $subjectType = $subjectTypeByRecordType[$recordType] ?? null;
            if ($subjectType === null || ! isset($idsByType[$subjectType])) {
                continue;
            }

            foreach ((array) ($changes['items'] ?? []) as $item) {
                $subjectId = data_get($item, 'id')
                    ?? data_get($item, 'schedule_id')
                    ?? data_get($item, 'maintenance_record_id')
                    ?? data_get($item, 'summary.id');
                $subjectId = is_numeric($subjectId) ? (int) $subjectId : 0;

                if ($subjectId <= 0
                    || ! $idsByType[$subjectType]->contains($subjectId)
                    || isset($result[$subjectType][$subjectId])) {
                    continue;
                }

                $result[$subjectType][$subjectId] = $log->user_name ?: 'System';
            }
        }

        return $result;
    }

    public function getBulkRecordTypeAttribute(): ?string
    {
        $changes = $this->getAttribute('changes') ?? [];

        return self::canonicalType($changes['record_type'] ?? null);
    }

    /**
     * Compute a field-level diff between two attribute arrays.
     * Returns only the keys that actually differ, each as ['old' => ..., 'new' => ...].
     *
     * - Create: diff([], $newAttributes) — every field shows old = null.
     * - Update: diff($before, $after) — only changed fields are included.
     * - Delete: diff($oldAttributes, []) — every field shows new = null.
     */
    public static function diff(array $before, array $after): array
    {
        $changes = [];
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));

        foreach ($keys as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;

            // Normalize booleans/null for a clean comparison (e.g. true vs 1)
            if ($old != $new) {
                $changes[$key] = ['old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }
}
