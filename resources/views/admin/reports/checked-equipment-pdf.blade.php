<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Preventive Maintenance Checklist</title>

    <style>
        /* 8.5 x 13 inches / long coupon bond, landscape */
        @page {
            size: 13in 8.5in;
            margin: 124px 24px 82px 24px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 7.4px;
            color: #111827;
        }

        .page-header {
            position: fixed;
            top: -82px;
            left: 0;
            right: 0;
            height: 82px;
        }

        .page-footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -74px;
            height: 72px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
        }

        .header-table td {
            border: 0;
            padding: 0;
            vertical-align: top;
        }

        .logo-cell {
            width: 110px;
            position: relative;
        }

        .logo {
            position: absolute;
            top: 5px;
            left: 50px;
            width: 50px;
            height: 50px;
        }

        .school-text div:first-child {
            font-style: italic;
        }

        .school-text {
            font-size: 11px;
            line-height: 1.15;
            padding-top: 5px !important;
            margin-left: 0;
        }

        .school-name {
            font-size: 9.3px;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.05;
            letter-spacing: 0.15px;
        }

        .header-spacer {
            width: auto;
        }

        .unit-title {
            position: absolute;
            top: 70px;
            left: 52px;
            font-size: 11px;
            font-family: Arial, Helvetica, sans-serif;
            white-space: nowrap;
        }

        .header-date {
            position: absolute;
            top: 101px;
            right: 0;
            width: 190px;
            font-size: 8.1px;
            text-align: left;
        }

        .line {
            display: inline-block;
            border-bottom: 1px solid #111827;
            height: 11px;
            vertical-align: bottom;
        }

        .date-line {
            width: 108px;
            text-align: center;
            padding-left: 3px;
            padding-right: 3px;
        }

        .blue-rule-top {
            position: absolute;
            left: 50px;
            right: 50px;
            top:  60px;
            border-top: 2px solid #1d70b8;
        }

        .office-line-wrap {
            position: absolute;
            top: 101px;
            left: 52px;
            width: 380px;
            font-size: 11px;
        }

        .office-line {
            width: 115px;
            text-align: center;
        }

        .form-title {
            position: absolute;
            top: 81px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.2px;
            text-transform: uppercase;
        }

        /* TABLE AREA ONLY - header and footer are intentionally unchanged. */
        .content {
            width: 96%;
            margin-top: 45px;
            padding: 0;
            margin-left: auto;
            margin-right: auto;
        }

        .page-break {
            page-break-after: always;
        }

        .remarks-cell,
        .action-cell {
            font-size: 9px;
            line-height: 1.05;
            text-align: left;
            padding-left: 2px;
            padding-right: 2px;
            overflow: hidden;
        }


        .main-table {
            width: 100%;
            max-width: none;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: auto;
            margin: 0;
            font-size: 6.9px;
        }

        thead {
            display: table-header-group;
        }

        tbody {
            display: table-row-group;
        }

        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        .main-table th,
        .main-table td {
            border: 1px solid #111827;
            padding: 1px 1px;
            text-align: center;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
            overflow: hidden;
            line-height: 1.05;
            font-size: 9px;
        }

        .main-table th {
            font-weight: bold;
        }

        .main-table .left {
            text-align: left;
            padding-left: 2px;
            font-size: 9px;
            line-height: 1.05;
        }

        .label-row th {
            font-size: 9px;
            font-weight: normal;
            line-height: 1.05;
        }

        .status-head {
            font-size: 9px;
            line-height: 1.05;
            font-weight: normal;
        }

        .row-height td {
            height: 42px;
        }

        .check {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9px;
            font-weight: bold;
            line-height: 1.05;
        }

        .remarks-cell,
        .action-cell {
            font-size: 6.2px;
            line-height: 1.04;
            text-align: left;
            padding-left: 2px;
            padding-right: 2px;
            overflow: hidden;
        }

        .footer-signatures {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 8px;
            position: relative;
            top: -24px;
        }

        .footer-signatures td {
            border: 0;
            padding: 0 18px;
            vertical-align: top;
            font-size: 8.5px;
        }

        .sig-label {
            display: inline-block;
            width: 62px;
            font-weight: bold;
            white-space: nowrap;
        }

        .sig-name-line {
            display: inline-block;
            width: 210px;
            height: 13px;
            border-bottom: 1px solid #111827;
            text-align: center;
            vertical-align: bottom;
        }

        .sig-date-line {
            display: inline-block;
            width: 210px;
            height: 13px;
            border-bottom: 1px solid #111827;
            text-align: center;
            vertical-align: bottom;
        }

        .approved-signature-block {
            display: inline-block;
            width: 300px;
            text-align: left;
        }

        .document-footer {
            position: absolute;
            left: 50px;
            right: 50px;
            bottom: 0;
            height: 18px;
            border-top: 2px solid #1d70b8;
            font-size: 9px;
        }

        .document-footer .code {
            position: absolute;
            left: 0;
            top: 4px;
        }

        .document-footer .rev {
            position: absolute;
            left: 50%;
            top: 4px;
            transform: translateX(-50%);
        }

        .document-footer .effectivity {
            position: absolute;
            right: 0;
            top: 4px;
        }
    </style>
