<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads equipment/import spreadsheets without writing rows directly.
 * DeviceController validates and persists each complete row so equipment
 * specifications and an optional location-aware issuance share one transaction.
 */
class DeviceInventoryImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        // The rows are read through Excel::toCollection().
    }
}
