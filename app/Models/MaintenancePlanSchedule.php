<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenancePlanSchedule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'location_id',
        'office_id',
        'assigned_user_id',
        'created_by',
        'scheduled_date',
        'schedule_month_from',
        'schedule_month_to',
        'title',
        'notes',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'schedule_month_from' => 'date',
        'schedule_month_to' => 'date',
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

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'maintenance_plan_schedule_user',
            'maintenance_plan_schedule_id',
            'user_id'
        )->withTimestamps()->orderBy('users.name');
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
        // Custodians manage the published PM Plan records, so they must be
        // able to see every schedule they may add, edit, or delete. Admin and
        // Unit Head accounts continue to see only unassigned or assigned
        // schedules.
        if (! $user || $user->isSuperAdmin() || $user->isCustodian()) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($user) {
            $visible
                ->where(function (Builder $unassigned) {
                    $unassigned->whereNull('assigned_user_id')
                        ->whereDoesntHave('assignedUsers');
                })
                ->orWhere('assigned_user_id', $user->id)
                ->orWhereHas('assignedUsers', fn (Builder $assigned) => $assigned->whereKey($user->id));
        });
    }

    public function effectiveDate(): \Carbon\CarbonInterface
    {
        return ($this->latestOverride?->override_month_from
            ?? $this->latestOverride?->override_date
            ?? $this->schedule_month_from
            ?? $this->scheduled_date)->copy();
    }
}
