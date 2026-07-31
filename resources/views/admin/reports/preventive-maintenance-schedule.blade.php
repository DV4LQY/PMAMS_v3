@extends('admin.layouts.app')

@section('title', 'Preventive Maintenance Schedule Monitoring')
@section('page_title', 'Preventive Maintenance Schedule Monitoring')

@section('breadcrumbs')
    <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Dashboard</a>
    <span>/</span>
    <a href="{{ route('admin.reports.index') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Reports</a>
    <span>/</span>
    <span class="font-medium text-gray-800 dark:text-gray-200">PM Schedule Monitoring</span>
@endsection

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 print:hidden sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Preventive Maintenance Schedule Monitoring</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">The Office column uses registered locations and offices. Actual dates are calculated from saved equipment checklists.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.reports.maintenanceSchedule.pdf', request()->query()) }}" class="rounded-xl bg-green-600 px-4 py-3 text-sm font-semibold text-white hover:bg-green-700">Print</a>
        </div>
    </div>

    <form method="GET" class="grid gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 print:hidden md:grid-cols-4">
        <select name="location_id" class="rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            <option value="">All locations</option>
            @foreach($locations as $location)
                <option value="{{ $location->id }}" @selected($locationId === $location->id)>{{ $location->code ? $location->code . ' - ' : '' }}{{ $location->name }}</option>
            @endforeach
        </select>
        <select name="office_id" class="rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            <option value="">All offices</option>
            @foreach($locations as $location)
                <optgroup label="{{ $location->code ? $location->code . ' - ' : '' }}{{ $location->name }}">
                    @foreach($location->offices as $office)
                        <option value="{{ $office->id }}" @selected($officeId === $office->id)>{{ $office->name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        <input type="month" name="month_from" value="{{ $monthFrom }}" class="rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white" aria-label="Schedule month from">
        <div class="flex gap-2">
            <input type="month" name="month_to" value="{{ $monthTo }}" class="min-w-0 flex-1 rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white" aria-label="Schedule month to">
            <button class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700">Filter</button>
        </div>
    </form>

    <section class="report-sheet flex min-h-[8.5in] flex-col rounded-2xl border border-gray-200 bg-white p-5 text-black shadow-sm dark:border-gray-700 dark:bg-white print:min-h-0 print:border-0 print:p-0 print:shadow-none">
        <div class="mb-4">
            <table class="w-full border-collapse text-black"><tr>
                <td class="w-[52px] border-0 p-0 align-top">
                    <img src="{{ asset('images/catsu-logo.png') }}" alt="Catanduanes State University" class="h-[50px] w-[50px] shrink-0 object-contain">
                </td>
                <td class="border-0 p-0 pl-2 align-top text-left text-[12px] leading-[1.15]">
                    <div class="text-left text-[12px] leading-tight">
                        <div class="text-[12px] italic">Republic of the Philippines</div>
                        <div class="text-[10px] font-bold uppercase">Catanduanes State University</div>
                        <div class="text-[12px] italic">Virac, Catanduanes</div>
                    </div>
                </td>
                <td class="w-[145px] border-0 p-0 text-right align-top">
                <img src="{{ asset('images/iso-9001-2015.jpg') }}" alt="TÜV Rheinland ISO 9001:2015 certified" class="h-[45px] w-[132px] shrink-0 object-contain">
                </td>
                </tr></table>
            <div class="mt-1 border-b-[1.5px] border-blue-800"></div>
            <div class="mt-1 text-left text-[12px] font-bold italic">Information and Communications Technology (ICT) Unit</div>
            <h2 class="mt-2 text-center text-[14px] font-bold">Preventive Maintenance Schedule Monitoring</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="report w-full min-w-[980px] table-fixed border-collapse text-[12px] text-black print:min-w-0">
                <colgroup>
                    <col style="width:25%">
                    <col style="width:16%">
                    <col style="width:16%">
                    <col style="width:13%">
                    <col style="width:15%">
                    <col style="width:20%">
                </colgroup>
                <thead>
                    <tr class="bg-gray-100 print:bg-gray-100">
                        <th class="border border-gray-700 px-[5px] py-[5px] text-center font-bold">Office</th>
                        <th class="border border-gray-700 px-[5px] py-[5px] text-center font-bold">Schedule of Maintenance</th>
                        <th class="border border-gray-700 px-[5px] py-[5px] text-center font-bold">Actual Date of Maintenance</th>
                        <th class="border border-gray-700 px-[5px] py-[5px] text-center font-bold">Person/s In Charge</th>
                        <th class="border border-gray-700 px-[5px] py-[5px] text-center font-bold">Signature</th>
                        <th class="border border-gray-700 px-[5px] py-[5px] text-center font-bold">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        @php($completion = $row['completion'])
                        @php($hasDigitalSignature = filled($completion?->signature_data))
                        <tr>
                            <td class="border border-gray-700 px-[5px] py-[5px] align-top">{{ $row['office'] }}</td>
                            <td class="border border-gray-700 px-[5px] py-[5px] align-top">
                                @if($row['override_schedule'])
                                    <div><strong class="text-[10px]">Scheduled:</strong> {{ $row['original_schedule'] }}</div>
                                    <div><strong class="text-[10px]">Re-Scheduled on:</strong> {{ $row['override_schedule'] }}</div>
                                @else
                                    <div>{{ $row['original_schedule'] }}</div>
                                @endif
                            </td>
                            <td class="border border-gray-700 px-[5px] py-[5px] align-top">{{ $row['actual_date'] ?: '' }}</td>
                            <td class="border border-gray-700 px-[5px] py-[5px] align-top">{{ $row['person_in_charge'] ?: '' }}</td>
                            <td class="signature-cell border border-gray-700 px-[5px] py-[5px] text-center align-top {{ $hasDigitalSignature ? 'signature-digital' : 'signature-text' }}">
                                @if($hasDigitalSignature)
                                    <img src="{{ $completion->signature_data }}" alt="Digital signature" class="sig-image">
                                @endif
                                <div>{{ $completion?->signer_name ?: ($hasDigitalSignature ? '' : $completion?->signature) }}</div>
                            </td>
                            <td class="remarks-cell border border-gray-700 px-[5px] py-[5px] align-top">
                                {{ $completion?->remarks ?: '' }}
                                @if($row['override_schedule'] && filled($row['override_reason']))
                                    <div class="text-[10px]">Rescheduled due to: {{ $row['override_reason'] }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="border border-gray-700 px-3 py-8 text-center">No schedule records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="approval-block grid grid-cols-2 text-sm">
            <div></div>
            <div class="text-left" style="padding-left:100px">
                <div>Approved by:</div>
                <div class="mt-7 font-semibold text-black">{{ $unitHead?->name ?: 'JAY-R R. REDITA,MIT' }}</div>
                <div class="text-xs">{{ $unitHead?->roleLabel() ?: 'Information Technology Officer I' }}</div>
            </div>
        </div>
        <div class="document-footer mt-auto flex items-center justify-between border-t-2 border-blue-300 pt-1 text-[11px] italic text-black">
            <span>CatSU-F-ICTU-07</span><span>Rev: 0</span><span>Effectivity Date: June 05, 2025</span>
        </div>
    </section>
</div>

<style>
    .report-sheet { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; }
    .report-sheet .report { margin-top: 14px; }
    .report-sheet .report th,
    .report-sheet .report td { border-color: #000; padding: 5px; vertical-align: top; overflow-wrap: anywhere; }
    .report-sheet .report th { background: #e5e7eb; text-align: center; }
    .report-sheet .report td:nth-child(4) { text-align: center; }
    .report-sheet .signature-cell { min-height: 72px; padding: 4px 5px 5px !important; line-height: 1.15; vertical-align: top; }
    .report-sheet .signature-cell.signature-text { padding-top: 22px !important; }
    .report-sheet .sig-image { display: block; margin: 0 auto 2px; max-height: 30px; max-width: 100px; object-fit: contain; }
    .report-sheet .remarks-cell { padding-top: 2px !important; }
    .report-sheet .approval-block { margin-top: 0.5in; }
    /* The admin layout's dark-mode safety layer intentionally recolors every
       .bg-white surface. This controlled-form preview must stay paper-white
       so it remains identical to the PDF source in either theme. */
    html.dark .report-sheet,
    html.dark .report-sheet.bg-white { background-color: #fff !important; color: #000 !important; }
    html.dark .report-sheet .report { background-color: #fff !important; color: #000 !important; }
    html.dark .report-sheet .report thead,
    html.dark .report-sheet .report th { background-color: #e5e7eb !important; color: #000 !important; }
    html.dark .report-sheet .report td { background-color: #fff !important; color: #000 !important; }
    @media print {
        @page { size: 13in 8.5in; margin: 12mm; }
        body { background: #fff !important; }
        .report-sheet { min-height: 0; border: 0 !important; font-size: 12px; }
    }
</style>
@endsection
