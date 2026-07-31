<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenancePlanCompletion extends Model
{
    protected $fillable = [
        'maintenance_plan_schedule_id',
        'actual_date',
        'person_in_charge',
        'signer_name',
        'signature',
        'signature_data',
        'remarks',
        'completed_by',
    ];

    protected $casts = [
        'actual_date' => 'date',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlanSchedule::class, 'maintenance_plan_schedule_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
