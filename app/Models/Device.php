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
        'part_of_property_number',
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
        $officeId = (int) ($filters['office_id'] ?? 0);
        $status = $filters['status'] ?? null;
        $condition = $filters['condition'] ?? null;
        $tokens = $this->searchTokens($q);

        return $query
            ->when($tokens !== [], function (Builder $query) use ($tokens) {
                foreach ($tokens as $token) {
                    $query->where(function (Builder $sub) use ($token) {
                        $like = "%{$token}%";

                        $sub->where('property_number', 'like', $like)
                            ->orWhere('part_of_property_number', 'like', $like)
                            ->orWhere('serial_number', 'like', $like)
                            ->orWhere('computer_name', 'like', $like)
                            ->orWhere('brand', 'like', $like)
                            ->orWhere('model', 'like', $like)
                            ->orWhere('mac_address', 'like', $like)
                            ->orWhereHas('type', fn (Builder $type) => $type->where('name', 'like', $like))
                            ->orWhereHas('currentAssignment.location', function (Builder $location) use ($like) {
                                $location->where('name', 'like', $like)
                                    ->orWhere('code', 'like', $like);
                            })
                            ->orWhereHas('currentAssignment.staff', function (Builder $staff) use ($like) {
                                $staff->where('first_name', 'like', $like)
                                    ->orWhere('last_name', 'like', $like)
                                    ->orWhere('email', 'like', $like)
                                    ->orWhere('position', 'like', $like)
                                    ->orWhereHas('office', function (Builder $office) use ($like) {
                                        $office->where('name', 'like', $like)
                                            ->orWhereHas('location', function (Builder $location) use ($like) {
                                                $location->where('name', 'like', $like)
                                                    ->orWhere('code', 'like', $like);
                                            });
                                    });
                            });
                    });
                }
            })
            ->when($typeId, fn (Builder $query) => $query->where('device_type_id', $typeId))
            ->when($locationId, function (Builder $query) use ($locationId) {
                $query->whereHas('currentAssignment', function (Builder $assignment) use ($locationId) {
                    $assignment->where('location_id', $locationId)
                        ->orWhereHas('staff.office', function (Builder $office) use ($locationId) {
                            $office->where('location_id', $locationId);
                        });
                });
            })
            ->when($officeId, function (Builder $query) use ($officeId) {
                $query->whereHas('currentAssignment', function (Builder $assignment) use ($officeId) {
                    $assignment->where('office_id', $officeId)
                        ->orWhereHas('staff', function (Builder $staff) use ($officeId) {
                            $staff->where('office_id', $officeId);
                        });
                });
            })
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($condition, fn (Builder $query) => $query->where('condition', $condition));
    }

    private function searchTokens(string $value): array
    {
        return collect(preg_split('/\s+/', strtolower(trim($value)), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $token) => trim($token))
            ->filter(fn (string $token) => $token !== '')
            ->take(5)
            ->values()
            ->all();
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

    /**
     * Peripheral records linked to this parent computer property number.
     */
    public function linkedPeripherals(): HasMany
    {
        return $this->hasMany(self::class, 'part_of_property_number', 'property_number');
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
