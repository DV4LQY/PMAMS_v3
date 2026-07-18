<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithLimit;

/**
 * Reads equipment/import spreadsheets without writing rows directly.
 * DeviceController validates and persists each complete row so equipment
 * specifications and an optional location-aware issuance share one transaction.
 */
class DeviceInventoryImport implements ToCollection, WithHeadingRow, WithLimit
{
    public const MAX_ROWS = 5000;

    public function collection(Collection $rows): void
    {
        // The rows are read through Excel::toCollection().
    }

    public function limit(): int
    {
        // Read one extra row so the controller can reject files over the limit.
        return self::MAX_ROWS + 1;
    }
}
