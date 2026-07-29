<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Preventive Maintenance Schedule Monitoring</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 9px; }
        .header { text-align: center; margin-bottom: 12px; }
        .header .unit { font-size: 10px; font-weight: bold; }
        .header h1 { margin: 3px 0 12px; font-size: 15px; text-transform: uppercase; }
        .date { text-align: left; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #222; padding: 5px; vertical-align: top; word-wrap: break-word; }
        th { background: #eee; font-weight: bold; text-align: left; }
        th:nth-child(1) { width: 23%; }
        th:nth-child(2) { width: 18%; }
        th:nth-child(3) { width: 14%; }
        th:nth-child(4) { width: 17%; }
        th:nth-child(5) { width: 14%; }
        th:nth-child(6) { width: 14%; }
        .approved { margin-top: 26px; }
        .signature { margin-top: 24px; width: 230px; border-bottom: 1px solid #111; text-align: center; font-weight: bold; }
        .role { width: 230px; text-align: center; font-size: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="unit">Information and Communications Technology (ICT) Unit</div>
        <h1>Preventive Maintenance Schedule Monitoring</h1>
    </div>
    <div class="date">Date: {{ $generatedAt->format('m/d/Y') }}</div>
    <table>
        <thead>
            <tr>
                <th>Office</th>
                <th>Schedule of Maintenance</th>
                <th>Actual Date of Maintenance</th>
                <th>Person/s In Charge</th>
                <th>Signature</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                @php($completion = $row['completion'])
                <tr>
                    <td>{{ $row['office'] }}</td>
                    <td>
                        Original: {{ $row['original_schedule'] }}
                        @if($row['override_schedule'])
                            <br><strong>Override: {{ $row['override_schedule'] }}</strong>
                            <br><small>Reason: {{ $row['override_reason'] }}</small>
                        @endif
                    </td>
                    <td>{{ $row['actual_date'] ?: '' }}</td>
                    <td>{{ $completion?->person_in_charge ?: '' }}</td>
                    <td>{{ $completion?->signature ?: '' }}</td>
                    <td>{{ $completion?->remarks ?: ($row['is_complete'] ? 'All equipment checked; completion details pending.' : 'Pending maintenance') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center">No schedule records found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="approved">Approved by:</div>
    <div class="signature">{{ $unitHead?->name ?: '____________________________' }}</div>
    <div class="role">{{ $unitHead?->roleLabel() ?: 'Information Technology Officer I' }}</div>
</body>
</html>
