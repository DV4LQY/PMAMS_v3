<table>
    <thead>
        <tr>
            <th colspan="12">PMAMS Issued Equipment Report</th>
        </tr>
        <tr>
            <th colspan="12">Generated: {{ $generatedAt->format('M d, Y h:i A') }}</th>
        </tr>
        <tr>
            <th>No.</th>
            <th>Origin User</th>
            <th>Origin Office / Location</th>
            <th>Transferred To</th>
            <th>Destination Office / Location</th>
            <th>Equipment Type</th>
            <th>Brand / Model</th>
            <th>Property #</th>
            <th>Serial #</th>
            <th>Issued Date</th>
            <th>Issued By</th>
            <th>Remarks</th>
        </tr>
    </thead>
    <tbody>
        @foreach($assignments as $assignment)
            @php
                $staff = $assignment->staff;
                $device = $assignment->device;
                $origin = $assignment->previousAssignment();
                $originStaff = $origin?->staff;
                $originOffice = $origin?->office ?: $originStaff?->office;
                $originLocation = $origin?->location ?: $originOffice?->location;
                $office = $assignment->office ?: $staff?->office;
                $location = $assignment->location ?: $office?->location;
                $originName = $origin
                    ? ($originStaff ? trim(($originStaff->last_name ?? '') . ', ' . ($originStaff->first_name ?? '')) : 'Shared / Location assignment')
                    : 'Initial issue / inventory';
                $destinationName = $staff ? trim(($staff->last_name ?? '') . ', ' . ($staff->first_name ?? '')) : 'Shared / Location assignment';
                $equipmentName = trim(($device?->brand ?? '') . ' ' . ($device?->model ?? '')) ?: '-';
            @endphp
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $originName }}</td>
                <td>{{ $origin ? ($originOffice?->name ?? '-') : 'Inventory' }} / {{ $origin ? ($originLocation?->code ?: ($originLocation?->name ?? '-')) : '-' }}</td>
                <td>{{ $destinationName }}{{ $staff?->position ? ' / ' . $staff->position : '' }}</td>
                <td>{{ $office?->name ?? '-' }} / {{ $location?->code ?: ($location?->name ?? '-') }}</td>
                <td>{{ $device?->type?->name ?? '-' }}</td>
                <td>{{ $equipmentName }}</td>
                <td>{{ $device?->property_number ?? '-' }}</td>
                <td>{{ $device?->serial_number ?: '-' }}</td>
                <td>{{ $assignment->issued_at?->format('M d, Y h:i A') ?? '-' }}</td>
                <td>{{ $assignment->issuer?->name ?? '-' }}</td>
                <td>{{ $assignment->remarks ?: '-' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
