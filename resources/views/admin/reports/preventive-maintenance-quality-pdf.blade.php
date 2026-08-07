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
        .report { width: 100%; border-collapse: collapse; table-layout: fixed; page-break-inside: auto; }
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
        .summary { width: 58%; margin-top: 7px; border-collapse: collapse; }
        .summary td { border: 0.7px solid #000; padding: 4px; font-size: 10px; }
        .summary td:first-child { width: 76%; font-weight: bold; }
        .summary td:last-child { text-align: center; font-weight: bold; }
        .signoff { width: 100%; margin-top: 22px; border-collapse: collapse; }
        .signoff td { width: 33.33%; border: 0; padding: 0 28px; vertical-align: top; font-family: Arial, sans-serif; font-size: 10px; }
        .signoff-label { margin-bottom: 16px; }
        .signoff-name { font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .footer { position: fixed; right: 0; bottom: 0; left: 0; border-top: 1.5px solid #93b8df; padding-top: 3px; color: #555; font-size: 8px; font-style: italic; }
        .footer span { display: inline-block; width: 33%; }
        .footer span:nth-child(2) { text-align: center; }
        .footer span:nth-child(3) { text-align: right; }
    </style>
</head>
<body>
@php($logoPath = public_path('images/catsu-logo-report.jpg'))
@php($isoPath = public_path('images/iso-9001-2015.jpg'))
<table class="header"><tr>
    <td style="width:70px">@if(file_exists($logoPath))<img src="{{ $logoPath }}" class="logo" alt="CatSU">@endif</td>
    <td class="school">
        <div><em>Republic of the Philippines</em></div>
        <div class="school-name">CATANDUANES STATE UNIVERSITY</div>
        <div><em>Virac, Catanduanes</em></div>
    </td>
    <td style="width:130px;text-align:right">@if(file_exists($isoPath))<img src="{{ $isoPath }}" class="iso" alt="ISO 9001:2015">@endif</td>
</tr></table>
<div class="rule"></div>
<div class="unit">Information and Communications Technology (ICT) Unit</div>
<div class="title">QUALITY OBJECTIVE MONITORING SUPPORTING SCHEDULE</div>
<div class="subtitle">For {{ $period['label'] }}, CY {{ $period['year'] }}</div>
<div class="objective">Quality Objective No. I: Ensure efficient maintenance of IT equipment and peripherals</div>
<div class="target">Target: {{ $summary['target_percent'] }}%</div>

<table class="report">
    <colgroup>
        <col style="width:18%"><col style="width:8%"><col style="width:7%"><col style="width:8%">
        <col style="width:8%"><col style="width:8%"><col style="width:6%"><col style="width:6%">
        <col style="width:10%"><col style="width:13%"><col style="width:4%"><col style="width:4%">
    </colgroup>
    <thead><tr>
        <th>Office / Unit</th>
        <th>Target no. of computers / peripherals for check-up / maintenance</th>
        <th>Condemned ICT equipment returned to Supply</th>
        <th>Unserviceable equipment (Not in Use / Repair)</th>
        <th>Additional computers / peripherals (New)</th>
        <th>Actual no. of computers / peripherals maintained</th>
        <th>Transferred computers IN</th>
        <th>Transferred computers OUT</th>
        <th>Date maintenance conducted</th>
        <th>Remarks</th>
        <th>Complied</th>
        <th>Not Complied</th>
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

<div class="footer"><span>CatSU-F-PMC-01C</span><span>Rev. 2</span><span>Effectivity Date: March 10, 2025</span></div>
</body>
</html>
