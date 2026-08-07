<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class PreventiveMaintenanceQualityExport implements WithMultipleSheets
{
    public function __construct(private readonly array $data)
    {
    }

    public function sheets(): array
    {
        return [
            new PreventiveMaintenanceQualitySheet($this->data),
            new PreventiveMaintenanceQualityGraphSheet($this->data),
        ];
    }
}

final class PreventiveMaintenanceQualitySheet implements FromView, WithDrawings, WithEvents
{
    private int $dataStartRow = 11;

    private int $dataEndRow;

    private int $summaryStartRow;

    private int $footerRow;

    public function __construct(private readonly array $data)
    {
        $count = max(1, $this->data['rows']->count());
        $this->dataEndRow = $this->dataStartRow + $count - 1;
        $this->summaryStartRow = $this->dataEndRow + 2;
        // Summary (3 rows), spacer, prepared label, spacer, name, role,
        // then the controlled-form footer.
        $this->footerRow = $this->summaryStartRow + 8;
    }

    public function view(): View
    {
        return view('admin.reports.preventive-maintenance-quality-excel', $this->data + [
            'dataStartRow' => $this->dataStartRow,
            'summaryStartRow' => $this->summaryStartRow,
            'footerRow' => $this->footerRow,
        ]);
    }

    public function drawings(): array
    {
        $drawings = [];
        $logo = public_path('images/catsu-logo.png');
        $iso = public_path('images/iso-9001-2015.jpg');

        if (is_file($logo)) {
            $drawing = new Drawing();
            $drawing->setName('Catanduanes State University');
            $drawing->setPath($logo);
            $drawing->setHeight(68);
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(4);
            $drawing->setOffsetY(3);
            $drawings[] = $drawing;
        }

        if (is_file($iso)) {
            $drawing = new Drawing();
            $drawing->setName('ISO 9001:2015');
            $drawing->setPath($iso);
            $drawing->setHeight(56);
            $drawing->setCoordinates('L1');
            $drawing->setOffsetX(-48);
            $drawing->setOffsetY(5);
            $drawings[] = $drawing;
        }

        return $drawings;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->setTitle('Quality Objective');

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_LEGAL)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0);
                $sheet->getPageMargins()->setTop(0.25)->setBottom(0.35)->setLeft(0.2)->setRight(0.2);
                $sheet->getPageSetup()->setPrintArea("A1:L{$this->footerRow}");
                $sheet->freezePane("A{$this->dataStartRow}");

                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
                $sheet->getStyle("A1:L{$this->footerRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                $sheet->getStyle('A1:L8')->getFont()->setName('Arial')->setSize(10);
                $sheet->getStyle('B1:K1')->getFont()->setSize(10);
                $sheet->getStyle('B2:K2')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('B3:K3')->getFont()->setSize(10);
                $sheet->getStyle('A4:L4')->getFont()->setBold(true)->setItalic(true)->setSize(11);
                $sheet->getStyle('A5:L5')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A6:L6')->getFont()->setBold(true)->setSize(10);
                $sheet->getStyle('A7:L8')->getFont()->setBold(true)->setSize(10);
                $sheet->getStyle('A1:L1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('A2:L6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A7:L8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // The blue rule mirrors the controlled-form header.
                $sheet->getStyle('A4:L4')->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_MEDIUM)
                    ->getColor()->setARGB('FF7AA7D9');

                $tableEnd = $this->dataEndRow;
                $sheet->getStyle("A10:L{$tableEnd}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB(Color::COLOR_BLACK);
                $sheet->getStyle('A10:L10')->getFont()->setName('Arial')->setBold(true)->setSize(9);
                $sheet->getStyle('A10:L10')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A10:L10')->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFD9EAF7');
                $sheet->getStyle("A{$this->dataStartRow}:A{$tableEnd}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("B{$this->dataStartRow}:H{$tableEnd}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("I{$this->dataStartRow}:J{$tableEnd}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("K{$this->dataStartRow}:L{$tableEnd}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                for ($row = $this->dataStartRow; $row <= $this->dataEndRow; $row++) {
                    $sheet->setCellValue("K{$row}", "=IFERROR(IF(F{$row}/B{$row}>=90%,\"\u{2713}\",\"\"),\"\")");
                    $sheet->setCellValue("L{$row}", "=IFERROR(IF(F{$row}/B{$row}<90%,\"X\",\"\"),\"\")");
                    $sheet->getStyle("A{$row}:L{$row}")->getFont()->setName('Arial')->setSize(10);
                    $sheet->getStyle("K{$row}")->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FF008000');
                    $sheet->getStyle("L{$row}")->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFC00000');
                    $sheet->getRowDimension($row)->setRowHeight(36);
                }

                $checkedRow = $this->summaryStartRow;
                $targetRow = $checkedRow + 1;
                $percentageRow = $checkedRow + 2;
                $sheet->setCellValue("B{$checkedRow}", "=SUM(F{$this->dataStartRow}:F{$this->dataEndRow})");
                $sheet->setCellValue("B{$targetRow}", "=SUM(B{$this->dataStartRow}:B{$this->dataEndRow})");
                // Controlled QO formula: the objective is reported as 100% only
                // when the checked-to-target ratio reaches the 90% threshold.
                $sheet->setCellValue("B{$percentageRow}", "=IFERROR(IF(B{$checkedRow}/B{$targetRow}>=90%,\"100%\",\"\"),\"\")");
                $sheet->getStyle("B{$percentageRow}")->getNumberFormat()->setFormatCode('@');
                $sheet->getStyle("A{$checkedRow}:B{$percentageRow}")->getFont()->setName('Arial')->setBold(true)->setSize(10);
                $sheet->getStyle("A{$checkedRow}:B{$percentageRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // Hidden helper cells keep the graph sheet formula-linked to this sheet.
                $sheet->setCellValue('N2', 'Semiannual Period');
                $sheet->setCellValue('O2', 'Actual');
                $sheet->setCellValue('P2', 'Target');
                foreach (array_values($this->data['chart']['points']) as $index => $point) {
                    $sourceRow = 3 + $index;
                    $sheet->setCellValue("N{$sourceRow}", $point['label']);
                    $sheet->setCellValue("O{$sourceRow}", $point['actual']);
                    $sheet->setCellValue("P{$sourceRow}", $point['target']);
                }
                // Hidden helpers keep the controlled-form performance graph
                // linked to the Quality Objective sheet while allowing its
                // baseline to come from the preceding preventive cycle.
                $sheet->setCellValue('N5', 'Performance actual data');
                $sheet->setCellValue('O5', $this->data['performanceGraph']['actual']);
                $sheet->setCellValue('N6', 'Performance baseline');
                $sheet->setCellValue('O6', $this->data['performanceGraph']['baseline']);
                $sheet->setCellValue('N7', 'Performance completion rate');
                $sheet->setCellValue('O7', $this->data['performanceGraph']['rate']);
                foreach (['N', 'O', 'P'] as $column) {
                    $sheet->getColumnDimension($column)->setVisible(false);
                }

                $sheet->getStyle("A{$this->footerRow}:L{$this->footerRow}")
                    ->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)
                    ->getColor()->setARGB('FF7AA7D9');
                $sheet->getStyle("A{$this->footerRow}:L{$this->footerRow}")
                    ->getFont()->setName('Arial')->setItalic(true)->setSize(9)->getColor()->setARGB('FF555555');
                $sheet->getStyle("A{$this->footerRow}:D{$this->footerRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("E{$this->footerRow}:H{$this->footerRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("I{$this->footerRow}:L{$this->footerRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $preparedRow = $percentageRow + 2;
                // Leave one blank row between the signatory label and name.
                $nameRow = $preparedRow + 2;
                $roleRow = $nameRow + 1;
                $sheet->getStyle("A{$preparedRow}:L{$roleRow}")->getFont()->setName('Arial')->setSize(10);
                $sheet->getStyle("A{$nameRow}:D{$nameRow}")->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle("I{$nameRow}:L{$nameRow}")->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle("A{$preparedRow}:D{$roleRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("I{$preparedRow}:L{$roleRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $widths = [
                    'A' => 25, 'B' => 15, 'C' => 12, 'D' => 13, 'E' => 13, 'F' => 12,
                    'G' => 11, 'H' => 11, 'I' => 16, 'J' => 26, 'K' => 9, 'L' => 11,
                ];
                foreach ($widths as $column => $width) {
                    $sheet->getColumnDimension($column)->setAutoSize(false)->setWidth($width);
                }
                $sheet->getRowDimension(1)->setRowHeight(24);
                $sheet->getRowDimension(2)->setRowHeight(28);
                $sheet->getRowDimension(3)->setRowHeight(22);
                $sheet->getRowDimension(4)->setRowHeight(22);
                $sheet->getRowDimension(5)->setRowHeight(28);
                $sheet->getRowDimension(6)->setRowHeight(22);
                $sheet->getRowDimension(7)->setRowHeight(22);
                $sheet->getRowDimension(8)->setRowHeight(20);
                $sheet->getRowDimension(10)->setRowHeight(64);
            },
        ];
    }
}

final class PreventiveMaintenanceQualityGraphSheet implements FromView, WithDrawings, WithEvents, WithCharts
{
    public function __construct(private readonly array $data)
    {
    }

    public function view(): View
    {
        return view('admin.reports.preventive-maintenance-quality-graph-excel', $this->data);
    }

    public function drawings(): array
    {
        $drawings = [];
        $logo = public_path('images/catsu-logo.png');
        $iso = public_path('images/iso-9001-2015.jpg');

        if (is_file($logo)) {
            $drawing = new Drawing();
            $drawing->setName('Catanduanes State University');
            $drawing->setPath($logo);
            $drawing->setHeight(58);
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(4);
            $drawing->setOffsetY(3);
            $drawings[] = $drawing;
        }

        if (is_file($iso)) {
            $drawing = new Drawing();
            $drawing->setName('ISO 9001:2015');
            $drawing->setPath($iso);
            $drawing->setHeight(48);
            $drawing->setCoordinates('F1');
            $drawing->setOffsetX(-42);
            $drawing->setOffsetY(5);
            $drawings[] = $drawing;
        }

        return $drawings;
    }

    public function charts(): array
    {
        $categories = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Performance Graph'!\$C\$25:\$D\$25", null, 2);
        $actual = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Performance Graph'!\$C\$28:\$D\$28", '0%', 2, [], 'circle', '5B9BD5', 5);
        $target = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Performance Graph'!\$C\$29:\$D\$29", '0%', 2, [], 'diamond', 'C0504D', 5);
        $actual->setFillColor('5B9BD5');
        $target->setFillColor('C0504D');

        $series = new DataSeries(
            DataSeries::TYPE_LINECHART,
            DataSeries::GROUPING_STANDARD,
            [0, 1],
            [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Performance Graph'!\$A\$28", null, 1),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Performance Graph'!\$A\$29", null, 1),
            ],
            [$categories],
            [$actual, $target],
            DataSeries::DIRECTION_COL,
            false,
            DataSeries::STYLE_LINEMARKER
        );

        $chart = new Chart(
            'QualityObjectivePerformance',
            new Title('Preventive Maintenance Performance'),
            new Legend(Legend::POSITION_BOTTOM, null, false),
            new PlotArea(null, [$series]),
            true,
            DataSeries::EMPTY_AS_GAP,
            new Title('Semiannual period'),
            new Title('Accomplishment (%)')
        );
        $chart->setTopLeftPosition('A10');
        $chart->setBottomRightPosition('E24');
        $chart->getChartAxisY()->setAxisNumberProperties('0%', true, 0);
        $chart->getChartAxisY()->setAxisOptionsProperties(
            'nextTo',
            null,
            null,
            null,
            null,
            null,
            '0',
            '1',
            '0.1'
        );

        return [$chart];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->setTitle('Performance Graph');
                $sheet->setShowGridlines(false);
                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_LEGAL)
                    ->setFitToWidth(1)
                    ->setFitToHeight(1);
                $sheet->getPageMargins()->setTop(0.25)->setBottom(0.35)->setLeft(0.2)->setRight(0.2);
                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
                $sheet->getStyle('A1:F39')->getFont()->setName('Arial')->setSize(10);
                $sheet->getStyle('A1:F39')
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                $performance = $this->data['performanceGraph'];
                $selectedColumn = (int) $performance['selected_semester'] === 1 ? 'C' : 'D';
                $otherColumn = $selectedColumn === 'C' ? 'D' : 'C';

                // Keep actual and baseline values linked to hidden helper
                // cells on the Quality Objective sheet.
                $sheet->setCellValue("{$selectedColumn}26", "=IF('Quality Objective'!\$O\$5=\"\",\"\",'Quality Objective'!\$O\$5)");
                $sheet->setCellValue("{$selectedColumn}27", "=IF('Quality Objective'!\$O\$6=\"\",\"\",'Quality Objective'!\$O\$6)");
                $sheet->setCellValue("{$otherColumn}26", null);
                $sheet->setCellValue("{$otherColumn}27", null);

                foreach (['C', 'D'] as $column) {
                    if ($column === $selectedColumn) {
                        $sheet->setCellValue("{$column}28", "=IF('Quality Objective'!\$O\$7=\"\",\"\",'Quality Objective'!\$O\$7)");
                    } else {
                        $sheet->setCellValue("{$column}28", null);
                    }
                    $sheet->setCellValue("{$column}29", 1);
                    // This is intentionally the controlled-form formula:
                    // empty actual values stay empty, otherwise MET/UNMET is
                    // determined against the 100% target.
                    $sheet->setCellValue("{$column}30", "=IF({$column}28=\"\",\"\",IF({$column}28>={$column}29,\"MET\",\"UNMET\"))");
                }

                $sheet->getStyle('A1:F1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('B1:F1')->getFont()->setSize(10);
                $sheet->getStyle('B2:F2')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('B3:F3')->getFont()->setSize(10);
                $sheet->getStyle('A4:F4')->getFont()->setBold(true)->setItalic(true)->setSize(11);
                $sheet->getStyle('A5:F5')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A6:F6')->getFont()->setSize(10);
                $sheet->getStyle('A9:F10')->getFont()->setSize(10);
                $sheet->getStyle('A9:F10')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('A4:F4')->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB('FF7AA7D9');

                $sheet->getStyle('A25:D30')
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB(Color::COLOR_BLACK);
                $sheet->getStyle('A25:D30')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A25:D25')->getFont()->setBold(true)->setSize(10);
                $sheet->getStyle('C26:D27')->getNumberFormat()->setFormatCode('0');
                $sheet->getStyle('C28:D29')->getNumberFormat()->setFormatCode('0%');

                // Controlled-form colors: actual is blue, target is red,
                // and status remains yellow until the formula resolves.
                $sheet->getStyle('A28:D28')->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FF9DB9D9');
                $sheet->getStyle('A29:D29')->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFD99696');
                $sheet->getStyle('A30:D30')->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFFFFF66');
                $sheet->getStyle('A28:B30')->getFont()->setBold(true);

                $metStyle = new Style(false, true);
                $metStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF00B050');
                $metStyle->getFont()->setBold(true)->getColor()->setARGB('FF000000');
                $unmetStyle = new Style(false, true);
                $unmetStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF0000');
                $unmetStyle->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $met = new Conditional();
                $met->setConditionType(Conditional::CONDITION_EXPRESSION)->setConditions(['C30="MET"']);
                $met->setStyle($metStyle);
                $unmet = new Conditional();
                $unmet->setConditionType(Conditional::CONDITION_EXPRESSION)->setConditions(['C30="UNMET"']);
                $unmet->setStyle($unmetStyle);
                $sheet->getStyle('C30:D30')->setConditionalStyles([$met, $unmet]);

                $sheet->getStyle('A33:F37')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('A36:B36')->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle('D36:E36')->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle('A36:B37')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('D36:E37')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle('A39:F39')->getBorders()->getTop()
                    ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB('FF7AA7D9');
                $sheet->getStyle('A39:F39')->getFont()->setItalic(true)->setSize(9)->getColor()->setARGB('FF555555');
                $sheet->getStyle('A39:B39')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('C39')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('D39:F39')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                foreach (['A' => 8.33, 'B' => 35.55, 'C' => 35.44, 'D' => 13, 'E' => 9.66, 'F' => 10] as $column => $width) {
                    $sheet->getColumnDimension($column)->setAutoSize(false)->setWidth($width);
                }
                $sheet->getRowDimension(1)->setRowHeight(24);
                $sheet->getRowDimension(2)->setRowHeight(28);
                $sheet->getRowDimension(3)->setRowHeight(22);
                $sheet->getRowDimension(4)->setRowHeight(22);
                $sheet->getRowDimension(5)->setRowHeight(22);
                $sheet->getRowDimension(6)->setRowHeight(22);
                $sheet->getRowDimension(25)->setRowHeight(30);
                $sheet->getRowDimension(26)->setRowHeight(22);
                $sheet->getRowDimension(27)->setRowHeight(22);
                $sheet->getRowDimension(28)->setRowHeight(22);
                $sheet->getRowDimension(29)->setRowHeight(22);
                $sheet->getRowDimension(30)->setRowHeight(22);
                $sheet->getPageSetup()->setPrintArea('A1:F39');
            },
        ];
    }
}
