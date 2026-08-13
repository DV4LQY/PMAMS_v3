<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * XLSX export for the filtered Maintenance Attention report.
 *
 * The view and the PDF share the same payload, so the selected Location,
 * Office, equipment type, condition, status, text, and recommendation
 * filters produce identical rows in both formats. Location and Office remain
 * valid filters, but are intentionally not repeated as report columns.
 */
class MaintenanceAttentionExport implements FromView, WithDrawings, WithEvents
{
    public function __construct(private readonly array $data)
    {
    }

    public function view(): View
    {
        return view('admin.reports.maintenance-attention-excel', $this->data);
    }

    public function drawings(): array
    {
        $drawings = [];

        if (is_file($this->data['logoPath'] ?? '')) {
            $logo = new Drawing();
            $logo->setName('Catanduanes State University');
            $logo->setPath($this->data['logoPath']);
            $logo->setHeight(48);
            $logo->setCoordinates('A1');
            $logo->setOffsetX(4);
            $logo->setOffsetY(3);
            $drawings[] = $logo;
        }

        if (is_file($this->data['isoLogoPath'] ?? '')) {
            $iso = new Drawing();
            $iso->setName('ISO 9001:2015');
            $iso->setPath($this->data['isoLogoPath']);
            $iso->setHeight(42);
            $iso->setCoordinates('J1');
            $iso->setOffsetX(-46);
            $iso->setOffsetY(4);
            $drawings[] = $iso;
        }

        return $drawings;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->setTitle('Maintenance Attention');
                $sheet->setShowGridlines(false);

                $lastRow = max(9, $sheet->getHighestRow());
                $tableHeaderRow = 8;
                $tableStartRow = 9;
                // The view renders one empty data row when there are no matches,
                // so keep the worksheet row map aligned in both empty and
                // populated exports.
                $tableEndRow = $tableStartRow + max(1, $this->data['rows']->count()) - 1;
                $legendHeaderRow = $tableEndRow + 1;
                $legendPriorityRow = $legendHeaderRow + 1;
                $legendNoteRow = $legendHeaderRow + 2;
                // The view has one spacer row after the legend, followed by
                // the sign-off label, signature line, name, and position.
                $signoffLabelRow = $legendHeaderRow + 4;
                $signoffLineRow = $signoffLabelRow + 1;
                $signoffNameRow = $signoffLabelRow + 2;
                $signoffPositionRow = $signoffLabelRow + 3;
                $footerRow = $signoffLabelRow + 5;
                $lastRow = max($lastRow, $footerRow);

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_LEGAL)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0);
                $sheet->getPageMargins()->setTop(0.25)->setBottom(0.35)->setLeft(0.25)->setRight(0.25)->setFooter(0.2);
                $generatedDate = $this->data['generatedAt']->format('Y-m-d');
                $sheet->getHeaderFooter()->setOddFooter(
                    '&LPMAMS Maintenance Attention Report&RGenerated ' . $generatedDate
                );
                $sheet->getHeaderFooter()->setEvenFooter(
                    '&LPMAMS Maintenance Attention Report&RGenerated ' . $generatedDate
                );
                $sheet->getPageSetup()->setPrintArea("A1:J{$lastRow}");
                $sheet->freezePane("A{$tableStartRow}");

                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
                $sheet->getStyle("A1:J{$lastRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                $sheet->mergeCells('B1:I1');
                $sheet->mergeCells('B2:I2');
                $sheet->mergeCells('B3:I3');
                $sheet->mergeCells('A4:J4');
                $sheet->mergeCells('A5:J5');
                $sheet->mergeCells('A6:J6');
                $sheet->mergeCells("A{$legendHeaderRow}:J{$legendHeaderRow}");
                $sheet->mergeCells("A{$legendPriorityRow}:J{$legendPriorityRow}");
                $sheet->mergeCells("A{$legendNoteRow}:J{$legendNoteRow}");
                $sheet->mergeCells("A{$signoffLabelRow}:D{$signoffLabelRow}");
                $sheet->mergeCells("E{$signoffLabelRow}:G{$signoffLabelRow}");
                $sheet->mergeCells("H{$signoffLabelRow}:J{$signoffLabelRow}");
                $sheet->mergeCells("A{$signoffLineRow}:D{$signoffLineRow}");
                $sheet->mergeCells("E{$signoffLineRow}:G{$signoffLineRow}");
                $sheet->mergeCells("H{$signoffLineRow}:J{$signoffLineRow}");
                $sheet->mergeCells("A{$signoffNameRow}:D{$signoffNameRow}");
                $sheet->mergeCells("E{$signoffNameRow}:G{$signoffNameRow}");
                $sheet->mergeCells("H{$signoffNameRow}:J{$signoffNameRow}");
                $sheet->mergeCells("A{$signoffPositionRow}:D{$signoffPositionRow}");
                $sheet->mergeCells("E{$signoffPositionRow}:G{$signoffPositionRow}");
                $sheet->mergeCells("H{$signoffPositionRow}:J{$signoffPositionRow}");
                $sheet->mergeCells("A{$footerRow}:E{$footerRow}");
                $sheet->mergeCells("F{$footerRow}:J{$footerRow}");

                $sheet->getStyle('B1:I3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('B1:I3')->getFont()->setName('Arial')->setSize(10);
                $sheet->getStyle('B2:I2')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A4:J4')->getFont()->setItalic(true)->setBold(true)->setSize(10);
                $sheet->getStyle('A5:J5')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A5:J6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A1:J6')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                // The PDF places the blue rule directly below the institutional
                // header; the ICTU line follows immediately underneath it.
                $sheet->getStyle('A3:J3')->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_MEDIUM)
                    ->getColor()->setARGB('FF4472C4');

                $sheet->getStyle("A{$tableHeaderRow}:J{$tableEndRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB(Color::COLOR_BLACK);
                $sheet->getStyle("A{$tableHeaderRow}:J{$tableHeaderRow}")
                    ->getFont()->setBold(true)->setSize(9);
                $sheet->getStyle("A{$tableHeaderRow}:J{$tableHeaderRow}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFD9EAF7');
                $sheet->getStyle("A{$tableHeaderRow}:J{$tableHeaderRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$tableStartRow}:B{$tableEndRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("C{$tableStartRow}:E{$tableEndRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("F{$tableStartRow}:G{$tableEndRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("H{$tableStartRow}:H{$tableEndRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("I{$tableStartRow}:J{$tableEndRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $sheet->getStyle("A{$legendHeaderRow}:J{$legendHeaderRow}")
                    ->getFont()->setBold(true)->setSize(9);
                $sheet->getStyle("A{$legendHeaderRow}:J{$legendNoteRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setWrapText(true);
                $sheet->getStyle("A{$legendHeaderRow}:J{$legendNoteRow}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF3F6F9');

                $sheet->getStyle("A{$signoffLabelRow}:J{$signoffLabelRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("A{$signoffLineRow}:J{$signoffLineRow}")
                    ->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB(Color::COLOR_BLACK);
                $sheet->getStyle("A{$signoffNameRow}:J{$signoffPositionRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$signoffNameRow}:J{$signoffNameRow}")
                    ->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle("A{$footerRow}:J{$footerRow}")
                    ->getBorders()->getTop()
                    ->setBorderStyle(Border::BORDER_MEDIUM)
                    ->getColor()->setARGB('FF4472C4');
                $sheet->getStyle("A{$footerRow}:J{$footerRow}")
                    ->getFont()->setItalic(true)->setSize(9)->getColor()->setARGB('FF666666');
                $sheet->getStyle("A{$footerRow}:F{$footerRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("F{$footerRow}:J{$footerRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                foreach ([
                    'A' => 24, 'B' => 22, 'C' => 15, 'D' => 13, 'E' => 13,
                    'F' => 12, 'G' => 9, 'H' => 48, 'I' => 17, 'J' => 25,
                ] as $column => $width) {
                    $sheet->getColumnDimension($column)->setAutoSize(false)->setWidth($width);
                }

                $sheet->getRowDimension(1)->setRowHeight(20);
                $sheet->getRowDimension(2)->setRowHeight(24);
                $sheet->getRowDimension(3)->setRowHeight(20);
                $sheet->getRowDimension(4)->setRowHeight(20);
                $sheet->getRowDimension(5)->setRowHeight(26);
                $sheet->getRowDimension(6)->setRowHeight(36);
                $sheet->getRowDimension(7)->setRowHeight(7);
                $sheet->getRowDimension($tableHeaderRow)->setRowHeight(38);
                for ($row = $tableStartRow; $row <= $tableEndRow; $row++) {
                    // Let the description/recommendation cell grow with its
                    // content so the first row (or any long row) is never
                    // clipped by the fixed export row height.
                    $sourceRow = $this->data['rows']->get($row - $tableStartRow);
                    $reason = is_array($sourceRow)
                        ? implode('; ', $sourceRow['report_reasons'] ?? [])
                        : '';
                    $lineCount = max(1, (int) ceil(mb_strlen($reason) / 58));
                    $sheet->getRowDimension($row)->setRowHeight(max(38, min(120, 18 * $lineCount + 8)));
                }
                $sheet->getRowDimension($legendHeaderRow)->setRowHeight(18);
                $sheet->getRowDimension($legendPriorityRow)->setRowHeight(32);
                $sheet->getRowDimension($legendNoteRow)->setRowHeight(22);
                $sheet->getRowDimension($signoffLabelRow)->setRowHeight(20);
                $sheet->getRowDimension($signoffLineRow)->setRowHeight(18);
                $sheet->getRowDimension($signoffNameRow)->setRowHeight(22);
                $sheet->getRowDimension($signoffPositionRow)->setRowHeight(20);
                $sheet->getRowDimension($footerRow)->setRowHeight(18);
            },
        ];
    }
}
