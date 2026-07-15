<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function getCollegeIdAttribute(): ?int
    {
        return $this->attributes['location_id'] ?? null;
    }

    public function setCollegeIdAttribute($value): void
    {
        $this->attributes['location_id'] = $value;
    }
}
