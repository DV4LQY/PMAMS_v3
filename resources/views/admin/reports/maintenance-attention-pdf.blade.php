<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Maintenance Attention Report</title>
    <style>
        /* Keep the institutional header in the reserved top margin so Dompdf
           repeats it on every page without colliding with the report table. */
        /* Reserve only the space needed below the institutional heading.
           The report title and table now flow immediately under the blue
           line instead of leaving a large unused top band. */
        @page { size: 13in 8.5in; margin: 96px 0.4in 28px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111827; font-family: Arial, Helvetica, sans-serif; font-size: 9px; }
        /* The rule belongs immediately below the institutional logo row; a
           small two-pixel gap keeps it visually separate without leaving a
           large band of empty space. */
        /* Offset the fixed header by the same amount as the page margin so
           it remains at the top of every printed page. */
        .header { position: fixed; top: -94px; left: 0; right: 0; height: 62px; border-bottom: 1.5px solid #4472c4; padding-bottom: 2px; z-index: 10; }
        .header-table, .report, .signoff { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: middle; }
        .logo { width: 52px; height: 52px; object-fit: contain; }
        .iso { width: 86px; height: 42px; object-fit: contain; }
        .institution { font-size: 10px; line-height: 1.3; }
        .institution strong { font-size: 12px; text-transform: uppercase; }
        /* Fixed elements are repeated by Dompdf. This keeps the ICTU label
           directly below the institutional rule on every printed page. */
        .report-unit { position: fixed; top: -26px; left: 0; right: 0; margin: 0; font-size: 10px; font-style: italic; font-weight: bold; z-index: 10; }
        /* Keep the title outside the fixed-width data grid. In Dompdf, a
           leading colspan row makes every data column equal-width even when
           a colgroup is supplied. The first table row must be the real
           column-heading row for its declared widths to take effect. */
        .report-page-heading { margin: 0; padding: 0; }
        h1 { margin: 0 0 2px; text-align: center; font-size: 15px; }
        .meta { margin: 0 0 6px; text-align: center; font-size: 9px; color: #374151; }
        /* The printable area is 878.4pt wide (13in landscape less the two
           0.4in page margins). Fixed point widths keep the full report grid
           aligned on every printed page. */
        .report { width: 878.4pt; table-layout: fixed; margin: 0; }
        .report thead { display: table-header-group; }
        .report tbody { display: table-row-group; }
        .report th, .report td { border: 0.65px solid #111827; padding: 3.5px 4px; vertical-align: top; word-wrap: break-word; }
        .report th.column-heading { background: #d9eaf7; text-align: center; font-size: 8px; }
        .report td { line-height: 1.25; }
        /* Keep the complete recommendation/description in the first row (and
           every following row) inside its cell.  Dompdf can otherwise clip a
           long unbroken value at the top of a record table when the fixed
           heading is repeated. */
        .reason-cell { overflow: visible; }
        .reason-text { display: block; white-space: normal; overflow: visible; overflow-wrap: anywhere; word-break: normal; }
        .report tbody tr { page-break-inside: avoid; height: auto; }
        .report tbody td { height: auto; }
        .pm-schedule { white-space: pre-line; }
        .responsible { margin-top: 2px; font-size: 8px; color: #374151; }
        /* PDF column widths are defined inline in the colgroup below. Dompdf
           reliably honors inline percentage widths, unlike CSS classes on
           <col> elements. Keep their total at exactly 100%. */
        .empty { text-align: center; padding: 14px !important; }
        .score-legend { margin-top: 5px; border-top: 0.65px solid #9ca3af; border-bottom: 0.65px solid #9ca3af; padding: 3px 0; font-size: 8px; line-height: 1.25; }
        .score-legend strong { font-size: 8.5px; }
        .signoff { margin-top: 8px; table-layout: fixed; }
        .signoff td { width: 33.33%; padding: 0 14px; text-align: center; vertical-align: top; }
        .signoff-label { text-align: left; font-size: 9px; margin-bottom: 10px; }
        .printed-name { display: inline-block; border-bottom: 0.8px solid #111827; padding: 0 8px 2px; font-size: 10px; font-weight: bold; text-transform: uppercase; min-height: 12px; min-width: 82%; }
        .position { font-size: 9px; min-height: 12px; }
        /* Repeated on every printed page, matching the institutional report
           footer used by the other maintenance reports. */
        .report-footer { position: fixed; left: 0; right: 0; bottom: -17px; height: 14px; border-top: 1.25px solid #4472c4; padding-top: 3px; color: #4b5563; font-size: 8px; font-style: italic; }
        .report-footer .footer-right { float: right; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width:65px">@if(is_file($logoPath))<img src="{{ $logoPath }}" class="logo" alt="CatSU">@endif</td>
                <td class="institution"><div>Republic of the Philippines</div><strong>CATANDUANES STATE UNIVERSITY</strong><div>Virac, Catanduanes</div></td>
                <td style="width:100px; text-align:right">@if(is_file($isoLogoPath))<img src="{{ $isoLogoPath }}" class="iso" alt="ISO 9001:2015">@endif</td>
            </tr>
        </table>
    </div>

    <div class="report-unit">Information Communication Technology Unit (ICTU)</div>

    <div class="report-page-heading">
        <h1>Maintenance Attention Report</h1>
        <p class="meta">Year: {{ $filters['year'] ?: 'All years' }} &nbsp; | &nbsp; Semester: {{ ($filters['semester'] ?? null) === 1 ? '1st Semi-Annually (Jan-Jun)' : (($filters['semester'] ?? null) === 2 ? '2nd Semi-Annually (Jul-Dec)' : 'All semesters') }} &nbsp; | &nbsp; Location: {{ $filters['location'] ?: 'All locations' }} &nbsp; | &nbsp; Office: {{ $filters['office'] ?: 'All offices' }} &nbsp; | &nbsp; Equipment: {{ !empty($filters['equipment_types']) ? implode(', ', $filters['equipment_types']) : 'All types' }} &nbsp; |  &nbsp; Generated: {{ $generatedAt->format('M d, Y h:i A') }}</p>
    </div>

    <table class="report">
        {{-- Change the percentages below to adjust the PDF columns. Keep their total at 100%. --}}
        <colgroup>
            <col width="14%" style="width:14%"> {{-- Property Number --}}
            <col width="12%" style="width:12%"> {{-- Computer Name --}}
            <col width="9%" style="width:9%">   {{-- Equipment Type --}}
            <col width="8%" style="width:8%">   {{-- Condition --}}
            <col width="7%" style="width:7%">   {{-- Status --}}
            <col width="6%" style="width:6%">   {{-- Priority --}}
            <col width="5%" style="width:5%">   {{-- Score --}}
            <col width="17%" style="width:17%"> {{-- Recommendation / Reason --}}
            <col width="9%" style="width:9%">   {{-- Last Maintenance --}}
            <col width="13%" style="width:13%"> {{-- PM Plan Schedule --}}
        </colgroup>
        <thead>
            <tr>
                <th class="column-heading" width="14%" style="width:14%">Property Number</th><th class="column-heading" width="12%" style="width:12%">Computer Name</th><th class="column-heading" width="9%" style="width:9%">Equipment Type</th><th class="column-heading" width="8%" style="width:8%">Condition</th><th class="column-heading" width="7%" style="width:7%">Status</th><th class="column-heading" width="6%" style="width:6%">Priority</th><th class="column-heading" width="5%" style="width:5%">Score</th><th class="column-heading" width="17%" style="width:17%">Recommendation / Reason</th><th class="column-heading" width="9%" style="width:9%">Last Maintenance</th><th class="column-heading" width="13%" style="width:13%">PM Plan Schedule</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                @php($device = $row['device'])
                <tr>
                    <td>{{ $device->property_number ?: '' }}</td>
                    <td>{{ $device->computer_name ?: '' }}</td>
                    <td>{{ $device->type?->name ?: '' }}</td>
                    <td>{{ $row['condition'] ? ucwords(str_replace('_', ' ', $row['condition'])) : '' }}</td>
                    <td>{{ $row['report_status'] ? ucwords(str_replace('_', ' ', $row['report_status'])) : '' }}</td>
                    <td style="text-align:center">{{ $row['priority'] ?: '' }}</td>
                    <td style="text-align:center">{{ $row['score'] ?? '' }}</td>
                    <td class="reason-cell"><div class="reason-text">{{ implode('; ', $row['report_reasons'] ?? []) }}</div></td>
                    <td>{{ $row['last_maintenance']?->format('M d, Y') ?: '' }}</td>
                    <td class="pm-schedule">{{ $row['pm_schedule'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="empty">No maintenance-attention records matched the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="score-legend">
        <strong>Priority and Score Explanation:</strong>
        Critical (75–100): Immediate attention required;&nbsp;
        High (50–74): Attention should be scheduled soon;&nbsp;
        Medium (25–49): Monitor and include in the next maintenance cycle;&nbsp;
        Low (0–24): No urgent issue detected; continue monitoring.<br>
        The score is capped at 100 and is advisory only.
    </div>

    <table class="signoff">
        <tr>
            <td><div class="signoff-label">Acknowledged by:</div><div class="printed-name">{{ $signatories['head']?->display_name ?: '' }}</div><div class="position">{{ $signatories['head']?->position ?: ($signatories['head_title'] ?? 'Head of Unit') }}</div></td>
            <td><div class="signoff-label">Checked by:</div><div class="printed-name">{{ $signatories['checked_by_names'] ?? '' }}</div><div class="position">{{ $signatories['checked_by_position'] ?? 'Admin' }}</div></td>
            <td><div class="signoff-label">Certified correct:</div><div class="printed-name">{{ $signatories['it_officer']?->name ?: '' }}</div><div class="position">{{ $signatories['it_officer']?->position ?: 'IT Officer - I' }}</div></td>
        </tr>
    </table>

    <div class="report-footer">
        <span>PMAMS Maintenance Attention Report</span>
       <!-- <span class="footer-right">Generated {{ $generatedAt->format('Y-m-d') }}</span> -->
    </div>

</body>
</html>
