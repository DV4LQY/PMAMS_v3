<table>
    <tr><td colspan="6"></td></tr>
    <tr><td colspan="6"></td></tr>
    <tr><td colspan="6"></td></tr>
    <tr><td colspan="6"></td></tr>
    <tr><td colspan="6"></td></tr>
    <tr><td colspan="6"></td></tr>
    <tr><td colspan="6"></td></tr>
    <tr><td colspan="6"></td></tr>
    <tr><td colspan="6">Quality Objective No. I: Ensure efficient maintenance of IT equipment and peripherals.</td></tr>
    <tr><td colspan="6">Target: {{ $summary['target_percent'] }}%</td></tr>
    @for($row = 11; $row <= 24; $row++)
        <tr><td colspan="6"></td></tr>
    @endfor
    <tr>
        <td colspan="2"></td>
        <td>{{ $performanceGraph['columns'][1]['label'] }}</td>
        <td>{{ $performanceGraph['columns'][2]['label'] }}</td>
        <td colspan="2"></td>
    </tr>
    <tr>
        <td colspan="2">(Actual data)</td>
        <td></td><td></td><td colspan="2"></td>
    </tr>
    <tr>
        <td colspan="2">(Baseline)</td>
        <td></td><td></td><td colspan="2"></td>
    </tr>
    <tr>
        <td colspan="2">Actual</td>
        <td></td><td></td><td colspan="2"></td>
    </tr>
    <tr>
        <td colspan="2">Target</td>
        <td></td><td></td><td colspan="2"></td>
    </tr>
    <tr>
        <td colspan="2">Status</td>
        <td></td><td></td><td colspan="2"></td>
    </tr>
    <tr><td colspan="6"></td></tr>
    <tr><td colspan="6"></td></tr>
    <tr>
        <td colspan="2">Prepared by:</td><td></td>
        <td colspan="2">Certified Correct:</td><td></td>
    </tr>
    <tr><td colspan="6"></td></tr>
    <tr><td colspan="6"></td></tr>
    <tr>
        <td colspan="2"><strong>{{ strtoupper($preparedBy?->name ?: 'SYSTEM') }}</strong></td><td></td>
        <td colspan="2"><strong>{{ strtoupper($unitHead?->name ?: '') }}</strong></td><td></td>
    </tr>
    <tr>
        <td colspan="2">{{ $preparedBy?->position ?: ($preparedBy?->roleLabel() ?: '') }}</td><td></td>
        <td colspan="2">{{ $unitHead?->position ?: ($unitHead?->roleLabel() ?: 'Unit Head') }}</td><td></td>
    </tr>
    <tr><td colspan="6"></td></tr>
    <tr>
        <td colspan="2">CatSU-F-PMC-01C</td><td>Rev. 2</td>
        <td colspan="3">Effectivity: March 10, 2025</td>
    </tr>
</table>
