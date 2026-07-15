<table>
    <thead>
        <tr>
            <th colspan="11">PMAMS Issued Equipment Report</th>
        </tr>
        <tr>
            <th colspan="11">Generated: {{ $generatedAt->format('M d, Y h:i A') }}</th>
        </tr>
        <tr>
            <th>No.</th>
            <th>End User</th>
            <th>Position</th>
            <th>Office</th>
            <th>Location</th>
            <th>Equipment Type</th>
            <th>Brand / Model</th>
            <th>Property #</th>
            <th>Serial #</th>
            <th>Issued Date</th>
            <th>Remarks</th>
        </tr>
    </thead>
    <tbody>
        @foreach($assignments as $assignment)
            @php
                $staff = $assignment->staff;
                $device = $assignment->device;
                $office = $staff?->office;
                $location = $office?->location;
                $staffName = $staff ? trim(($staff->last_name ?? '') . ', ' . ($staff->first_name ?? '')) : '-';
                $equipmentName = trim(($device?->brand ?? '') . ' ' . ($device?->model ?? '')) ?: '-';
            @endphp
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $staffName }}</td>
                <td>{{ $staff?->position ?: '-' }}</td>
                <td>{{ $office?->name ?? '-' }}</td>
                <td>{{ $location?->code ?: ($location?->name ?? '-') }}</td>
                <td>{{ $device?->type?->name ?? '-' }}</td>
                <td>{{ $equipmentName }}</td>
                <td>{{ $device?->property_number ?? '-' }}</td>
                <td>{{ $device?->serial_number ?: '-' }}</td>
                <td>{{ $assignment->issued_at?->format('M d, Y h:i A') ?? '-' }}</td>
                <td>{{ $assignment->remarks ?: '-' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
