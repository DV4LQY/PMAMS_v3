<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
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
        'photo_path',
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

    public function scopeFilterInventory(Builder $query, array $filters): Builder
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $typeId = (int) ($filters['type_id'] ?? 0);
        $locationId = (int) ($filters['location_id'] ?? 0);
        $status = $filters['status'] ?? null;
        $condition = $filters['condition'] ?? null;

        return $query
            ->when($q !== '', function (Builder $query) use ($q) {
                $query->where(function (Builder $sub) use ($q) {
                    $sub->where('property_number', 'like', "%{$q}%")
                        ->orWhere('serial_number', 'like', "%{$q}%")
                        ->orWhere('computer_name', 'like', "%{$q}%")
                        ->orWhere('brand', 'like', "%{$q}%")
                        ->orWhere('model', 'like', "%{$q}%")
                        ->orWhere('mac_address', 'like', "%{$q}%");
                });
            })
            ->when($typeId, fn (Builder $query) => $query->where('device_type_id', $typeId))
            ->when($locationId, function (Builder $query) use ($locationId) {
                $query->whereHas('currentAssignment.staff.office', function (Builder $office) use ($locationId) {
                    $office->where('location_id', $locationId);
                });
            })
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($condition, fn (Builder $query) => $query->where('condition', $condition));
    }

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
