<?php

namespace App\Models;

/**
 * Backward-compatible alias while the system is being renamed from College to Location.
 * New code should use App\Models\Location.
 */
class College extends Location
{
    protected $table = 'locations';
}
