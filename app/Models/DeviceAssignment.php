<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceAssignment extends Model
{
    protected $fillable = [
        'device_id', 'staff_id', 'office_id', 'location_id', 'issued_by', 'issued_at', 'returned_at', 'remarks'
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Return the immediately preceding assignment for this equipment.
     *
     * Issuance reports use this to show where an item came from before it was
     * transferred/reissued. The controller eager-loads the assignment history
     * so this does not create an N+1 query during report rendering.
     */
    public function previousAssignment(): ?self
    {
        $history = $this->device?->relationLoaded('assignments')
            ? $this->device->assignments
            : $this->device?->assignments()->get();

        if (! $history || ! $this->issued_at) {
            return null;
        }

        return $history
            ->filter(fn (self $assignment) => $assignment->id !== $this->id
                && $assignment->issued_at
                && $assignment->issued_at->lt($this->issued_at))
            ->sortByDesc(fn (self $assignment) => $assignment->issued_at->timestamp)
            ->first();
    }
}
