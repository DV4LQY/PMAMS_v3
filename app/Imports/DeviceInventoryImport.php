<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads equipment/import spreadsheets without writing rows directly.
 * DeviceController validates and persists each row so inventory and issuance
 * imports can share the same transaction and location-aware staff lookup.
 */
class DeviceInventoryImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        // The rows are read through Excel::toCollection().
    }
}
