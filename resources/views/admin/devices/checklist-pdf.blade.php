<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Preventive Maintenance Checklist</title>

    <style>
        @page {
            size: 13in 8.5in;
            margin: 14mm 9mm 14mm 9mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8px;
            color: #111827;
            margin: 0;
            padding: 0;
            background: #ffffff !important;
        }

        /*
        |--------------------------------------------------------------------------
        | Fixed header and footer
        |--------------------------------------------------------------------------
        | These stay visible on every PDF page when the checklist table exceeds
        | one coupon bond page.
        */
        .pdf-header {
            position: fixed;
            top: -7mm;
            left: 0;
            right: 0;
            height: 35mm;
            background: #ffffff;
        }

        .pdf-footer {
            position: fixed;
            bottom: -10mm;
            left: 0;
            right: 0;
            height: 10mm;
            background: #ffffff;
        }

        .content {
            margin-top: 34mm;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            border: none;
            padding: 0;
            vertical-align: top;
        }

        .logo-cell {
            width: 55px;
            padding-right: 2px;
        }

        .logo {
            width: 42px;
            height: 42px;
            margin-left: 0;
            margin-top: 1px;
            object-fit: contain;
        }

        .school-cell {
            padding-left: 0;
            padding-top: 0;
            line-height: 1.08;
            text-align: left;
        }

        .school-top {
            font-style: italic;
            font-size: 7px;
            line-height: 1.05;
        }

        .school-name {
            font-weight: bold;
            font-size: 9px;
            line-height: 1.05;
            letter-spacing: .15px;
        }

        .school-address {
            font-size: 7px;
            line-height: 1.05;
        }

        .header-spacer {
            width: 190px;
        }

        .blue-line {
            border-top: 2px solid #1c75bc;
            height: 0;
            margin-top: 2px;
            margin-bottom: 6px;
        }

        .sub-header-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }

        .sub-header-table td {
            border: none;
            padding: 0;
            vertical-align: bottom;
            font-size: 8px;
        }

        .unit-title {
            width: 32%;
            text-align: left;
            padding-left: 52px !important;
            font-size: 8px;
        }

        .checklist-title {
            width: 36%;
            text-align: center;
            font-weight: bold;
            font-size: 9px;
            letter-spacing: .2px;
            white-space: nowrap;
        }

        .date-title {
            width: 32%;
            text-align: right;
            font-size: 8px;
            padding-right: 2px !important;
        }

        .line {
            display: inline-block;
            border-bottom: 1px solid #111827;
            min-height: 9px;
            vertical-align: bottom;
        }

        .date-line {
            width: 100px;
            text-align: center;
        }

        .office-line {
            width: 160px;
            text-align: center;
        }

        .office-unit-row {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            margin-bottom: 2px;
        }

        .office-unit-row td {
            border: none;
            padding: 0;
            font-size: 8px;
        }

        .main-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .main-table thead {
            display: table-header-group;
        }

        .main-table tfoot {
            display: table-footer-group;
        }

        .main-table th,
        .main-table td {
            border: 1px solid #111827;
            padding: 2px 2px;
            text-align: center;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .main-table th {
            font-weight: bold;
            background: #ffffff;
            font-size: 6.7px;
            line-height: 1.05;
        }

        .main-table td {
            font-size: 7px;
            line-height: 1.05;
        }

        .device-col {
            width: 7.2%;
        }

        .pair-ok {
            width: 3.7%;
        }

        .software-col {
            width: 4.8%;
        }

        .remarks-col {
            width: 9.7%;
        }

        .corrective-col {
            width: 9.7%;
        }

        .vertical-head {
            font-weight: bold;
        }

        .left {
            text-align: left !important;
        }

        .check {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8px;
            font-weight: bold;
        }

        .table-row td {
            height: 20px;
        }

        .blank-row td {
            height: 20px;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            page-break-inside: avoid;
        }

        .signature-table td {
            border: none;
            padding: 0;
            width: 50%;
            font-size: 9px;
            vertical-align: top;
        }

        .sig-label {
            font-weight: bold;
        }

        .sig-line {
            display: inline-block;
            border-bottom: 1px solid #111827;
            width: 170px;
            height: 12px;
            text-align: center;
            vertical-align: bottom;
        }

        .sig-date-line {
            display: inline-block;
            border-bottom: 1px solid #111827;
            width: 85px;
            height: 12px;
            text-align: center;
            vertical-align: bottom;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 2px solid #1c75bc;
        }

        .footer-table td {
            border: none;
            padding-top: 3px;
            font-size: 7px;
            color: #1f2937;
        }

        .footer-left {
            text-align: left;
            width: 33.333%;
        }

        .footer-center {
            text-align: center;
            width: 33.333%;
        }

        .footer-right {
            text-align: right;
            width: 33.333%;
        }
    </style>
</head>

<body>
@php
    /*
    |--------------------------------------------------------------------------
    | Data preparation
    |--------------------------------------------------------------------------
    | This template supports the current single-device checklist route and also
    | accepts a records collection if the controller passes one in the future.
    */
    $records = isset($records)
        ? (is_array($records) ? collect($records) : $records)
        : collect();

    $hasRecords = $records instanceof \Illuminate\Support\Collection && $records->isNotEmpty();

    $assignment = $device?->currentAssignment ?? null;
    $staff = $assignment?->staff;
    $office = $staff?->office;
    $college = $office?->college;

    $headerUnit = 'Information and Communications Technology Unit';

    $officeUnitText = $office?->name
        ?? $college?->code
        ?? 'Unassigned';

    $hardware = $hardwareResponses ?? ($checklistValues ?? []);
    $software = $softwareResponses ?? ($softwareValues ?? []);

    $checkedByName = $checkedBy?->name ?? $record?->checkedBy?->name ?? auth()->user()?->name ?? '';
    $dateText = \Carbon\Carbon::parse($dateChecked ?? $record?->maintenance_date ?? now())->format('m/d/Y');

    $logoPath = public_path('images/catsu-logo.png');

    $hardwareItems = $checklistItems ?? [
        'system_unit_power_on' => ['group' => 'System Unit', 'label' => 'Check for power on'],
        'monitor_display' => ['group' => 'Monitor', 'label' => 'Check display'],
        'keyboard_keys' => ['group' => 'Keyboard', 'label' => 'Check for keys'],
        'mouse_buttons' => ['group' => 'Mouse', 'label' => 'Check mouse left/right buttons'],
        'avr_ups_power_recovery' => ['group' => 'AVR/UPS', 'label' => 'Check for power recovery'],
        'printer_printout' => ['group' => 'Printer', 'label' => 'Check printout'],
    ];

    $softwareItems = $softwareItems ?? [
        'setup_antivirus' => 'Setup Anti-Virus',
        'system_scan_removal' => 'System Scan and Removal of Malicious Software',
    ];

    $getChecklistData = function ($sourceRecord) {
        $data = $sourceRecord?->checklist_data ?? [];

        if (is_string($data)) {
            $decoded = json_decode($data, true);
            $data = is_array($decoded) ? $decoded : [];
        }

        return is_array($data) ? $data : [];
    };

    $deviceLabel = function ($rowDevice) {
        if (! $rowDevice) {
            return '-';
        }

        $computerName = trim((string) ($rowDevice->computer_name ?? data_get($rowDevice->specs, 'computer_name', '')));
        $typeName = $rowDevice->type?->name ?? 'Equipment';
        $propertyNo = $rowDevice->property_number ?? '';
        $brand = $rowDevice->brand ?? '';
        $condition = strtoupper($rowDevice->condition ?? '');

        $name = $computerName ?: trim($typeName . ' ' . $propertyNo);
        $brandText = $brand ? ' (' . strtoupper($brand) . ')' : '';
        $conditionText = $condition ? "\n(" . $condition . ')' : '';

        return trim($name . $brandText . $conditionText);
    };

    $rows = collect();

    if ($hasRecords) {
        foreach ($records as $rowRecord) {
            $rowData = $getChecklistData($rowRecord);

            $rows->push([
                'device' => $rowRecord->device,
                'hardware' => $rowData['hardware'] ?? $rowData['hardwareResponses'] ?? [],
                'software' => $rowData['software'] ?? $rowData['softwareResponses'] ?? [],
                'remarks' => $rowRecord->remarks ?? '',
                'corrective_action' => $rowRecord->corrective_action ?? data_get($rowData, 'corrective_action', ''),
            ]);
        }
    } else {
        $rows->push([
            'device' => $device,
            'hardware' => $hardware,
            'software' => $software,
            'remarks' => $remarks ?? $record?->remarks ?? '',
            'corrective_action' => $correctiveAction ?? $record?->corrective_action ?? '',
        ]);
    }

    $minimumRows = 10;
@endphp

<div class="pdf-header">
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @if(file_exists($logoPath))
                    <img src="{{ $logoPath }}" class="logo" alt="CatSU Logo">
                @endif
            </td>

            <td class="school-cell">
                <div class="school-top">Republic of the Philippines</div>
                <div class="school-name">CATANDUANES STATE UNIVERSITY</div>
                <div class="school-address">Virac, Catanduanes</div>
            </td>

            <td class="header-spacer"></td>
        </tr>
    </table>

    <div class="blue-line"></div>

    <table class="sub-header-table">
        <tr>
            <td class="unit-title">{{ $headerUnit }}</td>
            <td class="checklist-title">PREVENTIVE MAINTENANCE CHECKLIST</td>
            <td class="date-title">
                Date:
                <span class="line date-line">{{ $dateText }}</span>
            </td>
        </tr>
    </table>

    <table class="office-unit-row">
        <tr>
            <td>
                Office/Unit:
                <span class="line office-line">{{ $officeUnitText }}</span>
            </td>
        </tr>
    </table>
</div>

<div class="pdf-footer">
    <table class="footer-table">
        <tr>
            <td class="footer-left">CatSU-F-ICTU-05</td>
            <td class="footer-center">Rev: 0</td>
            <td class="footer-right">Effectivity Date: July 22, 2024</td>
        </tr>
    </table>
</div>

<div class="content">
    <table class="main-table">
        <thead>
            <tr>
                <th rowspan="3" class="device-col">Computers<br>and<br>Peripherals</th>

                @foreach($hardwareItems as $item)
                    <th colspan="2">{{ $item['group'] ?? '-' }}</th>
                @endforeach

                <th colspan="2">Software</th>
                <th rowspan="3" class="remarks-col">Remarks</th>
                <th rowspan="3" class="corrective-col">Corrective<br>Action</th>
            </tr>

            <tr>
                @foreach($hardwareItems as $item)
                    <th colspan="2">{{ $item['label'] ?? '-' }}</th>
                @endforeach

                <th rowspan="2" class="software-col">Setup Anti-<br>Virus</th>
                <th rowspan="2" class="software-col">System Scan<br>and Removal<br>of Malicious<br>Software</th>
            </tr>

            <tr>
                @foreach($hardwareItems as $item)
                    <th class="pair-ok">OK</th>
                    <th class="pair-ok">Not<br>OK</th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            @foreach($rows as $row)
                @php
                    $rowDevice = $row['device'];
                    $rowHardware = $row['hardware'] ?? [];
                    $rowSoftware = $row['software'] ?? [];
                    $rowRemarks = $row['remarks'] ?? '';
                    $rowCorrectiveAction = $row['corrective_action'] ?? '';
                @endphp

                <tr class="table-row">
                    <td class="left">{!! nl2br(e($deviceLabel($rowDevice))) !!}</td>

                    @foreach($hardwareItems as $key => $item)
                        @php $value = $rowHardware[$key] ?? ''; @endphp
                        <td class="check">{{ $value === 'OK' ? '✓' : '' }}</td>
                        <td class="check">{{ $value === 'Not OK' ? '✓' : '' }}</td>
                    @endforeach

                    <td class="check">{{ ($rowSoftware['setup_antivirus'] ?? '') === 'check' ? '✓' : '' }}</td>

                    <td class="check">
                        {{ ($rowSoftware['system_scan_removal'] ?? '') === 'check'
                            ? '✓'
                            : (($rowSoftware['system_scan_removal'] ?? '') === 'dash' ? '-' : '') }}
                    </td>

                    <td>{{ $rowRemarks }}</td>
                    <td>{{ $rowCorrectiveAction }}</td>
                </tr>
            @endforeach

            @for($i = $rows->count(); $i < $minimumRows; $i++)
                <tr class="blank-row">
                    <td>&nbsp;</td>

                    @foreach($hardwareItems as $item)
                        <td></td>
                        <td></td>
                    @endforeach

                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            @endfor
        </tbody>
    </table>

    <table class="signature-table">
        <tr>
            <td>
                <span class="sig-label">Checked by:</span>
                <span class="sig-line">{{ $checkedByName }}</span>
                <br>
                <span class="sig-label">Date:</span>
                <span class="sig-date-line">{{ $dateText }}</span>
            </td>

            <td style="text-align: right;">
                <span class="sig-label">Approved by:</span>
                <span class="sig-line"></span>
                <br>
                <span class="sig-label">Date:</span>
                <span class="sig-date-line"></span>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
