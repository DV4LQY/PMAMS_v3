<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceMaintenanceRecord extends Model
{
    protected $fillable = [
        'device_id',
        'staff_id',
        'office_id',
        'location_id',
        'maintenance_date',
        'maintenance_type',
        'condition',
        'remarks',
        'corrective_action',
        'checklist_data',
        'checked_by',
    ];

    protected $casts = [
        'maintenance_date' => 'date',
        'checklist_data' => 'array',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
