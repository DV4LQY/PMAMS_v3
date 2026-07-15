<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Device extends Model
{
    protected $fillable = [
        'device_type_id',
        'property_number',
        'serial_number',
        'computer_name',
        'brand',
        'model',
        'mac_address',
        'unit_price',
        'date_acquired',
        'status',
        'condition',
        'notes',
        'specs',
        'last_maintenance_date',
        'maintenance_remarks',
        'os_version',
        'os_license',
        'ms_office_version',
        'ms_office_license',
    ];

    protected $casts = [
        'specs' => 'array',
        'date_acquired' => 'date',
        'last_maintenance_date' => 'date',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(DeviceType::class, 'device_type_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DeviceAssignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(DeviceAssignment::class)
            ->whereNull('returned_at')
            ->latestOfMany();
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(DeviceMaintenanceRecord::class);
    }

    public function latestMaintenanceRecord(): HasOne
    {
        return $this->hasOne(DeviceMaintenanceRecord::class)
            ->latestOfMany('maintenance_date');
    }
}
