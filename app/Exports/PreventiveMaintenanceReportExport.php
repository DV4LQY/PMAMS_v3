<?php

namespace App\Exports;

use App\Models\DeviceAssignment;
use App\Models\Office;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class PreventiveMaintenanceReportExport implements FromView, WithEvents
{
    public function __construct(
        protected ?Office $office = null
    ) {}

    public function view(): View
    {
        $assignments = DeviceAssignment::query()
            ->with([
                'device.type',
                'staff.office.college',
            ])
            ->whereNull('returned_at')
            ->whereHas('device.type', function ($query) {
                $query->whereIn('name', [
                    'Desktop',
                    'Laptop',
                    'Monitor',
                    'Printer',
                    'UPS',
                    'AVR',
                    'Other',
                ]);
            })
            ->when($this->office, function ($query) {
                $query->whereHas('staff', function ($staffQuery) {
                    $staffQuery->where('office_id', $this->office->id);
                });
            })
            ->orderBy('staff_id')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Group devices by office and staff
        |--------------------------------------------------------------------------
        | This keeps devices owned by the same staff in the same row:
        | Desktop / Monitor / Printer / UPS / AVR
        */
        $groupedByOffice = $assignments
            ->groupBy(function ($assignment) {
                return $assignment->staff?->office?->name ?? 'No Office';
            })
            ->map(function ($officeAssignments) {
                return $officeAssignments
                    ->groupBy('staff_id')
                    ->map(function ($staffAssignments) {
                        $staff = $staffAssignments->first()?->staff;

                        $findType = function (string $typeName) use ($staffAssignments) {
                            return $staffAssignments->first(function ($assignment) use ($typeName) {
                                return strtolower($assignment->device?->type?->name ?? '') === strtolower($typeName);
                            });
                        };

                        return [
                            'staff' => $staff,
                            'desktop' => $findType('Desktop') ?: $findType('Laptop'),
                            'monitor' => $findType('Monitor'),
                            'printer' => $findType('Printer'),
                            'ups' => $findType('UPS'),
                            'avr' => $findType('AVR'),
                        ];
                    })
                    ->values();
            });

        return view('admin.reports.preventive-maintenance-excel', [
            'groupedByOffice' => $groupedByOffice,
            'reportDate' => now(),
            'office' => $this->office,
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $highestRow = $sheet->getHighestRow();

                /*
                |--------------------------------------------------------------------------
                | Page Setup
                |--------------------------------------------------------------------------
                | Do not force all columns into 1 page width because it makes the
                | Excel file unreadable. Legal landscape gives more room.
                */
                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_LEGAL)
                    ->setFitToPage(false)
                    ->setScale(75);

                $sheet->getPageMargins()->setTop(0.25);
                $sheet->getPageMargins()->setRight(0.20);
                $sheet->getPageMargins()->setLeft(0.20);
                $sheet->getPageMargins()->setBottom(0.25);

                /*
                |--------------------------------------------------------------------------
                | Freeze header
                |--------------------------------------------------------------------------
                */
                $sheet->freezePane('A6');

                /*
                |--------------------------------------------------------------------------
                | General Styling
                |--------------------------------------------------------------------------
                */
                $sheet->getStyle("A1:AS{$highestRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setWrapText(true);

                $sheet->getStyle("A1:AS{$highestRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                $sheet->getStyle('A1:AS5')
                    ->getFont()
                    ->setBold(true);

                /*
                |--------------------------------------------------------------------------
                | Row Heights
                |--------------------------------------------------------------------------
                */
                $sheet->getRowDimension(1)->setRowHeight(24);
                $sheet->getRowDimension(2)->setRowHeight(22);
                $sheet->getRowDimension(3)->setRowHeight(12);
                $sheet->getRowDimension(4)->setRowHeight(28);
                $sheet->getRowDimension(5)->setRowHeight(46);

                for ($row = 6; $row <= $highestRow; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(42);
                }

                /*
                |--------------------------------------------------------------------------
                | Column Widths
                |--------------------------------------------------------------------------
                | Last column is AR.
                | Wider columns make the exported Excel readable.
                */
                $widths = [
                    'A' => 14,  // Date
                    'B' => 8,   // No.
                    'C' => 22,  // Office

                    // Desktop: D to P
                    'D' => 20,  // Name / Model
                    'E' => 14,  // Brand
                    'F' => 24,  // Issued To
                    'G' => 14,  // Acquired
                    'H' => 24,  // Property #
                    'I' => 22,  // Serial #
                    'J' => 20,  // Computer Name
                    'K' => 14,  // Unit Price
                    'L' => 22,  // MAC
                    'M' => 18,  // OS
                    'N' => 18,  // Storage
                    'O' => 18,  // Form Factor
                    'P' => 16,  // Condition

                    // Monitor: Q to W
                    'Q' => 22,  // Brand / Model
                    'R' => 24,  // Issued To
                    'S' => 14,  // Acquired
                    'T' => 24,  // Property #
                    'U' => 22,  // Serial #
                    'V' => 14,  // Unit Price
                    'W' => 16,  // Condition

                    // Printer: X to AD
                    'X' => 22, // Brand / Model
                    'Y' => 24, // Issued To
                    'Z' => 14, // Acquired
                    'AA' => 24, // Property #
                    'AB' => 22, // Serial #
                    'AC' => 14, // Unit Price
                    'AD' => 16, // Condition

                    // UPS: AE to AK
                    'AE' => 22, // Brand / Model
                    'AF' => 24, // Issued To
                    'AG' => 14, // Acquired
                    'AH' => 24, // Property #
                    'AI' => 22, // Serial #
                    'AJ' => 14, // Unit Price
                    'AK' => 16, // Condition

                    // AVR: AL to AR
                    'AL' => 22, // Brand / Model
                    'AM' => 24, // Issued To
                    'AN' => 14, // Acquired
                    'AO' => 24, // Property #
                    'AP' => 22, // Serial #
                    'AQ' => 14, // Unit Price
                    'AR' => 16, // Condition

                    // Remarks
                    'AS' => 36,
                ];

                foreach ($widths as $column => $width) {
                    $sheet->getColumnDimension($column)->setAutoSize(false);
                    $sheet->getColumnDimension($column)->setWidth($width);
                }

                /*
                |--------------------------------------------------------------------------
                | Keep important number-like values as text
                |--------------------------------------------------------------------------
                | Property numbers, serial numbers, and MAC addresses should not be
                | converted by Excel.
                */
                $textColumns = [
                    'H', 'I', 'L',
                    'T', 'U',
                    'AA', 'AB',
                    'AH', 'AI',
                    'AO', 'AP',
                ];

                foreach ($textColumns as $column) {
                    for ($row = 6; $row <= $highestRow; $row++) {
                        $cell = $column . $row;
                        $value = $sheet->getCell($cell)->getValue();

                        if ($value !== null && $value !== '') {
                            $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
                        }
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Better visual formatting
                |--------------------------------------------------------------------------
                */
                $sheet->getStyle("A4:AS5")
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setShrinkToFit(false);

                $sheet->getStyle("A6:AS{$highestRow}")
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setShrinkToFit(false);

                /*
                |--------------------------------------------------------------------------
                | Hide extra columns after AR if Excel shows blank space
                |--------------------------------------------------------------------------
                */
                foreach (range('AT', 'BA') as $column) {
                    $sheet->getColumnDimension($column)->setVisible(false);
                }
            },
        ];
    }
}
