<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Location;
use App\Models\Office;

class Device extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'device_type_id',
        'property_number',
        'part_of_property_number',
        'serial_number',
        'computer_name',
        'brand',
        'model',
        'network_device_type',
        'location_deployed',
        'location_deployed_id',
        'office_deployed_id',
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
                            ->orWhere('network_device_type', 'like', $like)
                            ->orWhere('location_deployed', 'like', $like)
                            ->orWhere('mac_address', 'like', $like)
                            ->orWhereHas('deployedLocation', function (Builder $location) use ($like) {
                                $location->where('name', 'like', $like)
                                    ->orWhere('code', 'like', $like);
                            })
                            ->orWhereHas('deployedOffice', function (Builder $office) use ($like) {
                                $office->where('name', 'like', $like)
                                    ->orWhereHas('location', function (Builder $location) use ($like) {
                                        $location->where('name', 'like', $like)
                                            ->orWhere('code', 'like', $like);
                                    });
                            })
                            ->orWhereHas('type', fn (Builder $type) => $type->where('name', 'like', $like))
                            ->orWhereHas('currentAssignment.location', function (Builder $location) use ($like) {
                                $location->where('name', 'like', $like)
                                    ->orWhere('code', 'like', $like);
                            })
                            ->orWhereHas('currentAssignment.office', function (Builder $office) use ($like) {
                                $office->where('name', 'like', $like)
                                    ->orWhereHas('location', function (Builder $location) use ($like) {
                                        $location->where('name', 'like', $like)
                                            ->orWhere('code', 'like', $like);
                                    });
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
                            })
                            ->orWhere(function (Builder $inherited) use ($like) {
                                $inherited->whereDoesntHave('currentAssignment.staff')
                                    ->whereHas('parentProperty.currentAssignment.staff', function (Builder $staff) use ($like) {
                                        $staff->where('first_name', 'like', $like)
                                            ->orWhere('last_name', 'like', $like)
                                            ->orWhere('email', 'like', $like)
                                            ->orWhere('position', 'like', $like);
                                    });
                            });
                    });
                }
            })
            ->when($typeId, fn (Builder $query) => $query->where('device_type_id', $typeId))
            ->when($locationId, function (Builder $query) use ($locationId) {
                $query->where(function (Builder $match) use ($locationId) {
                    // An active assignment is authoritative. For legacy or
                    // unissued equipment with no active assignment, use the
                    // registered deployment references as the fallback.
                    $match->whereHas('currentAssignment', function (Builder $assignment) use ($locationId) {
                        $assignment->where(function (Builder $assignmentLocation) use ($locationId) {
                            $assignmentLocation->where('location_id', $locationId)
                                ->orWhereHas('office', function (Builder $office) use ($locationId) {
                                    $office->where('location_id', $locationId);
                                })
                                ->orWhereHas('staff.office', function (Builder $office) use ($locationId) {
                                    $office->where('location_id', $locationId);
                                });
                        });
                    })->orWhere(function (Builder $inherited) use ($locationId) {
                        $inherited->whereDoesntHave('currentAssignment.staff')
                            ->whereHas('parentProperty.currentAssignment', function (Builder $assignment) use ($locationId) {
                                $assignment->where(function (Builder $assignmentLocation) use ($locationId) {
                                    $assignmentLocation->where('location_id', $locationId)
                                        ->orWhereHas('office', function (Builder $office) use ($locationId) {
                                            $office->where('location_id', $locationId);
                                        })
                                        ->orWhereHas('staff.office', function (Builder $office) use ($locationId) {
                                            $office->where('location_id', $locationId);
                                        });
                                });
                            });
                    })->orWhere(function (Builder $deployment) use ($locationId) {
                        $deployment->whereDoesntHave('currentAssignment')
                            ->whereDoesntHave('parentProperty.currentAssignment')
                            ->where(function (Builder $deploymentLocation) use ($locationId) {
                                $deploymentLocation->where('location_deployed_id', $locationId)
                                    ->orWhereHas('deployedOffice', function (Builder $office) use ($locationId) {
                                        $office->where('location_id', $locationId);
                                    });
                            });
                    });
                });
            })
            ->when($officeId, function (Builder $query) use ($officeId) {
                $query->where(function (Builder $match) use ($officeId) {
                    $match->whereHas('currentAssignment', function (Builder $assignment) use ($officeId) {
                        $assignment->where('office_id', $officeId)
                            ->orWhereHas('staff', function (Builder $staff) use ($officeId) {
                                $staff->where('office_id', $officeId);
                            });
                    })->orWhere(function (Builder $inherited) use ($officeId) {
                        $inherited->whereDoesntHave('currentAssignment.staff')
                            ->whereHas('parentProperty.currentAssignment', function (Builder $assignment) use ($officeId) {
                                $assignment->where('office_id', $officeId)
                                    ->orWhereHas('staff', function (Builder $staff) use ($officeId) {
                                        $staff->where('office_id', $officeId);
                                    });
                            });
                    })->orWhere(function (Builder $deployment) use ($officeId) {
                        $deployment->whereDoesntHave('currentAssignment')
                            ->whereDoesntHave('parentProperty.currentAssignment')
                            ->where('office_deployed_id', $officeId);
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

    /**
     * Registered Location used as the deployment reference for network devices.
     * The legacy location_deployed text is retained for older/imported records.
     */
    public function deployedLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_deployed_id');
    }

    /** Registered Office used as the deployment reference for network devices. */
    public function deployedOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_deployed_id');
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
     * Resolve the assignment context that should be shown to users.
     *
     * A linked peripheral may have a location-only assignment left over from
     * an earlier workflow while its parent computer carries the staff
     * assignment. A direct child staff assignment remains authoritative;
     * location-only child rows inherit the parent's active staff, office, and
     * location for display and filtering. This is deliberately read-only;
     * linking a peripheral must not create duplicate assignment history rows.
     */
    public function effectiveAssignmentContext(): array
    {
        $childAssignment = $this->currentAssignment;
        $parent = null;

        if (filled($this->part_of_property_number)) {
            $parent = $this->relationLoaded('parentProperty')
                ? $this->getRelation('parentProperty')
                : $this->parentProperty()->first();

            $parent?->loadMissing([
                'currentAssignment.staff.office.location',
                'currentAssignment.office.location',
                'currentAssignment.location',
            ]);
        }

        $parentAssignment = $parent?->currentAssignment;
        $childStaff = $childAssignment?->staff;
        $parentStaff = $parentAssignment?->staff;
        $staff = $childStaff ?: $parentStaff;

        // A child assignment without a staff member is a legacy
        // location-only record. Once the parent has a staff assignment, the
        // parent is the current source of truth for the linked equipment's
        // staff, office, and location; otherwise a direct child assignment
        // remains authoritative.
        $usesParentAssignment = ! $childStaff && (bool) $parentAssignment;
        if ($usesParentAssignment) {
            $office = $parentAssignment?->office
                ?: $parentStaff?->office
                ?: $childAssignment?->office
                ?: $childStaff?->office;

            $location = $parentAssignment?->location
                ?: $parentAssignment?->office?->location
                ?: $parentStaff?->office?->location
                ?: $childAssignment?->location
                ?: $childAssignment?->office?->location
                ?: $childStaff?->office?->location;
        } else {
            $office = $childAssignment?->office
                ?: $childStaff?->office
                ?: $parentAssignment?->office
                ?: $parentStaff?->office;

            $location = $childAssignment?->location
                ?: $childAssignment?->office?->location
                ?: $childStaff?->office?->location
                ?: $parentAssignment?->location
                ?: $parentAssignment?->office?->location
                ?: $parentStaff?->office?->location;
        }

        return [
            'assignment' => $usesParentAssignment ? ($parentAssignment ?: $childAssignment) : ($childAssignment ?: $parentAssignment),
            'child_assignment' => $childAssignment,
            'parent_assignment' => $parentAssignment,
            'staff' => $staff,
            'office' => $office,
            'location' => $location,
            'inherited_staff' => ! $childStaff && (bool) $parentStaff,
            'inherited_context' => $usesParentAssignment,
        ];
    }

    /**
     * Peripheral records linked to this parent computer property number.
     */
    public function linkedPeripherals(): HasMany
    {
        return $this->hasMany(self::class, 'part_of_property_number', 'property_number');
    }

    /**
     * The standalone equipment record this peripheral belongs to.
     *
     * Property numbers are intentionally used instead of the numeric device
     * id because that is the persisted relationship for linked equipment.
     */
    public function parentProperty(): BelongsTo
    {
        return $this->belongsTo(self::class, 'part_of_property_number', 'property_number');
    }

    /**
     * Return the canonical property number for generated equipment linked to
     * a standalone parent computer.
     *
     * The parent property number is retained verbatim so the relationship is
     * easy to recognize and remains within the devices table's 50-character
     * limit. Invalid or overlong values return null so the caller can use a
     * collision-safe temporary number instead.
     */
    public static function linkedPropertyNumberForParent(
        ?string $equipmentType,
        ?string $parentPropertyNumber
    ): ?string {
        $parentPropertyNumber = trim((string) $parentPropertyNumber);

        if ($parentPropertyNumber === ''
            || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9\-\/]*$/', $parentPropertyNumber)) {
            return null;
        }

        $typeKey = strtolower(trim((string) $equipmentType));
        $typeSegment = $typeKey === 'network device'
            ? 'NET'
            : strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', trim((string) $equipmentType)) ?: 'EQUIPMENT');
        $typeSegment = substr($typeSegment, 0, 30);
        $candidate = $typeSegment . '-' . $parentPropertyNumber;

        return strlen($candidate) <= 50 ? $candidate : null;
    }

    /**
     * Backward-compatible Monitor-specific alias for existing generation and
     * desktop-monitor repair workflows.
     */
    public static function monitorPropertyNumberForParent(?string $parentPropertyNumber): ?string
    {
        return self::linkedPropertyNumberForParent('Monitor', $parentPropertyNumber);
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(DeviceMaintenanceRecord::class);
    }

    public function maintenanceRecordsIncludingTrashed(): HasMany
    {
        return $this->hasMany(DeviceMaintenanceRecord::class)->withTrashed();
    }

    public function maintenancePhotos(): HasMany
    {
        return $this->hasMany(DeviceMaintenancePhoto::class);
    }

    public function latestMaintenanceRecord(): HasOne
    {
        return $this->hasOne(DeviceMaintenanceRecord::class)
            ->latestOfMany('maintenance_date');
    }

    /**
     * Return the latest inventory create/update audit entry for this device.
     *
     * Activity logs use the model's basename ("Device") as their subject
     * type. Restricting this relationship to inventory mutations keeps the
     * Add/Edit form metadata from being replaced by an issuance, checklist,
     * or other operational event.
     */
    public function latestAuditLog(): HasOne
    {
        return $this->hasOne(ActivityLog::class, 'subject_id')
            ->where('subject_type', 'Device')
            ->whereIn('action', ['created', 'updated'])
            ->latestOfMany('created_at');
    }

    /**
     * Return the newest maintenance date known for this equipment itself.
     *
     * The denormalized device column is retained for fast inventory reads,
     * while the history relation covers checklist records saved before that
     * column was synchronized.
     */
    private function latestOwnMaintenanceDate(): ?Carbon
    {
        return collect([
            $this->last_maintenance_date,
            $this->latestMaintenanceRecord?->maintenance_date,
        ])
            ->filter(fn ($date) => filled($date))
            ->map(fn ($date) => Carbon::parse($date))
            ->sortBy(fn (Carbon $date) => $date->getTimestamp())
            ->last();
    }

    /**
     * Return the date that should be shown as the last maintenance date.
     *
     * Linked/part-of-property equipment inherits the latest saved checklist
     * date from its standalone parent. A newer date recorded directly on the
     * child remains authoritative, so an older parent checklist cannot make
     * the child appear to go backwards in time.
     */
    public function effectiveLastMaintenanceDate(): ?Carbon
    {
        $dates = collect([$this->latestOwnMaintenanceDate()]);

        if (filled($this->part_of_property_number)) {
            $parent = $this->relationLoaded('parentProperty')
                ? $this->getRelation('parentProperty')
                : $this->parentProperty()->with('latestMaintenanceRecord')->first();

            if ($parent) {
                $dates->push($parent->latestOwnMaintenanceDate());
            }
        }

        return $dates
            ->filter(fn ($date) => $date instanceof Carbon)
            ->sortBy(fn (Carbon $date) => $date->getTimestamp())
            ->last();
    }

    /**
     * Copy the parent checklist date into a linked child when it is newer.
     * Returns true when the child record was updated.
     */
    public function syncLastMaintenanceDateFromParent(?self $parent = null): bool
    {
        if (blank($this->part_of_property_number)) {
            return false;
        }

        $parent ??= $this->relationLoaded('parentProperty')
            ? $this->getRelation('parentProperty')
            : $this->parentProperty()->with('latestMaintenanceRecord')->first();

        $parentDate = $parent?->latestOwnMaintenanceDate();
        if (! $parentDate) {
            return false;
        }

        $childDate = $this->last_maintenance_date
            ? Carbon::parse($this->last_maintenance_date)
            : null;
        if ($childDate && $childDate->greaterThanOrEqualTo($parentDate)) {
            return false;
        }

        $this->update(['last_maintenance_date' => $parentDate->toDateString()]);

        return true;
    }

    /**
     * Return the acquisition date that should be displayed for this record.
     *
     * A peripheral belongs to its standalone parent property for acquisition
     * details as well as maintenance history.  Resolve the parent only when
     * the relationship is present so unlinked equipment keeps its own value.
     */
    public function effectiveDateAcquired(?self $parent = null): ?Carbon
    {
        if (filled($this->part_of_property_number)) {
            $parent ??= $this->resolvedParentPropertyForInheritance();

            if ($parent) {
                return $this->asAcquisitionDate($parent->date_acquired);
            }
        }

        return $this->asAcquisitionDate($this->date_acquired);
    }

    /**
     * Return the unit price that should be displayed for this record.
     *
     * A linked child mirrors the parent's amount, including a null amount.
     * Unlinked equipment continues to use its own stored unit price.
     */
    public function effectiveUnitPrice(?self $parent = null): mixed
    {
        if (filled($this->part_of_property_number)) {
            $parent ??= $this->resolvedParentPropertyForInheritance();

            if ($parent) {
                return $parent->unit_price;
            }
        }

        return $this->unit_price;
    }

    /**
     * Keep a linked child's acquisition fields synchronized with its parent.
     * Returns true when either persisted value changed.
     */
    public function syncInheritedAcquisitionFromParent(?self $parent = null): bool
    {
        if (blank($this->part_of_property_number)) {
            return false;
        }

        $parent ??= $this->resolvedParentPropertyForInheritance();
        if (! $parent) {
            return false;
        }

        $parentDate = $this->asAcquisitionDate($parent->date_acquired);
        $childDate = $this->asAcquisitionDate($this->date_acquired);
        $updates = [];

        if (($childDate?->toDateString()) !== ($parentDate?->toDateString())) {
            $updates['date_acquired'] = $parentDate?->toDateString();
        }

        $parentPrice = $parent->unit_price;
        $childPrice = $this->unit_price;
        $pricesMatch = ($parentPrice === null || $parentPrice === '') && ($childPrice === null || $childPrice === '')
            ? true
            : ($parentPrice !== null && $childPrice !== null && (float) $parentPrice === (float) $childPrice);

        if (! $pricesMatch) {
            $updates['unit_price'] = $parentPrice;
        }

        if ($updates === []) {
            return false;
        }

        $this->update($updates);

        return true;
    }

    /**
     * Resolve the current parent while avoiding a stale eager-loaded relation
     * after a part_of_property_number value has been changed in memory.
     */
    private function resolvedParentPropertyForInheritance(?self $parent = null): ?self
    {
        if ($parent) {
            return $parent;
        }

        if ($this->relationLoaded('parentProperty')) {
            $loadedParent = $this->getRelation('parentProperty');
            if ($loadedParent && (string) $loadedParent->property_number === (string) $this->part_of_property_number) {
                return $loadedParent;
            }
        }

        return $this->parentProperty()->first();
    }

    private function asAcquisitionDate(mixed $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        return $value instanceof Carbon ? $value->copy() : Carbon::parse($value);
    }
}
