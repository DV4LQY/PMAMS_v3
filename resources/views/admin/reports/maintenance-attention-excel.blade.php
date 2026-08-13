<table>
    {{-- Keep this row structure in sync with the PDF header. The drawings are
         anchored to A1 and J1; the institutional text occupies the middle. --}}
    <tr><td></td><td colspan="8">Republic of the Philippines</td><td></td></tr>
    <tr><td></td><td colspan="8"><strong>CATANDUANES STATE UNIVERSITY</strong></td><td></td></tr>
    <tr><td></td><td colspan="8">Virac, Catanduanes</td><td></td></tr>
    <tr><td colspan="10"><em>Information Communication Technology Unit (ICTU)</em></td></tr>
    <tr><td colspan="10">MAINTENANCE ATTENTION REPORT</td></tr>
    <tr><td colspan="10">Year: {{ $filters['year'] ?: 'All years' }} | Semester: {{ ($filters['semester'] ?? null) === 1 ? '1st Semi-Annually (Jan-Jun)' : (($filters['semester'] ?? null) === 2 ? '2nd Semi-Annually (Jul-Dec)' : 'All semesters') }} | Location: {{ $filters['location'] ?: 'All locations' }} | Office: {{ $filters['office'] ?: 'All offices' }} | Equipment: {{ !empty($filters['equipment_types']) ? implode(', ', $filters['equipment_types']) : 'All types' }} | Generated: {{ $generatedAt->format('M d, Y h:i A') }}</td></tr>
    <tr><td colspan="10"></td></tr>
    <tr>
        <th>Property Number</th>
        <th>Computer Name</th>
        <th>Equipment Type</th>
        <th>Condition</th>
        <th>Status</th>
        <th>Priority</th>
        <th>Score</th>
        <th>Recommendation / Reason</th>
        <th>Last Maintenance</th>
        <th>PM Plan Schedule</th>
    </tr>
    @forelse($rows as $row)
        @php($device = $row['device'])
        <tr>
            <td>{{ $device->property_number ?: '' }}</td>
            <td>{{ $device->computer_name ?: '' }}</td>
            <td>{{ $device->type?->name ?: '' }}</td>
            <td>{{ $row['condition'] ? ucwords(str_replace('_', ' ', $row['condition'])) : '' }}</td>
            <td>{{ $row['report_status'] ? ucwords(str_replace('_', ' ', $row['report_status'])) : '' }}</td>
            <td>{{ $row['priority'] ?: '' }}</td>
            <td>{{ $row['score'] ?? '' }}</td>
            <td>{{ implode('; ', $row['report_reasons'] ?? []) }}</td>
            <td>{{ $row['last_maintenance']?->format('M d, Y') ?: '' }}</td>
            <td>{{ $row['pm_schedule'] ?? '' }}</td>
        </tr>
    @empty
        <tr><td>No maintenance-attention records matched the selected filters.</td>@for($column = 2; $column <= 10; $column++)<td></td>@endfor</tr>
    @endforelse
    <tr><td colspan="10"><strong>Priority and Score Explanation</strong></td></tr>
    <tr><td colspan="10">Critical (75-100): Immediate attention required | High (50-74): Attention should be scheduled soon | Medium (25-49): Monitor and include in the next maintenance cycle | Low (0-24): No urgent issue detected; continue monitoring.</td></tr>
    <tr><td colspan="10">The score is capped at 100 and is advisory only; existing approval workflows remain required.</td></tr>
    {{-- Sign-off block mirrors the PDF: label, signature line, name, then role. --}}
    <tr><td colspan="10"></td></tr>
    <tr>
        <td colspan="4">Acknowledged by:</td><td colspan="3">Checked by:</td><td colspan="3">Certified correct:</td>
    </tr>
    <tr>
        <td colspan="4"></td><td colspan="3"></td><td colspan="3"></td>
    </tr>
    <tr>
        <td colspan="4"><strong>{{ strtoupper($signatories['head']?->display_name ?: '') }}</strong></td>
        <td colspan="3"><strong>{{ strtoupper($signatories['checked_by_names'] ?? '') }}</strong></td>
        <td colspan="3"><strong>{{ strtoupper($signatories['it_officer']?->name ?: '') }}</strong></td>
    </tr>
    <tr>
        <td colspan="4">{{ $signatories['head']?->position ?: ($signatories['head_title'] ?? 'Head of Unit') }}</td>
        <td colspan="3">{{ $signatories['checked_by_position'] ?? 'Assigned PM Plan Admins' }}</td>
        <td colspan="3">{{ $signatories['it_officer']?->position ?: 'IT-Officer I' }}</td>
    </tr>
    <tr><td colspan="10"></td></tr>
    <tr>
        <td colspan="5">PMAMS Maintenance Attention Report</td>
        <td colspan="5" style="text-align:right">Generated {{ $generatedAt->format('Y-m-d') }}</td>
    </tr>
</table>
