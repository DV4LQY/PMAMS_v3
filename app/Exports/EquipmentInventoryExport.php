<?php

namespace App\Exports;

use App\Models\Device;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EquipmentInventoryExport implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private readonly array $filters = []
    ) {}

    public function query(): Builder
    {
        return Device::query()
            ->with([
                'type',
                'deployedLocation',
                'deployedOffice.location',
                'currentAssignment.staff.office.location',
                'currentAssignment.office.location',
                'currentAssignment.location',
            ])
            ->filterInventory($this->filters)
            ->orderByRaw('COALESCE(part_of_property_number, property_number)')
            ->orderBy('property_number');
    }

    public function headings(): array
    {
        return [
            'Property Number',
            'Child Property Number',
            'Equipment Type',
            'Serial Number',
            'Computer Name',
            'Brand',
            'Model',
            'Network Device Type',
            'Location Deployed',
            'MAC Address',
            'Memory',
            'Processor',
            'Storage',
            'Form Factor',
            'OS Version',
            'OS License',
            'MS Office Version',
            'MS Office License',
            'Unit Price',
            'Date Acquired',
            'Availability',
            'Condition',
            'Last Maintenance Date',
            'Maintenance Remarks',
            'End User',
            'End User Email',
            'Office',
            'Location',
            'Issued At',
            'Issuance Remarks',
        ];
    }

    public function map($device): array
    {
        $assignment = $device->currentAssignment;
        $staff = $assignment?->staff;
        $office = $assignment?->office ?: $staff?->office;
        $location = $assignment?->location ?? $office?->location;

        return [
            // Keep linked peripherals aligned with their parent group while
            // retaining each equipment record's unique number in the child column.
            $device->part_of_property_number ?: $device->property_number,
            $device->part_of_property_number ? $device->property_number : null,
            $device->type?->name,
            $device->serial_number,
            $device->computer_name ?: data_get($device->specs, 'computer_name'),
            $device->brand,
            $device->model,
            $device->network_device_type,
            $device->deployedOffice
                ? trim($device->deployedOffice->name . ' - ' . $device->deployedOffice->location?->name . ($device->deployedOffice->location?->code ? ' (' . $device->deployedOffice->location->code . ')' : ''))
                : ($device->deployedLocation
                    ? trim($device->deployedLocation->name . ($device->deployedLocation->code ? ' (' . $device->deployedLocation->code . ')' : ''))
                    : $device->location_deployed),
            $device->mac_address,
            data_get($device->specs, 'memory'),
            data_get($device->specs, 'processor'),
            data_get($device->specs, 'storage'),
            data_get($device->specs, 'form_factor'),
            $device->os_version,
            $device->os_license,
            $device->ms_office_version,
            $device->ms_office_license,
            $device->unit_price !== null ? (float) $device->unit_price : null,
            $device->date_acquired?->format('Y-m-d'),
            ucfirst((string) $device->status),
            ucfirst((string) $device->condition),
            $device->last_maintenance_date?->format('Y-m-d'),
            $device->maintenance_remarks,
            $staff ? trim($staff->first_name . ' ' . $staff->last_name) : null,
            $staff?->email,
            $office?->name,
            $location?->name,
            $assignment?->issued_at?->format('Y-m-d H:i:s'),
            $assignment?->remarks,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1D4ED8'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:AD{$highestRow}");
                $sheet->getStyle("A1:AD{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle("A2:AD{$highestRow}")->getAlignment()->setWrapText(true);

                foreach (['A', 'B', 'D', 'E', 'H', 'I', 'J'] as $column) {
                    for ($row = 2; $row <= $highestRow; $row++) {
                        $value = $sheet->getCell("{$column}{$row}")->getValue();
                        if ($value !== null && $value !== '') {
                            $sheet->setCellValueExplicit("{$column}{$row}", (string) $value, DataType::TYPE_STRING);
                        }
                    }
                }
            },
        ];
    }
}
