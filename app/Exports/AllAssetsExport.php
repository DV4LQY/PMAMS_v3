<?php

namespace App\Exports;

use App\Http\Controllers\Admin\ReportController;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class AllAssetsExport implements FromView, ShouldAutoSize, WithEvents
{
    public function __construct(
        protected array $filters = []
    ) {}

    public function view(): View
    {
        $devices = ReportController::assetsQuery($this->filters)
            ->orderByDesc('id')
            ->get();

        return view('admin.reports.assets-export', [
            'devices' => $devices,
            'generatedAt' => now(),
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_LEGAL);

                $sheet->freezePane('A4');
                $sheet->getStyle("A1:J{$highestRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $sheet->getStyle("A3:J{$highestRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('A1:J3')->getFont()->setBold(true);
            },
        ];
    }
}
