<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Preventive Maintenance Schedule Monitoring</title>
    <style>
        /* Reserve enough room for the two-line institutional heading before
           the table, matching the supplied controlled-form layout. */
        @page { size: 13in 8.5in; margin: 142px 24px 30px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #000000; font-family: DejaVu Sans, Arial, sans-serif, Calibri; font-size: 12px; }
        .page-header { position: fixed; top: -132px; left: 0; right: 0; height: 132px; }
        .page-footer { position: fixed; bottom: -88px; left: 0; right: 0; height: 84px; }
        .header-table, .footer-table { width: 100%; border-collapse: collapse; }
        .header-table td, .footer-table td { border: 0; padding: 0; vertical-align: top; }
        .logo { width: 50px; height: 50px; object-fit: contain; }
        .iso-logo { width: 132px; height: 45px; object-fit: contain; }
        .school { line-height: 1.15; padding-left: 8px !important; }
        .school-name { font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .blue-rule { border-bottom: 1.5px solid #1e40af; margin-top: 3px; }
        .unit { margin-top: 4px; font-size: 12px; font-weight: bold; font-style: italic; text-align: left}
        .title { margin-top: 9px; text-align: left; font-size: 14px; font-weight: bold; text-align: center}
        /* .date { margin-top: 6px; font-size: 11px; } */
        .approval-name { margin-top: 26px; color: #000000; font-weight: bold; }
        .approval-role { font-size: 10px; }
        /* Keep a clear signing gap without leaving most of the page unused. */
        .approval-block { margin-top: 0.5in; }
        .document-footer { border-top: 1.5px solid #1e40af; padding-top: 4px; display: table; width: 100%; color: #000000; font-size: 11px; font-style: italic; }
        .document-footer span { display: table-cell; width: 33%; }
        .document-footer span:nth-child(2) { text-align: center; }
        .document-footer span:nth-child(3) { text-align: right; }
        table.report { width: 100%; margin-top: 14px; border-collapse: collapse; table-layout: fixed; }
        .report th, .report td { border: 1px solid #000000; padding: 5px; vertical-align: top; overflow-wrap: anywhere;  }
        .report td:nth-child(4) {text-align: center;}
        .report th { background: #e5e7eb; text-align: center }
        .report th:nth-child(1) { width: 25%; } .report th:nth-child(2) { width: 16%; }
        .report th:nth-child(3) { width: 16%; } .report th:nth-child(4) { width: 13%; }
        .report th:nth-child(5) { width: 15%; } .report th:nth-child(6) { width: 20%; }
        .signature-cell { min-height: 72px; padding: 4px 5px 5px !important; text-align: center; vertical-align: top !important; line-height: 1.15; }
        /* Reserve a wet-signature area only when no digital signature image
           was saved. Digital signatures stay close to the top border. */
        .signature-cell.signature-text { padding-top: 22px !important; }
        .remarks-cell { padding-top: 2px !important; }
        .sig-image { display: block; margin: 0 auto 2px; max-height: 30px; max-width: 100px; object-fit: contain; }
        .muted { color: #000000; font-size: 10px; }
    </style>
</head>
<body>
@php($logoPath = public_path('images/catsu-logo.png'))
@php($isoLogoPath = public_path('images/iso-9001-2015.jpg'))
<div class="page-header">
    <table class="header-table"><tr>
        <td style="width:52px">@if(file_exists($logoPath))<img src="{{ $logoPath }}" class="logo" alt="CatSU Logo">@endif</td>
        <td class="school"><div><em>Republic of the Philippines</em></div><div class="school-name">Catanduanes State University</div><div>Virac, Catanduanes</div></td>
        <td style="width:145px; text-align:right">@if(file_exists($isoLogoPath))<img src="{{ $isoLogoPath }}" class="iso-logo" alt="TÜV Rheinland ISO 9001:2015 certified">@endif</td>
    </tr></table>
    <div class="blue-rule"></div>
    <div class="unit">Information and Communications Technology (ICT) Unit</div>
    <div class="title">Preventive Maintenance Schedule Monitoring</div>
    <!-- <div class="date">Date: <span style="display:inline-block; border-bottom:1px solid #000000; width:130px; text-align:center">{{ $generatedAt->format('m/d/Y') }}</span></div> -->
</div>

<table class="report">
    <thead><tr><th>Office</th><th>Schedule of Maintenance</th><th>Actual Date of Maintenance</th><th>Person/s In Charge</th><th>Signature</th><th>Remarks</th></tr></thead>
    <tbody>
    @forelse($rows as $row)
        @php($completion = $row['completion'])
        @php($hasDigitalSignature = filled($completion?->signature_data))
        <tr>
            <td>{{ $row['office'] }}</td>
            <td>
                @if($row['override_schedule'])
                    <strong style="font-size: 10px;">Scheduled:</strong> {{ $row['original_schedule'] }}<br><strong style="font-size: 10px;">Re-Scheduled on:</strong> {{ $row['override_schedule'] }}
                @else
                    {{ $row['original_schedule'] }}
                @endif
            </td>
            <td>{{ $row['actual_date'] ?: '' }}</td>
            <td>{{ $row['person_in_charge'] ?: '' }}</td>
            <td class="signature-cell {{ $hasDigitalSignature ? 'signature-digital' : 'signature-text' }}">@if($hasDigitalSignature)<img src="{{ $completion->signature_data }}" class="sig-image" alt="Digital signature">@endif<div>{{ $completion?->signer_name ?: ($hasDigitalSignature ? '' : $completion?->signature) }}</div></td>
            <td class="remarks-cell">{{ $completion?->remarks ?: '' }}@if($row['override_schedule'] && filled($row['override_reason']))<br><span class="muted" style="font-size: 10px;">Rescheduled due to: {{ $row['override_reason'] }}</span>@endif</td>
        </tr>
    @empty
        <tr><td colspan="6" style="text-align:center">No schedule records found.</td></tr>
    @endforelse
    </tbody>
</table>
<div class="approval-block">
    <table class="footer-table"><tr>
        <td style="width:50%"></td>
        <td style="width:50%; padding-left:100px; text-align:left">
            <div>Approved by:</div>
            <div class="approval-name">{{ $unitHead?->name ?: 'JAY-R R. REDITA,MIT' }}</div>
            <div class="approval-role">{{ $unitHead?->roleLabel() ?: 'Information Technology Officer I' }}</div>
        </td>
    </tr></table>
</div>
<div class="page-footer">
    <div class="document-footer"><span>CatSU-F-ICTU-07</span><span>Rev: 0</span><span>Effectivity Date: June 05, 2025</span></div>
</div>
</body>
</html>
