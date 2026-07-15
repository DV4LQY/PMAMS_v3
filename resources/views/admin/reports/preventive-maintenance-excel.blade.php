<table>
    <colgroup>
        <col style="width: 14px;"> {{-- A Date --}}
        <col style="width: 8px;">  {{-- B No --}}
        <col style="width: 22px;"> {{-- C Office --}}

        {{-- Desktop D-P --}}
        <col style="width: 18px;">
        <col style="width: 14px;">
        <col style="width: 22px;">
        <col style="width: 14px;">
        <col style="width: 24px;">
        <col style="width: 20px;">
        <col style="width: 20px;"> {{-- Computer Name --}}
        <col style="width: 14px;">
        <col style="width: 20px;">
        <col style="width: 16px;">
        <col style="width: 16px;">
        <col style="width: 16px;">
        <col style="width: 16px;">

        {{-- Monitor Q-W --}}
        <col style="width: 18px;">
        <col style="width: 22px;">
        <col style="width: 14px;">
        <col style="width: 24px;">
        <col style="width: 20px;">
        <col style="width: 14px;">
        <col style="width: 16px;">

        {{-- Printer X-AD --}}
        <col style="width: 18px;">
        <col style="width: 22px;">
        <col style="width: 14px;">
        <col style="width: 24px;">
        <col style="width: 20px;">
        <col style="width: 14px;">
        <col style="width: 16px;">

        {{-- UPS AE-AK --}}
        <col style="width: 18px;">
        <col style="width: 22px;">
        <col style="width: 14px;">
        <col style="width: 24px;">
        <col style="width: 20px;">
        <col style="width: 14px;">
        <col style="width: 16px;">

        {{-- AVR AL-AR --}}
        <col style="width: 18px;">
        <col style="width: 22px;">
        <col style="width: 14px;">
        <col style="width: 24px;">
        <col style="width: 20px;">
        <col style="width: 14px;">
        <col style="width: 16px;">

        {{-- AS Remarks --}}
        <col style="width: 34px;">
    </colgroup>

    <tr>
        <td colspan="45" style="font-weight: bold; font-size: 14px; text-align: center;">
            DESKTOP COMPUTERS AND PERIPHERALS COVERED DURING PREVENTIVE MAINTENANCE
        </td>
    </tr>

    <tr>
        <td colspan="45" style="font-weight: bold; font-size: 12px; text-align: center;">
            Exported Date: {{ $reportDate->format('F d, Y') }}
        </td>
    </tr>

    <tr>
        <td colspan="45"></td>
    </tr>

    <thead>
        <tr>
            <th rowspan="2" style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center; vertical-align: middle;">
                Date
            </th>

            <th rowspan="2" style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center; vertical-align: middle;">
                No.
            </th>

            <th rowspan="2" style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center; vertical-align: middle;">
                Office
            </th>

            <th colspan="13" style="border: 1px solid #000000; background-color: #b4c6e7; font-weight: bold; text-align: center;">
                Desktop
            </th>

            <th colspan="7" style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">
                Monitor
            </th>

            <th colspan="7" style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">
                Printer
            </th>

            <th colspan="7" style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">
                UPS
            </th>

            <th colspan="7" style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">
                AVR
            </th>

            <th rowspan="2" style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center; vertical-align: middle;">
                Remarks
            </th>
        </tr>

        <tr>
            {{-- Desktop --}}
            <th style="border: 1px solid #000000; background-color: #b4c6e7; font-weight: bold; text-align: center;">Name / Model</th>
            <th style="border: 1px solid #000000; background-color: #b4c6e7; font-weight: bold; text-align: center;">Brand</th>
            <th style="border: 1px solid #000000; background-color: #ffff00; font-weight: bold; text-align: center;">Issued To</th>
            <th style="border: 1px solid #000000; background-color: #b4c6e7; font-weight: bold; text-align: center;">Acquired</th>
            <th style="border: 1px solid #000000; background-color: #b4c6e7; font-weight: bold; text-align: center;">Property #</th>
            <th style="border: 1px solid #000000; background-color: #b4c6e7; font-weight: bold; text-align: center;">Serial #</th>
            <th style="border: 1px solid #000000; background-color: #b4c6e7; font-weight: bold; text-align: center;">Computer Name</th>
            <th style="border: 1px solid #000000; background-color: #b4c6e7; font-weight: bold; text-align: center;">Unit Price</th>
            <th style="border: 1px solid #000000; background-color: #b4c6e7; font-weight: bold; text-align: center;">MAC</th>
            <th style="border: 1px solid #000000; background-color: #b4c6e7; font-weight: bold; text-align: center;">OS Version</th>
            <th style="border: 1px solid #000000; background-color: #b4c6e7; font-weight: bold; text-align: center;">OS License</th>
            <th style="border: 1px solid #000000; background-color: #b4c6e7; font-weight: bold; text-align: center;">MS Office Version</th>
            <th style="border: 1px solid #000000; background-color: #b4c6e7; font-weight: bold; text-align: center;">MS Office License</th>
            <th style="border: 1px solid #000000; background-color: #b4c6e7; font-weight: bold; text-align: center;">Storage</th>
            <th style="border: 1px solid #000000; background-color: #b4c6e7; font-weight: bold; text-align: center;">Form Factor</th>
            <th style="border: 1px solid #000000; background-color: #b4c6e7; font-weight: bold; text-align: center;">Condition</th>

            {{-- Monitor --}}
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Brand / Model</th>
            <th style="border: 1px solid #000000; background-color: #ffff00; font-weight: bold; text-align: center;">Issued To</th>
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Acquired</th>
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Property #</th>
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Serial #</th>
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Unit Price</th>
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Condition</th>

            {{-- Printer --}}
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Brand / Model</th>
            <th style="border: 1px solid #000000; background-color: #ffff00; font-weight: bold; text-align: center;">Issued To</th>
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Acquired</th>
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Property #</th>
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Serial #</th>
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Unit Price</th>
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Condition</th>

            {{-- UPS --}}
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Brand / Model</th>
            <th style="border: 1px solid #000000; background-color: #ffff00; font-weight: bold; text-align: center;">Issued To</th>
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Acquired</th>
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Property #</th>
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Serial #</th>
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Unit Price</th>
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Condition</th>

            {{-- AVR --}}
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Brand / Model</th>
            <th style="border: 1px solid #000000; background-color: #ffff00; font-weight: bold; text-align: center;">Issued To</th>
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Acquired</th>
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Property #</th>
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Serial #</th>
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Unit Price</th>
            <th style="border: 1px solid #000000; background-color: #d9e6f2; font-weight: bold; text-align: center;">Condition</th>
        </tr>
    </thead>

    <tbody>
        @php
            $staffName = function ($staff) {
                if (! $staff) {
                    return '';
                }

                return trim(($staff->last_name ?? '') . ', ' . ($staff->first_name ?? ''));
            };

            $dateValue = function ($device) {
                return $device?->date_acquired ? $device->date_acquired->format('m/d/Y') : '';
            };

            $priceValue = function ($device) {
                return $device?->unit_price ? (float) $device->unit_price : '';
            };

            $brandModel = function ($device) {
                if (! $device) {
                    return '';
                }

                return trim(($device->brand ?? '') . ' ' . ($device->model ?? ''));
            };

            $conditionValue = function ($device) {
                if (! $device) {
                    return '';
                }

                return $device->condition ? ucfirst($device->condition) : 'Serviceable';
            };
        @endphp

        @forelse($groupedByOffice as $officeName => $rows)
            @php
                $officeCounter = 1;
                $officeTotal = count($rows);
            @endphp

            @foreach($rows as $row)
                @php
                    $desktopAssignment = $row['desktop'];
                    $monitorAssignment = $row['monitor'];
                    $printerAssignment = $row['printer'];
                    $upsAssignment = $row['ups'];
                    $avrAssignment = $row['avr'];

                    $desktop = $desktopAssignment?->device;
                    $monitor = $monitorAssignment?->device;
                    $printer = $printerAssignment?->device;
                    $ups = $upsAssignment?->device;
                    $avr = $avrAssignment?->device;

                    $staff = $row['staff'];
                    $issuedTo = $staffName($staff);
                @endphp

                <tr>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $reportDate->format('m/d/Y') }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $officeCounter++ }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $officeName }}</td>

                    {{-- Desktop --}}
                    <td style="border: 1px solid #000000; text-align: center;">{{ $desktop ? ($desktop->model ?: $desktop->brand ?: $desktop->property_number) : '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $desktop?->brand ?? '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center; background-color: #ffff00;">{{ $desktop ? $issuedTo : '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $dateValue($desktop) }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $desktop?->property_number ?? '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $desktop?->serial_number ?? '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $desktop?->computer_name ?? '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $priceValue($desktop) }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $desktop?->mac_address ?? '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $desktop?->device?->os_version ?? '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $desktop?->device?->os_license ?? '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $desktop?->device?->ms_office_version ?? '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $desktop?->device?->ms_office_license ?? '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ data_get($desktop?->specs, 'storage', '') }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ data_get($desktop?->specs, 'form_factor', '') }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $conditionValue($desktop) }}</td>

                    {{-- Monitor --}}
                    <td style="border: 1px solid #000000; text-align: center;">{{ $brandModel($monitor) }}</td>
                    <td style="border: 1px solid #000000; text-align: center; background-color: #ffff00;">{{ $monitor ? $issuedTo : '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $dateValue($monitor) }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $monitor?->property_number ?? '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $monitor?->serial_number ?? '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $priceValue($monitor) }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $conditionValue($monitor) }}</td>

                    {{-- Printer --}}
                    <td style="border: 1px solid #000000; text-align: center;">{{ $brandModel($printer) }}</td>
                    <td style="border: 1px solid #000000; text-align: center; background-color: #ffff00;">{{ $printer ? $issuedTo : '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $dateValue($printer) }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $printer?->property_number ?? '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $printer?->serial_number ?? '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $priceValue($printer) }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $conditionValue($printer) }}</td>

                    {{-- UPS --}}
                    <td style="border: 1px solid #000000; text-align: center;">{{ $brandModel($ups) }}</td>
                    <td style="border: 1px solid #000000; text-align: center; background-color: #ffff00;">{{ $ups ? $issuedTo : '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $dateValue($ups) }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $ups?->property_number ?? '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $ups?->serial_number ?? '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $priceValue($ups) }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $conditionValue($ups) }}</td>

                    {{-- AVR --}}
                    <td style="border: 1px solid #000000; text-align: center;">{{ $brandModel($avr) }}</td>
                    <td style="border: 1px solid #000000; text-align: center; background-color: #ffff00;">{{ $avr ? $issuedTo : '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $dateValue($avr) }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $avr?->property_number ?? '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $avr?->serial_number ?? '' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $priceValue($avr) }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $conditionValue($avr) }}</td>

                    {{-- Remarks --}}
                    <td style="border: 1px solid #000000; text-align: center;"></td>
                </tr>
            @endforeach

            {{-- Office Total Row --}}
            <tr>
                <td style="border: 1px solid #000000; background-color: #92d050; text-align: center; font-weight: bold;"></td>

                <td style="border: 1px solid #000000; background-color: #92d050; text-align: center; font-weight: bold;">
                    Total
                </td>

                <td style="border: 1px solid #000000; background-color: #92d050; text-align: center; font-weight: bold;">
                    {{ $officeTotal }}
                </td>

                @for($i = 4; $i <= 45; $i++)
                    <td style="border: 1px solid #000000; background-color: #92d050;"></td>
                @endfor
            </tr>
        @empty
            <tr>
                <td colspan="45" style="border: 1px solid #000000; text-align: center;">
                    No records found.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
