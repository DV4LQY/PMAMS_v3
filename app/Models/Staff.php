<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    protected $table = 'staff';

    protected $fillable = [
        'office_id', 'first_name', 'last_name', 'position', 'email', 'phone', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DeviceAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->assignments()->whereNull('returned_at');
    }
}