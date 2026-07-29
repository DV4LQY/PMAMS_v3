<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MaintenancePlanSchedule extends Model
{
    protected $fillable = [
        'location_id',
        'office_id',
        'assigned_user_id',
        'created_by',
        'scheduled_date',
        'title',
        'notes',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(MaintenancePlanOverride::class);
    }

    public function latestOverride(): HasOne
    {
        return $this->hasOne(MaintenancePlanOverride::class)->latestOfMany('id');
    }

    public function completion(): HasOne
    {
        return $this->hasOne(MaintenancePlanCompletion::class);
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user || $user->isSuperAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($user) {
            $visible->whereNull('assigned_user_id')->orWhere('assigned_user_id', $user->id);
        });
    }

    public function effectiveDate(): \Carbon\CarbonInterface
    {
        return ($this->latestOverride?->override_date ?? $this->scheduled_date)->copy();
    }
}
