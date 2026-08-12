<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Office extends Model
{
    protected $fillable = ['location_id', 'college_id', 'name'];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Backward-compatible relationship name for older code.
     */
    public function college(): BelongsTo
    {
        return $this->location();
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function responsibleStaff(): HasOne
    {
        return $this->hasOne(Staff::class)
            ->where('is_office_head', true)
            ->where('is_active', true);
    }

    /**
     * Colleges use the title Dean; administrative locations use Head of Unit.
     * The check is intentionally based on the registered parent location so
     * the report remains correct when an office is renamed.
     */
    public function responsibleTitle(): string
    {
        $locationName = strtolower(trim((string) $this->location?->name));

        return str_contains($locationName, 'college') ? 'Dean' : 'Head of Unit';
    }

    public function getCollegeIdAttribute(): ?int
    {
        return $this->attributes['location_id'] ?? null;
    }

    public function setCollegeIdAttribute($value): void
    {
        $this->attributes['location_id'] = $value;
    }
}