</head>

<body>
@php
    if (! isset($records)) {
        $records = isset($record) && $record
            ? collect([$record])
            : collect();
    } elseif (is_array($records)) {
        $records = collect($records);
    }

    $firstRecord = $records->first() ?? null;
    $firstDevice = $firstRecord?->device;
    $firstAssignment = $firstDevice?->currentAssignment;
    $firstStaff = $firstAssignment?->staff;
    $firstOffice = $firstStaff?->office;
    $firstCollege = $firstOffice?->college;

    // Fixed header unit based on the official printed form.
    $fixedUnitName = 'Information and Communications Technology Unit';

    $officeUnitCode = $firstCollege?->code
        ?? $firstOffice?->name
        ?? '';

    $dateSource = $firstRecord?->maintenance_date ?? now();
    $dateText = \Carbon\Carbon::parse($dateSource)->format('m/d/Y');

    $logoPath = public_path('images/catsu-logo.png');

    $hardwareItems = $checklistItems ?? [
        'system_unit_power_on' => ['group' => 'System Unit', 'label' => 'Check for<br>power on'],
        'monitor_display' => ['group' => 'Monitor', 'label' => 'Check<br>display'],
        'keyboard_keys' => ['group' => 'Keyboard', 'label' => 'Check for<br>keys'],
        'mouse_buttons' => ['group' => 'Mouse', 'label' => 'Check<br>mouse<br>left/right<br>buttons'],
        'avr_ups_power_recovery' => ['group' => 'AVR/UPS', 'label' => 'Check for<br>power<br>recovery'],
        'printer_printout' => ['group' => 'Printer', 'label' => 'Check<br>printout'],
    ];

    $softwareItems = $softwareItems ?? [
        'setup_antivirus' => 'Setup Anti-Virus',
        'system_scan_removal' => 'System Scan and Removal of Malicious Software',
    ];

    $getChecklistData = function ($record) {
        $data = $record->checklist_data ?? [];

        if (is_string($data)) {
            $decoded = json_decode($data, true);
            $data = is_array($decoded) ? $decoded : [];
        }

        return is_array($data) ? $data : [];
    };

    $displayComputerPeripheral = function ($record) {
        $device = $record->device;

        if (! $device) {
            return '-';
        }

        $computerName = $device->computer_name
            ?: data_get($device->specs, 'computer_name')
            ?: $device->property_number;

        $status = strtoupper($device->condition ?? '');

        return trim($computerName . ($status ? ' (' . $status . ')' : ''));
    };

    $checkedByText = $firstRecord?->checkedBy?->name
        ?? auth()->user()->name
        ?? '';

    /*
     * Dynamic page split for DOMPDF.
     *
     * Goal:
     * - The table always has 8 row-units of height per coupon/page.
     * - Short records use 1 row-unit.
     * - Long Remarks / Corrective Action records use 2+ row-units.
     * - If a long record will not fit, it automatically moves to the next coupon/page.
     * - Blank rows are added only until the page reaches the same table length.
     */
    $rowUnitsPerPage = 8;
    $rowUnitHeight = 42;

    $plainCellText = function ($value) {
        return trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)));
    };

    $estimateRowUnits = function ($record) use (
        $getChecklistData,
        $displayComputerPeripheral,
        $plainCellText,
        $rowUnitsPerPage
    ) {
        $data = $getChecklistData($record);
        $correctiveAction = $record->corrective_action ?? data_get($data, 'corrective_action', '');

        $computerText = $plainCellText($displayComputerPeripheral($record));
        $remarksText = $plainCellText($record->remarks ?? '');
        $actionText = $plainCellText($correctiveAction);

        /*
         * These numbers are practical estimates for this PDF layout.
         * Lower number = row becomes taller sooner.
         */
        $computerUnits = (int) ceil(max(1, \Illuminate\Support\Str::length($computerText)) / 36);
        $remarksUnits = (int) ceil(max(1, \Illuminate\Support\Str::length($remarksText)) / 50);
        $actionUnits = (int) ceil(max(1, \Illuminate\Support\Str::length($actionText)) / 50);

        $units = max(1, $computerUnits, $remarksUnits, $actionUnits);

        return min($rowUnitsPerPage, $units);
    };

    $recordPages = collect();
    $currentRows = collect();
    $currentUnits = 0;

    foreach ($records as $pageRecord) {
        $units = $estimateRowUnits($pageRecord);

        if ($currentRows->isNotEmpty() && (($currentUnits + $units) > $rowUnitsPerPage)) {
            $recordPages->push([
                'rows' => $currentRows,
                'used_units' => $currentUnits,
            ]);

            $currentRows = collect();
            $currentUnits = 0;
        }

        $currentRows->push([
            'record' => $pageRecord,
            'units' => $units,
        ]);

        $currentUnits += $units;
    }

    if ($currentRows->isNotEmpty()) {
        $recordPages->push([
            'rows' => $currentRows,
            'used_units' => $currentUnits,
        ]);
    }

    if ($recordPages->isEmpty()) {
        $recordPages->push([
            'rows' => collect(),
            'used_units' => 0,
        ]);
    }
