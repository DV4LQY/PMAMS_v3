<table>
    {{-- The Excel export keeps the reserved header rows for the logo/rule,
         but does not repeat the institution text shown in the PDF header. --}}
    <tr><td colspan="12"></td></tr>
    <tr><td colspan="12"></td></tr>
    <tr><td colspan="12"></td></tr>
    <tr><td colspan="12"></td></tr>
    <tr><td colspan="12">QUALITY OBJECTIVE MONITORING SUPPORTING SCHEDULE</td></tr>
    <tr><td colspan="12">For {{ $period['label'] }}, CY {{ $period['year'] }}</td></tr>
    <tr><td colspan="12">Quality Objective No. I: Ensure efficient maintenance of IT equipment and peripherals</td></tr>
    <tr><td colspan="12">Target: {{ $summary['target_percent'] }}%</td></tr>
    <tr><td colspan="12"></td></tr>
    <tr>
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
    </tr>
    @forelse($rows as $row)
        <tr>
            <td>{{ $row['office'] }}</td>
            <td>{{ $row['target'] }}</td>
            <td>{{ $row['condemned'] }}</td>
            <td>{{ $row['unserviceable'] }}</td>
            <td>{{ $row['additional'] }}</td>
            <td>{{ $row['actual'] }}</td>
            <td>{{ $row['transferred_in'] }}</td>
            <td>{{ $row['transferred_out'] }}</td>
            <td>{{ $row['dates'] ?: '' }}</td>
            <td>{{ trim($row['remarks'] . (count($row['warnings']) ? ' ' . implode(' ', $row['warnings']) : '')) }}</td>
            <td></td>
            <td></td>
        </tr>
    @empty
        <tr>
            <td>No PM Plan schedules matched the selected period and filters.</td>
            @for($column = 2; $column <= 12; $column++)<td></td>@endfor
        </tr>
    @endforelse
    <tr><td colspan="12"></td></tr>
    <tr><td>No. of IT equipment / peripherals checked</td><td></td><td colspan="10"></td></tr>
    <tr><td>Total IT equipment / peripherals to be checked / maintained</td><td></td><td colspan="10"></td></tr>
    <tr><td>Percentage of Accomplishment</td><td></td><td colspan="10"></td></tr>
    <tr><td colspan="12"></td></tr>
    <tr>
        <td colspan="4">Prepared by:</td>
        <td colspan="4"></td>
        <td colspan="4">Certified correct:</td>
    </tr>
    <tr><td colspan="12"></td></tr>
    <tr>
        <td colspan="4"><strong>{{ strtoupper($preparedBy?->name ?: 'SYSTEM') }}</strong></td>
        <td colspan="4"></td>
        <td colspan="4"><strong>{{ strtoupper($unitHead?->name ?: '') }}</strong></td>
    </tr>
    <tr>
        <td colspan="4">{{ $preparedBy?->position ?: ($preparedBy?->roleLabel() ?: '') }}</td>
        <td colspan="4"></td>
        <td colspan="4">{{ $unitHead?->position ?: ($unitHead?->roleLabel() ?: 'Unit Head') }}</td>
    </tr>
    <tr>
        <td colspan="4">CatSU-F-PMC-01C</td>
        <td colspan="4">Rev. 2</td>
        <td colspan="4">Effectivity Date: March 10, 2025</td>
    </tr>
</table>
