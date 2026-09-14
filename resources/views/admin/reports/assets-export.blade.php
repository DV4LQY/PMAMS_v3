<table>
    <thead>
        <tr>
            <th colspan="11">PMAMS All Assets Report</th>
        </tr>
        <tr>
            <th colspan="11">
                Generated: {{ $generatedAt->format('M d, Y h:i A') }}
                @if(($filters['pm_plan_scope'] ?? false))
                    · PM Plan scope only
                @endif
            </th>
        </tr>
        <tr>
            <th>Type</th>
            <th>Property #</th>
            <th>Serial #</th>
            <th>Brand / Model</th>
            <th>Status</th>
            <th>Condition</th>
            <th>Maintenance</th>
            <th>Unit Price</th>
            <th>Location</th>
            <th>Office</th>
            <th>Assigned To</th>
        </tr>
    </thead>
    <tbody>
        @foreach($devices as $device)
            @php
                $assignmentContext = $device->effectiveAssignmentContext();
                $assignment = $assignmentContext['assignment'];
                $staff = $assignmentContext['staff'];
                $deploymentOffice = $device->deployedOffice ?: $device->parentProperty?->deployedOffice;
                $deploymentLocation = $device->deployedLocation
                    ?: $deploymentOffice?->location
                    ?: $device->parentProperty?->deployedLocation
                    ?: $device->parentProperty?->deployedOffice?->location;
                $office = $assignmentContext['office'] ?: $deploymentOffice;
                $college = $assignmentContext['location'] ?: $deploymentLocation ?: $office?->college;
                $staffName = $staff
                    ? trim(($staff->last_name ?? '') . ', ' . ($staff->first_name ?? ''))
                    : ($assignment?->location ? 'Location assignment' : '-');
                $effectiveUnitPrice = $device->effectiveUnitPrice();
                $effectiveMaintenanceDate = $device->effectiveLastMaintenanceDate();
            @endphp
            <tr>
                <td>{{ $device->type?->name ?? '-' }}</td>
                <td>{{ $device->property_number ?? '-' }}</td>
                <td>{{ $device->serial_number ?: '-' }}</td>
                <td>{{ trim(($device->brand ?? '') . ' ' . ($device->model ?? '')) ?: '-' }}</td>
                <td>{{ $device->status ?: '-' }}</td>
                <td>{{ $device->condition ?: '-' }}</td>
                <td>{{ $effectiveMaintenanceDate ? 'Maintained (' . $effectiveMaintenanceDate->format('M d, Y') . ')' : 'Not maintained' }}</td>
                <td>{{ $effectiveUnitPrice !== null && $effectiveUnitPrice !== '' ? number_format((float) $effectiveUnitPrice, 2) : '-' }}</td>
                <td>{{ $college?->name ?? '-' }}</td>
                <td>{{ $office?->name ?? '-' }}</td>
                <td>{{ $staffName ?: '-' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