@endphp

<div class="page-header">
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @if(file_exists($logoPath))
                    <img src="{{ $logoPath }}" class="logo">
                @endif
            </td>

            <td class="school-text">
                <div>Republic of the Philippines</div>
                <div class="school-name">CATANDUANES STATE UNIVERSITY</div>
                <div>Virac, Catanduanes</div>
            </td>

            <td class="header-spacer"></td>
        </tr>
    </table>

    <div class="unit-title">{{ $fixedUnitName }}</div>

    <div class="header-date">
        Date:
        <span class="line date-line">{{ $dateText }}</span>
    </div>

    <div class="blue-rule-top"></div>

    <div class="office-line-wrap">
        Office/Unit:
        <span class="line office-line">{{ $officeUnitCode }}</span>
    </div>

    <div class="form-title">PREVENTIVE MAINTENANCE CHECKLIST</div>
</div>

<div class="page-footer">
    <table class="footer-signatures">
        <tr>
            <td style="width: 50%;">
                <span class="sig-label">Checked by:</span>
                <span class="sig-name-line">{{ $checkedByText }}</span>
                <br>
                <span class="sig-label">Date:</span>
                <span class="sig-date-line">{{ $dateText }}</span>
            </td>

            <td style="width: 50%; text-align: right;">
                <div class="approved-signature-block">
                    <span class="sig-label">Approved by:</span>
                    <span class="sig-name-line">
                        {{ $unitHead?->name ?? '' }}
                    </span>
                    <br>
                    <span class="sig-label">Date:</span>
                    <span class="sig-date-line"></span>
                </div>
            </td>
        </tr>
    </table>

    <div class="document-footer">
        <span class="code">CatSU-F-ICTU-05</span>
        <span class="rev">Rev: 0</span>
        <span class="effectivity">Effectivity Date: July 22, 2024</span>
    </div>
</div>

@foreach($recordPages as $pageIndex => $page)
@php
    $pageRows = $page['rows'];
    $usedUnits = $page['used_units'];
@endphp

