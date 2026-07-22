<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceMaintenancePhoto extends Model
{
    protected $fillable = [
        'device_id',
        'maintenance_record_id',
        'uploaded_by',
        'photo_path',
        'captured_at',
        'caption',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function maintenanceRecord(): BelongsTo
    {
        return $this->belongsTo(DeviceMaintenanceRecord::class, 'maintenance_record_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
