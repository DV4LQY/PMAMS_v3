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
            font-size: 8.4px;
            color: #111827;
        }

        /*
         * Each manually generated sheet fills exactly one printable content
         * area (8.5in minus the configured top and bottom page margins).
         * Keeping the header/footer inside the sheet lets every office use
         * its own historical checklist data instead of repeating page one's
         * office across the complete PDF.
         */
        .pdf-page {
            position: relative;
            height: 610px;
        }

        .pdf-page.force-page-break {
            page-break-after: always;
        }

        .page-header {
            position: absolute;
            top: -82px;
            left: 0;
            right: 0;
            height: 82px;
        }

        .page-footer {
            position: absolute;
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
            font-size: 12px;
            line-height: 1.15;
            padding-top: 5px !important;
            margin-left: 0;
        }

        .school-name {
            font-size: 10.3px;
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
            font-size: 12px;
            font-family: Arial, Helvetica, sans-serif;
            white-space: nowrap;
        }

        .header-date {
            position: absolute;
            top: 101px;
            right: 0;
            width: 190px;
            font-size: 11px;
            text-align: left;
        }

        .line {
            display: inline-block;
            border-bottom: 1px solid #111827;
            height: 11px;
            vertical-align: bottom;
            line-height: 1;
        }

        .date-line {
            width: 108px;
            height: 14px;
            font-size: 11px;
            line-height: 12px;
            text-align: center;
            padding: 1px 3px 0;
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
            width: 330px;
            height: 15px;
            font-size: 12px;
            white-space: nowrap;
        }

        .office-label {
            position: absolute;
            top: 0;
            left: 0;
            line-height: 13px;
        }

        .office-line {
            position: absolute;
            top: -1px;
            left: 72px;
            width: 260px;
            text-align: center;
            height: 14px;
            white-space: nowrap;
        }

        .office-line .line-value {
            display: block;
            position: relative;
            top: -1px;
            line-height: 12px;
            white-space: nowrap;
            overflow: visible;
        }

        .form-title {
            position: absolute;
            top: 81px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 0.2px;
            text-transform: uppercase;
        }

        /* TABLE AREA ONLY - header and footer are intentionally unchanged. */
        .content {
            width: 96%;
            /* Keep the first table header below the Office/Unit underline. */
            margin-top: 48px;
            padding: 0;
            margin-left: auto;
            margin-right: auto;
        }

        .remarks-cell,
        .action-cell {
            font-size: 10px;
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
            font-size: 7.9px;
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
            font-size: 10px;
        }

        .main-table th {
            font-weight: bold;
        }

        .main-table .left {
            text-align: left;
            padding-left: 2px;
            font-size: 10px;
            line-height: 1.05;
        }

        .main-table .computer-peripheral {
            /* Equipment labels in this column should follow the report's
             * left-aligned data convention (the table-wide rule defaults to
             * centered cells). Keep a small inset so the text does not touch
             * the border in the PDF renderer. */
            text-align: left !important;
            padding-left: 8px !important;
            font-size: 12px;
            line-height: 1.05;
        }

        .label-row th {
            font-size: 10px;
            font-weight: normal;
            line-height: 1.05;
        }

        .status-head {
            font-size: 10px;
            line-height: 1.05;
            font-weight: normal;
        }

        .row-height td {
            height: 42px;
        }

        .check {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 16px;
            font-weight: bold;
            line-height: 1;
        }

        .remarks-cell,
        .action-cell {
            font-size: 7.2px;
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
            font-size: 9.5px;
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
            height: 20px;
            font-size: 12px;
            line-height: 12px;
            padding: 1px 2px 0;
            border-bottom: 1px solid #111827;
            text-align: center;
            vertical-align: bottom;
            white-space: nowrap;
        }

        .sig-date-line {
            display: inline-block;
            width: 210px;
            height: 15px;
            font-size: 12px;
            line-height: 12px;
            padding: 1px 2px 0;
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
            font-size: 10px;
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

    // Fixed header unit based on the official printed form.
    $fixedUnitName = 'Information and Communications Technology Unit';

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

    /*
     * Resolve the office from the saved checklist snapshot first. This is
     * deliberately historical: if equipment is later transferred, its old
     * checklist stays under the office where the maintenance was performed.
     */
    $getRecordOfficeContext = function ($record) use ($getChecklistData) {
        $data = $getChecklistData($record);
        $snapshot = data_get($data, 'snapshot', []);
        $snapshot = is_array($snapshot) ? $snapshot : [];

        $assignment = $record->device?->currentAssignment;
        $staff = $record->staff ?? $assignment?->staff;
        $office = $record->office ?? $assignment?->office ?? $staff?->office;
        $location = $record->location ?? $assignment?->location ?? $office?->location;

        $officeId = data_get($snapshot, 'office_id') ?? $record->office_id ?? $office?->id;
        $locationId = data_get($snapshot, 'location_id') ?? $record->location_id ?? $location?->id;
        $officeName = trim((string) (data_get($snapshot, 'office') ?? $office?->name ?? ''));
        $locationCode = trim((string) (data_get($snapshot, 'location_code') ?? $location?->code ?? ''));
        $locationName = trim((string) (data_get($snapshot, 'location') ?? $location?->name ?? ''));

        // The specific office is the clearest Office/Unit value. Older
        // records without an office fall back to their saved location code.
        $display = $officeName !== ''
            ? $officeName
            : ($locationCode !== '' ? $locationCode : ($locationName !== '' ? $locationName : 'Unassigned'));

        $key = $officeId
            ? 'office:' . $officeId
            : ($locationId
                ? 'location:' . $locationId . ':' . mb_strtolower($display)
                : 'snapshot:' . mb_strtolower($display));

        return [
            'key' => $key,
            'display' => $display,
            'sort' => mb_strtolower(($locationCode !== '' ? $locationCode . ' ' : '') . $display),
        ];
    };

    $displayComputerPeripheral = function ($record) use ($getChecklistData) {
        $device = $record->device;

        if (! $device) {
            return '-';
        }

        $data = $getChecklistData($record);
        $computerName = data_get($data, 'snapshot.computer_name')
            ?: $device->computer_name
            ?: data_get($device->specs, 'computer_name')
            ?: data_get($data, 'snapshot.property_number')
            ?: $device->property_number;

        return trim($computerName);
    };

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

    /*
     * Sort and paginate within an office group. A page is flushed whenever
     * the office changes, even when space remains, so records from the next
     * office can never appear beneath the previous office heading.
     */
    $records = $records
        ->sort(function ($left, $right) use ($getRecordOfficeContext) {
            $leftOffice = $getRecordOfficeContext($left);
            $rightOffice = $getRecordOfficeContext($right);

            return [$leftOffice['sort'], (string) $left->maintenance_date, (int) $left->id]
                <=> [$rightOffice['sort'], (string) $right->maintenance_date, (int) $right->id];
        })
        ->values();

    $recordPages = collect();

    foreach ($records->groupBy(fn ($pageRecord) => $getRecordOfficeContext($pageRecord)['key']) as $officeRecords) {
        $currentRows = collect();
        $currentUnits = 0;

        foreach ($officeRecords as $pageRecord) {
            $units = $estimateRowUnits($pageRecord);

            if ($currentRows->isNotEmpty() && (($currentUnits + $units) > $rowUnitsPerPage)) {
                $firstPageRecord = $currentRows->first()['record'];
                $recordPages->push([
                    'rows' => $currentRows,
                    'used_units' => $currentUnits,
                    'office' => $getRecordOfficeContext($firstPageRecord),
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
            $firstPageRecord = $currentRows->first()['record'];
            $recordPages->push([
                'rows' => $currentRows,
                'used_units' => $currentUnits,
                'office' => $getRecordOfficeContext($firstPageRecord),
            ]);
        }
    }

    if ($recordPages->isEmpty()) {
        $recordPages->push([
            'rows' => collect(),
            'used_units' => 0,
            'office' => ['key' => 'empty', 'display' => '', 'sort' => ''],
        ]);
    }
@endphp

@foreach($recordPages as $pageIndex => $page)
@php
    $pageRows = $page['rows'];
    $usedUnits = $page['used_units'];
    $pageFirstRecord = data_get($pageRows->first(), 'record');
    $pageOfficeUnit = data_get($page, 'office.display', '');
    $pageDateSource = $pageFirstRecord?->maintenance_date ?? now();
    $pageDateText = \Carbon\Carbon::parse($pageDateSource)->format('m/d/Y');
    $pageCheckedByText = $pageFirstRecord?->checkedBy?->name
        ?? auth()->user()?->name
        ?? '';
@endphp

<div class="pdf-page {{ ! $loop->last ? 'force-page-break' : '' }}">
<div class="page-header">
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @if(file_exists($logoPath) && (extension_loaded('gd') || extension_loaded('imagick')))
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
        <span class="line date-line">{{ $pageDateText }}</span>
    </div>

    <div class="blue-rule-top"></div>

    <div class="office-line-wrap">
        <span class="office-label">Office/Unit:</span>
        <span class="line office-line"><span class="line-value">{{ $pageOfficeUnit }}</span></span>
    </div>

    <div class="form-title">PREVENTIVE MAINTENANCE CHECKLIST</div>
</div>

<div class="page-footer">
    <table class="footer-signatures">
        <tr>
            <td style="width: 50%">
                <span class="sig-label">Checked by:</span>
                <span class="sig-name-line">{{ $pageCheckedByText }}</span>
                <br> <br>
                <span class="sig-label">Date:</span>
                <span class="sig-date-line">{{ $pageDateText }}</span>
            </td>

            <td style="width: 50%; text-align: right">
                <div class="approved-signature-block">
                    <span class="sig-label">Approved by:</span>
                    <span class="sig-name-line">
                        {{ $unitHead?->name ?? '' }}
                    </span>
                    <br><br>
                    <span class="sig-label">Date:</span>
                    <span class="sig-date-line"></span>
                </div>
            </td>
        </tr>
    </table>

    <div class="document-footer">
        <span class="code">CatSU-F-ICTU-05</span>
        <span class="rev">Rev: 1</span>
        <span class="effectivity">Effectivity Date: September 12, 2024</span>
    </div>
</div>

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
                    <td class="computer-peripheral" style="{{ $rowHeightStyle }}">{{ $displayComputerPeripheral($record) }}</td>

                    @foreach($hardwareItems as $key => $item)
                        @php
                            $value = $hardware[$key] ?? '';
                            $hardwareCellStyle = $rowHeightStyle;
                            if ($value === 'Not Available') {
                                $hardwareCellStyle .= ' background-color: #9ca3af; color: #9ca3af;';
                            }
                        @endphp
                        <td class="check" style="{{ $hardwareCellStyle }} width: 3.5%;">{{ $value === 'OK' ? '✓' : '' }}</td>
                        <td class="check" style="{{ $hardwareCellStyle }} width: 3.5%;">{{ $value === 'Not OK' ? '✕' : '' }}</td>
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
</div>
@endforeach
</body>
</html>
