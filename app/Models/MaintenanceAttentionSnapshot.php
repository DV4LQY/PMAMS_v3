<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceAttentionSnapshot extends Model
{
    protected $fillable = [
        'snapshot_month',
        'critical_count',
        'high_count',
        'medium_count',
        'low_count',
        'ai_recommended_count',
        'total_count',
        'engine_mode',
        'captured_at',
    ];

    protected $casts = [
        'snapshot_month' => 'date',
        'captured_at' => 'datetime',
    ];
}
