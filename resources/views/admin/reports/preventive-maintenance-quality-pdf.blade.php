<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quality Objective Monitoring Supporting Schedule</title>
    <style>
        @page { size: 13in 8.5in; margin: 22px 22px 30px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #000; font-family: Arial, sans-serif; font-size: 10px; }
        .header { width: 100%; border-collapse: collapse; }
        .header td { border: 0; padding: 0; vertical-align: middle; }
        .logo { width: 64px; height: 64px; object-fit: contain; }
        .iso { width: 125px; height: 48px; object-fit: contain; }
        .school { padding-left: 9px !important; line-height: 1.15; font-family: Arial, sans-serif; }
        .school > div { font-size: 10px; }
        .school-name { color: #222; font-size: 13px !important; font-weight: bold; text-transform: uppercase; }
        .rule { margin-top: 3px; border-bottom: 1.5px solid #7aa7d9; }
        .unit { margin-top: 5px; font-size: 11px; font-weight: bold; font-style: italic; }
        .title { margin: 6px 0 1px; text-align: center; font-size: 14px; font-weight: bold; }
        .subtitle { text-align: center; font-size: 10px; font-weight: bold; }
        .objective { margin: 7px 0 1px; font-size: 10px; font-weight: bold; }
        .target { margin-bottom: 7px; font-size: 10px; font-weight: bold; }
        .report { width: 100% !important; border-collapse: collapse; table-layout: fixed !important; page-break-inside: auto; }
        .report col { min-width: 0; }
        .report thead { display: table-header-group; }
        .report tr { page-break-inside: avoid; page-break-after: auto; }
        .report th, .report td { border: 0.7px solid #000; padding: 4px 3px; vertical-align: middle; overflow-wrap: anywhere; font-family: Arial, sans-serif; font-size: 10px; }
        .report th { background: #d9eaf7; text-align: center; font-size: 7px; line-height: 1.15; }
        .report td.office, .report td.date, .report td.remarks { text-align: left; }
        .report td.num, .report td.status { text-align: center; }
        .report td.status { font-family: DejaVu Sans, Arial, sans-serif; font-size: 13px; font-weight: bold; }
        .complied { color: #087f23; }
        .not-complied { color: #b00020; }
        .warning { margin-top: 2px; color: #7c2d12; font-size: 8px; }
        .summary { width: 20%; margin-top: 7px; border-collapse: collapse; }
        .summary td { border: 0.7px solid #000; padding: 4px; font-size: 10px; }
        .summary td:first-child { width: 80%; font-weight: bold; }
        .summary td:last-child { text-align: center; font-weight: bold; }
        .signoff { width: 100%; margin-top: 22px; border-collapse: collapse; }
        .signoff td { width: 33.33%; border: 0; padding: 0 28px; vertical-align: top; font-family: Arial, sans-serif; font-size: 10px; }
        .signoff-label { margin-bottom: 16px; }
        .signoff-name { font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .performance-page { page-break-before: always; padding-bottom: 32px; }
        .performance-heading { margin-top: 7px; text-align: center; font-size: 12px; font-weight: bold; }
        .performance-subheading { margin-top: 2px; text-align: center; font-size: 10px; font-weight: bold; }
        .performance-objective { margin-top: 14px; font-size: 10px; font-weight: bold; }
        .performance-target { margin-top: 5px; font-size: 10px; font-weight: bold; }
        .performance-chart { position: relative; display: block; width: 100%; height: 265px; margin-top: 5px; border: 0.7px solid #888; background: #fff; overflow: hidden; }
        .graph-grid-line { position: absolute; height: 0; border-top: 0.8px solid #999; }
        .graph-y-label { position: absolute; width: 43px; color: #000; font-size: 10px; line-height: 10px; text-align: right; }
        .graph-x-label { position: absolute; top: 238px; color: #000; font-size: 10px; line-height: 12px; text-align: center; white-space: nowrap; }
        .graph-segment { position: absolute; height: 2px; transform-origin: 0 0; }
        .graph-point { position: absolute; width: 7px; height: 7px; margin: -3.5px 0 0 -3.5px; }
        .graph-target { background: #c0504d; }
        .graph-actual { background: #4472c4; }
        .graph-point.graph-target { width: 7px; height: 7px; margin: -3.5px 0 0 -3.5px; }
        .graph-point.graph-actual { border-radius: 50%; }
        .performance-data { width: 58%; margin-top: 7px; border-collapse: collapse; table-layout: fixed; }
        .performance-data th, .performance-data td { border: 0.7px solid #000; padding: 5px 4px; font-size: 10px; text-align: center; }
        .performance-data th { font-weight: bold; }
        .performance-data td:first-child { width: 38%; text-align: center; }
        .performance-data .actual { background: #9db9d9; }
        .performance-data .target { background: #d99696; }
        .performance-data .status { background: #ffff66; font-weight: bold; }
        .performance-data .status-met { background: #00b050; }
        .performance-data .status-unmet { background: #ff0000; color: #fff; }
        .performance-signoff { width: 100%; margin-top: 22px; border-collapse: collapse; }
        .performance-signoff td { width: 50%; border: 0; padding: 0 28px; vertical-align: top; font-size: 10px; }
        .performance-signoff .label { margin-bottom: 16px; }
        .performance-signoff .name { font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .footer { position: fixed; right: 0; bottom: 0; left: 0; border-top: 1.5px solid #93b8df; padding-top: 3px; color: #555; font-size: 8px; font-style: italic; }
        .footer span { display: inline-block; width: 33%; }
        .footer span:nth-child(2) { text-align: center; }
        .footer span:nth-child(3) { text-align: right; }
    </style>
</head>
<body>
<?php
$logoPath = public_path('images/catsu-logo-report.jpg');
$isoPath = public_path('images/iso-9001-2015.jpg');
?>
<table class="header"><tr>
    <td style="width:70px"><?php if (is_file($logoPath)): ?><img src="<?= e($logoPath) ?>" class="logo" alt="CatSU"><?php endif; ?></td>
    <td class="school">
        <div><em>Republic of the Philippines</em></div>
        <div class="school-name">CATANDUANES STATE UNIVERSITY</div>
        <div><em>Virac, Catanduanes</em></div>
    </td>
    <td style="width:130px;text-align:right"><?php if (is_file($isoPath)): ?><img src="<?= e($isoPath) ?>" class="iso" alt="ISO 9001:2015"><?php endif; ?></td>
</tr></table>
<div class="rule"></div>
<div class="unit">Information and Communications Technology (ICT) Unit</div>
<div class="title">QUALITY OBJECTIVE MONITORING SUPPORTING SCHEDULE</div>
<div class="subtitle">For {{ $period['label'] }}, CY {{ $period['year'] }}</div>
<div class="objective">Quality Objective No. I: Ensure efficient maintenance of IT equipment and peripherals</div>
<div class="target">Target: {{ $summary['target_percent'] }}%</div>

<table class="report" style="width:100% !important; table-layout:fixed !important">
    <colgroup>
        <col width="22%" style="width:22% !important; min-width:22% !important; max-width:22% !important"><col width="7%" style="width:7% !important; min-width:7% !important; max-width:7% !important"><col width="6%" style="width:6% !important; min-width:6% !important; max-width:6% !important"><col width="7%" style="width:7% !important; min-width:7% !important; max-width:7% !important">
        <col width="7%" style="width:7% !important; min-width:7% !important; max-width:7% !important"><col width="8%" style="width:8% !important; min-width:8% !important; max-width:8% !important"><col width="6%" style="width:6% !important; min-width:6% !important; max-width:6% !important"><col width="6%" style="width:6% !important; min-width:6% !important; max-width:6% !important">
        <col width="10%" style="width:10% !important; min-width:10% !important; max-width:10% !important"><col width="17%" style="width:17% !important; min-width:17% !important; max-width:17% !important"><col width="2%" style="width:2% !important; min-width:2% !important; max-width:2% !important"><col width="2%" style="width:2% !important; min-width:2% !important; max-width:2% !important">
    </colgroup>
    <thead><tr>
        <th style="width:20% !important; min-width:22% !important; max-width:22% !important">Office / Unit</th>
        <th style="width:7% !important; min-width:7% !important; max-width:7% !important">Target no. of computers / peripherals for check-up / maintenance</th>
        <th style="width:7% !important; min-width:6% !important; max-width:6% !important">Condemned ICT equipment returned to Supply</th>
        <th style="width:7% !important; min-width:7% !important; max-width:7% !important">Unserviceable equipment (Not in Use / Repair)</th>
        <th style="width:7% !important; min-width:7% !important; max-width:7% !important">Additional computers / peripherals (New)</th>
        <th style="width:7% !important; min-width:8% !important; max-width:8% !important">Actual no. of computers / peripherals maintained</th>
        <th style="width:7% !important; min-width:6% !important; max-width:6% !important">Transferred computers IN</th>
        <th style="width:7% !important; min-width:6% !important; max-width:6% !important">Transferred computers OUT</th>
        <th style="width:10% !important; min-width:10% !important; max-width:10% !important">Date maintenance conducted</th>
        <th style="width:15% !important; min-width:17% !important; max-width:17% !important">Remarks</th>
        <th style="width:4% !important; min-width:2% !important; max-width:2% !important">Complied</th>
        <th style="width:4% !important; min-width:2% !important; max-width:2% !important">Not Complied</th>
    </tr></thead>
    <tbody>
    @forelse($rows as $row)
        <tr>
            <td class="office">{{ $row['office'] }}</td>
            <td class="num">{{ $row['target'] }}</td>
            <td class="num">{{ $row['condemned'] }}</td>
            <td class="num">{{ $row['unserviceable'] }}</td>
            <td class="num">{{ $row['additional'] }}</td>
            <td class="num">{{ $row['actual'] }}</td>
            <td class="num">{{ $row['transferred_in'] }}</td>
            <td class="num">{{ $row['transferred_out'] }}</td>
            <td class="date">{{ $row['dates'] ?: '' }}</td>
            <td class="remarks">{{ trim($row['remarks'] . (count($row['warnings']) ? ' ' . implode(' ', $row['warnings']) : '')) }}</td>
            <td class="status complied">{{ $row['status'] === 'Complied' ? '✓' : '' }}</td>
            <td class="status not-complied">{{ $row['status'] === 'Not Complied' ? 'X' : '' }}</td>
        </tr>
    @empty
        <tr><td colspan="12" style="padding:16px;text-align:center">No published PM Plan schedules matched the selected period and filters.</td></tr>
    @endforelse
    </tbody>
</table>

<table class="summary">
    <tr><td>No. of IT equipment / peripherals checked</td><td>{{ $summary['actual'] }}</td></tr>
    <tr><td>Total IT equipment / peripherals to be checked / maintained</td><td>{{ $summary['target'] }}</td></tr>
    <tr><td>Percentage of Accomplishment</td><td>{{ $summary['rate'] !== null && $summary['rate'] >= ($summary['target_percent'] / 100) ? '100%' : '' }}</td></tr>
</table>

<table class="signoff"><tr>
    <td>
        <div class="signoff-label">Prepared by:</div>
        <div style="height:12px"></div>
        <div class="signoff-name">{{ strtoupper($preparedBy?->name ?: 'SYSTEM') }}</div>
        <div>{{ $preparedBy?->position ?: ($preparedBy?->roleLabel() ?: '') }}</div>
    </td>
    <td></td>
    <td>
        <div class="signoff-label">Certified correct:</div>
        <div style="height:12px"></div>
        <div class="signoff-name">{{ strtoupper($unitHead?->name ?: '') }}</div>
        <div>{{ $unitHead?->position ?: ($unitHead?->roleLabel() ?: 'Unit Head') }}</div>
    </td>
</tr></table>

<?php
    $performanceLogoPath = public_path('images/catsu-logo-report.jpg');
    $performanceIsoPath = public_path('images/iso-9001-2015.jpg');
    $graph = $performanceGraph ?? [];
    $selectedSemester = (int) ($graph['selected_semester'] ?? ($filters['semester'] ?? 1));
    $graphRate = $graph['rate'] ?? null;
    $graphActualRate = $graphRate === null ? 0 : max(0, min(1, (float) $graphRate));
    $graphColumns = $graph['columns'] ?? [];
    $chartLeft = 64;
    $chartRight = 925;
    $chartTop = 15;
    $chartBottom = 215;
    $chartX = [255, 735];
    $chartY = static fn (float $value): float => $chartBottom - ($value * ($chartBottom - $chartTop));
    // Plot each semester from the same actual/baseline values used by the
    // performance table. If a semester has no baseline yet, keep it at 0% so
    // the graph still shows the complete 0–100% scale without inventing data.
    $graphActualValues = [];
    foreach ([1, 2] as $semester) {
        $actualData = $graphColumns[$semester]['actual_data'] ?? null;
        $baselineData = $graphColumns[$semester]['baseline'] ?? null;

        if (is_numeric($actualData) && is_numeric($baselineData) && (float) $baselineData > 0) {
            $graphActualValues[$semester] = max(0, min(1, (float) $actualData / (float) $baselineData));
        } elseif ($semester === $selectedSemester && $graphRate !== null) {
            $graphActualValues[$semester] = $graphActualRate;
        } else {
            $graphActualValues[$semester] = 0;
        }
    }
    $actualValues = [$graphActualValues[1], $graphActualValues[2]];
    $targetValues = [1, 1];
    $actualPoints = implode(' ', [
        $chartX[0] . ',' . $chartY($actualValues[0]),
        $chartX[1] . ',' . $chartY($actualValues[1]),
    ]);
    $targetPoints = implode(' ', [
        $chartX[0] . ',' . $chartY($targetValues[0]),
        $chartX[1] . ',' . $chartY($targetValues[1]),
    ]);
    $graphStatus = $graphRate === null ? '' : ($graphRate >= 1 ? 'MET' : 'UNMET');

    // Dompdf does not reliably render inline SVG. These coordinates are used
    // by the PDF-safe HTML/CSS chart below instead.
    $plotLeft = 50;
    // The PDF content area is approximately 1,200 CSS pixels wide. Keep the
    // grid and plotted points within that area so the chart does not leave a
    // large unused white region on the right.
    $plotRight = 1150;
    $plotTop = 10;
    $plotBottom = 220;
    $plotHeight = $plotBottom - $plotTop;
    // Keep the performance axis at 0–100%; the previous 110% headroom made
    // the chart look as if it had an extra data area above the target line.
    $plotY = static fn (float $value): float => $plotBottom - (max(0, min(1, $value)) * $plotHeight);
    $plotX = [350, 900];
    $graphSegment = static function (float $x1, float $y1, float $x2, float $y2): string {
        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        $length = sqrt(($dx * $dx) + ($dy * $dy));
        $angle = atan2($dy, $dx) * 180 / M_PI;

        return sprintf(
            'left:%.2fpx;top:%.2fpx;width:%.2fpx;transform:rotate(%.2fdeg)',
            $x1,
            $y1,
            $length,
            $angle
        );
    };
    $targetSegmentStyle = $graphSegment($plotX[0], $plotY($targetValues[0]), $plotX[1], $plotY($targetValues[1]));
    $actualSegmentStyle = $graphSegment($plotX[0], $plotY($actualValues[0]), $plotX[1], $plotY($actualValues[1]));
?>

<section class="performance-page">
    <table class="header"><tr>
        <td style="width:70px"><?php if (is_file($performanceLogoPath)): ?><img src="<?= e($performanceLogoPath) ?>" class="logo" alt="CatSU"><?php endif; ?></td>
        <td class="school">
            <div><em>Republic of the Philippines</em></div>
            <div class="school-name">CATANDUANES STATE UNIVERSITY</div>
            <div><em>Virac, Catanduanes</em></div>
        </td>
        <td style="width:130px;text-align:right"><?php if (is_file($performanceIsoPath)): ?><img src="<?= e($performanceIsoPath) ?>" class="iso" alt="ISO 9001:2015"><?php endif; ?></td>
    </tr></table>
    <div class="rule"></div>
    <div class="performance-heading">INFORMATION and COMMUNICATIONS TECHNOLOGY UNIT</div>
    <div class="performance-heading">Performance Monitoring</div>
    <div class="performance-subheading">For {{ $period['label'] }}, CY {{ $period['year'] }}</div>
    <div class="performance-objective">Quality Objective No. I: Ensure efficient maintenance of IT equipments and peripherals.</div>
    <div class="performance-target">Target: {{ $summary['target_percent'] }}%</div>

    <div class="performance-chart" role="img" aria-label="Actual versus target performance graph">
        <?php for ($tick = 0; $tick <= 100; $tick += 10):
            $tickY = $plotY($tick / 100);
        ?>
            <div class="graph-grid-line" style="left:<?= e($plotLeft) ?>px;top:<?= e($tickY) ?>px;width:<?= e($plotRight - $plotLeft) ?>px"></div>
            <div class="graph-y-label" style="left:<?= e($plotLeft - 47) ?>px;top:<?= e($tickY - 5) ?>px"><?= e($tick) ?>%</div>
        <?php endfor; ?>

        <div class="graph-segment graph-target" style="<?= e($targetSegmentStyle) ?>"></div>
        <div class="graph-segment graph-actual" style="<?= e($actualSegmentStyle) ?>"></div>

        <?php foreach ($targetValues as $index => $value): $pointY = $plotY($value); ?>
            <div class="graph-point graph-target" style="left:<?= e($plotX[$index]) ?>px;top:<?= e($pointY) ?>px"></div>
        <?php endforeach; ?>
        <?php foreach ($actualValues as $index => $value): $pointY = $plotY($value); ?>
            <div class="graph-point graph-actual" style="left:<?= e($plotX[$index]) ?>px;top:<?= e($pointY) ?>px"></div>
        <?php endforeach; ?>

        <div class="graph-x-label" style="left:<?= e($plotX[0] - 150) ?>px;width:300px">1st Semi-Annually (January-June)</div>
        <div class="graph-x-label" style="left:<?= e($plotX[1] - 150) ?>px;width:300px">2nd Semi-Annually (July-December)</div>
    </div>

    <table class="performance-data">
        <tr>
            <th></th>
            <th>{{ $graph['columns'][1]['label'] ?? '1st Semi-Annually (January-June)' }}</th>
            <th>{{ $graph['columns'][2]['label'] ?? '2nd Semi-Annually (July-December)' }}</th>
        </tr>
        <tr>
            <td>(Actual data)</td>
            <td>{{ $graph['columns'][1]['actual_data'] ?? '' }}</td>
            <td>{{ $graph['columns'][2]['actual_data'] ?? '' }}</td>
        </tr>
        <tr>
            <td>(Baseline)</td>
            <td>{{ $graph['columns'][1]['baseline'] ?? '' }}</td>
            <td>{{ $graph['columns'][2]['baseline'] ?? '' }}</td>
        </tr>
        <tr class="actual">
            <td>Actual</td>
            <td>{{ $selectedSemester === 1 && $graphRate !== null ? number_format($graphActualRate * 100, 0) . '%' : '' }}</td>
            <td>{{ $selectedSemester === 2 && $graphRate !== null ? number_format($graphActualRate * 100, 0) . '%' : '' }}</td>
        </tr>
        <tr class="target"><td>Target</td><td>100%</td><td>100%</td></tr>
        <tr class="status">
            <td>Status</td>
            <td class="{{ $selectedSemester === 1 && $graphStatus === 'MET' ? 'status-met' : ($selectedSemester === 1 && $graphStatus === 'UNMET' ? 'status-unmet' : '') }}">{{ $selectedSemester === 1 ? $graphStatus : '' }}</td>
            <td class="{{ $selectedSemester === 2 && $graphStatus === 'MET' ? 'status-met' : ($selectedSemester === 2 && $graphStatus === 'UNMET' ? 'status-unmet' : '') }}">{{ $selectedSemester === 2 ? $graphStatus : '' }}</td>
        </tr>
    </table>

    <table class="performance-signoff"><tr>
        <td><div class="label">Prepared by:</div><div style="height:12px"></div><div class="name">{{ strtoupper($preparedBy?->name ?: 'SYSTEM') }}</div><div>{{ $preparedBy?->position ?: ($preparedBy?->roleLabel() ?: '') }}</div></td>
        <td><div class="label">Certified Correct:</div><div style="height:12px"></div><div class="name">{{ strtoupper($unitHead?->name ?: '') }}</div><div>{{ $unitHead?->position ?: ($unitHead?->roleLabel() ?: 'Unit Head') }}</div></td>
    </tr></table>
</section>

<div class="footer"><span>CatSU-F-PMC-01C</span><span>Rev. 2</span><span>Effectivity Date: March 10, 2025</span></div>
</body>
</html>
