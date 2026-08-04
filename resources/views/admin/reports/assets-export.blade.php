<table>
    <thead>
        <tr>
            <th colspan="10">PMAMS All Assets Report</th>
        </tr>
        <tr>
            <th colspan="10">Generated: {{ $generatedAt->format('M d, Y h:i A') }}</th>
        </tr>
        <tr>
            <th>Type</th>
            <th>Property #</th>
            <th>Serial #</th>
            <th>Brand / Model</th>
            <th>Status</th>
            <th>Condition</th>
            <th>Unit Price</th>
            <th>College</th>
            <th>Office</th>
            <th>Assigned To</th>
        </tr>
    </thead>
    <tbody>
        @foreach($devices as $device)
            @php
                $assignment = $device->currentAssignment;
                $staff = $assignment?->staff;
                $office = $assignment?->office ?: $staff?->office;
                $college = $assignment?->location ?? $office?->college;
                $staffName = $staff
                    ? trim(($staff->last_name ?? '') . ', ' . ($staff->first_name ?? ''))
                    : ($assignment?->location ? 'Location assignment' : '-');
            @endphp
            <tr>
                <td>{{ $device->type?->name ?? '-' }}</td>
                <td>{{ $device->property_number ?? '-' }}</td>
                <td>{{ $device->serial_number ?: '-' }}</td>
                <td>{{ trim(($device->brand ?? '') . ' ' . ($device->model ?? '')) ?: '-' }}</td>
                <td>{{ $device->status ?: '-' }}</td>
                <td>{{ $device->condition ?: '-' }}</td>
                <td>{{ $device->unit_price !== null ? number_format((float) $device->unit_price, 2) : '-' }}</td>
                <td>{{ $college?->name ?? '-' }}</td>
                <td>{{ $office?->name ?? '-' }}</td>
                <td>{{ $staffName ?: '-' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