<div class="content">
    <table class="main-table">
        <colgroup>
            {{-- Computers and Peripherals --}}
            <col style="width: 16%;">

            {{-- System Unit: OK / Not OK --}}
            <col style="width: 3.5%;">
            <col style="width: 3.5%;">

            {{-- Monitor: OK / Not OK --}}
            <col style="width: 3.5%;">
            <col style="width: 3.5%;">

            {{-- Keyboard: OK / Not OK --}}
            <col style="width: 3.5%;">
            <col style="width: 3.5%;">

            {{-- Mouse: OK / Not OK --}}
            <col style="width: 3.5%;">
            <col style="width: 3.5%;">

            {{-- AVR/UPS: OK / Not OK --}}
            <col style="width: 3.5%;">
            <col style="width: 3.5%;">

            {{-- Printer: OK / Not OK --}}
            <col style="width: 3.5%;">
            <col style="width: 3.5%;">

            {{-- Software --}}
            <col style="width: 6%;">
            <col style="width: 8%;">

            {{-- Remarks --}}
            <col style="width: 13%;">

            {{-- Corrective Action --}}
            <col style="width: 15%;">
        </colgroup>

        <thead>
            <tr>
                <th rowspan="3" style="width: 16%;">Computers and Peripherals</th>

                <th colspan="2" style="width: 7%;">System<br>Unit</th>
                <th colspan="2" style="width: 7%;">Monitor</th>
                <th colspan="2" style="width: 7%;">Keyboard</th>
                <th colspan="2" style="width: 7%;">Mouse</th>
                <th colspan="2" style="width: 7%;">AVR/UPS</th>
                <th colspan="2" style="width: 7%;">Printer</th>
                <th colspan="2" style="width: 14%;">Software</th>
                <th rowspan="3" style="width: 13%;">Remarks</th>
                <th rowspan="3" style="width: 15%;">Corrective<br>Action</th>
            </tr>

            <tr class="label-row">
                @foreach($hardwareItems as $item)
                    <th colspan="2">{!! $item['label'] ?? '-' !!}</th>
                @endforeach

                <th rowspan="2">Setup Anti-<br>Virus</th>
                <th rowspan="2">System Scan<br>and Removal<br>of Malicious<br>Software</th>
            </tr>

            <tr class="status-head">
                @foreach($hardwareItems as $item)
                    <th>OK</th>
                    <th>Not<br>OK</th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            @forelse($pageRows as $pageRow)
                @php
                    $record = $pageRow['record'];
                    $rowUnits = $pageRow['units'];
                    $rowHeight = $rowUnits * $rowUnitHeight;
                    $rowHeightStyle = 'height: ' . $rowHeight . 'px;';

                    $data = $getChecklistData($record);
                    $hardware = $data['hardware'] ?? $data['hardwareResponses'] ?? [];
                    $software = $data['software'] ?? $data['softwareResponses'] ?? [];
                    $correctiveAction = $record->corrective_action ?? data_get($data, 'corrective_action', '');
                @endphp

                <tr class="row-height">
                    <td class="left" style="{{ $rowHeightStyle }}">{{ $displayComputerPeripheral($record) }}</td>

                    @foreach($hardwareItems as $key => $item)
                        @php $value = $hardware[$key] ?? ''; @endphp
                        <td class="check" style="{{ $rowHeightStyle }} width: 3.5%;">{{ $value === 'OK' ? '✓' : '' }}</td>
                        <td class="check" style="{{ $rowHeightStyle }} width: 3.5%;">{{ $value === 'Not OK' ? '✓' : '' }}</td>
                    @endforeach

                    <td class="check" style="{{ $rowHeightStyle }} width: 6%;">{{ ($software['setup_antivirus'] ?? '') === 'check' ? '✓' : '' }}</td>
                    <td class="check" style="{{ $rowHeightStyle }} width: 8%;">{{ ($software['system_scan_removal'] ?? '') === 'check' ? '✓' : (($software['system_scan_removal'] ?? '') === 'dash' ? '-' : '') }}</td>

                    <td class="remarks-cell" style="{{ $rowHeightStyle }} width: 13%;">{{ $plainCellText($record->remarks ?? '') }}</td>
                    <td class="action-cell" style="{{ $rowHeightStyle }} width: 15%;">{{ $plainCellText($correctiveAction) }}</td>
                </tr>
            @empty
                {{-- No records: show blank rows below. --}}
            @endforelse

            @for($i = $usedUnits; $i < $rowUnitsPerPage; $i++)
                @php
                    $blankRowHeightStyle = 'height: ' . $rowUnitHeight . 'px;';
                @endphp
                <tr class="row-height">
                    <td style="{{ $blankRowHeightStyle }}">&nbsp;</td>
                    @foreach($hardwareItems as $item)
                        <td style="{{ $blankRowHeightStyle }}"></td>
                        <td style="{{ $blankRowHeightStyle }}"></td>
                    @endforeach
                    <td style="{{ $blankRowHeightStyle }}"></td>
                    <td style="{{ $blankRowHeightStyle }}"></td>
                    <td style="{{ $blankRowHeightStyle }}"></td>
                    <td style="{{ $blankRowHeightStyle }}"></td>
                </tr>
            @endfor
        </tbody>
    </table>
</div>

@if(! $loop->last)
    <div class="page-break"></div>
@endif
@endforeach
</body>
</html>
