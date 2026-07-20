<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EquipmentImportTemplateExport implements FromArray, ShouldAutoSize, WithHeadings
{
    public function headings(): array
    {
        return [
            'property_number', 'equipment_type', 'serial_number', 'brand', 'model',
            'computer_name', 'mac_address', 'unit_price', 'date_acquired', 'condition',
            'part_of_property_number',
            'status', 'os_version', 'os_license', 'ms_office_version', 'ms_office_license',
            'memory', 'storage', 'form_factor', 'last_maintenance_date', 'maintenance_remarks', 'notes',
            'issued_user_email', 'issued_user', 'first_name', 'last_name', 'office',
            'location_code', 'issued_at', 'issuance_remarks',
        ];
    }

    public function array(): array
    {
        return [[
            'PN-2026-0001', 'Laptop', 'SN-0001', 'Example', 'Model', 'PC-001', '',
            '25000', '2026-01-15', 'serviceable', '', 'issued', 'Windows 11',
            'OEM Licensed', 'Microsoft 365', 'OEM Licensed', '16 GB', '512 GB SSD',
            '', '', '', '', 'juan@example.edu', 'Juan Dela Cruz', '', '', 'ICTU',
            'MAIN', '2026-01-15', 'Initial issuance',
        ]];
    }
}
