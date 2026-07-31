<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenancePlanOverride extends Model
{
    protected $fillable = [
        'maintenance_plan_schedule_id',
        'override_date',
        'override_month_from',
        'override_month_to',
        'reason',
        'overridden_by',
    ];

    protected $casts = [
        'override_date' => 'date',
        'override_month_from' => 'date',
        'override_month_to' => 'date',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlanSchedule::class, 'maintenance_plan_schedule_id');
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }
}
