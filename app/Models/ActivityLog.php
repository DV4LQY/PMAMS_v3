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
    ];

    public static function canonicalType(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        return self::TYPE_ALIASES[$type] ?? $type;
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
