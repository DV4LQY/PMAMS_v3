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
            <a href="{{ route('admin.reports.maintenanceSchedule.pdf', request()->query()) }}" class="rounded-xl bg-red-600 px-4 py-3 text-sm font-semibold text-white hover:bg-red-700">Download PDF</a>
            <button type="button" onclick="window.print()" class="rounded-xl bg-gray-700 px-4 py-3 text-sm font-semibold text-white hover:bg-gray-800">Print</button>
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
        <input type="date" name="date_from" value="{{ $dateFrom }}" class="rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white" aria-label="Schedule date from">
        <div class="flex gap-2">
            <input type="date" name="date_to" value="{{ $dateTo }}" class="min-w-0 flex-1 rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white" aria-label="Schedule date to">
            <button class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700">Filter</button>
        </div>
    </form>

    <section class="report-sheet rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-white dark:text-black print:border-0 print:p-0 print:shadow-none">
        <div class="mb-5 text-center">
            <div class="text-sm font-semibold">Information and Communications Technology (ICT) Unit</div>
            <h2 class="mt-1 text-xl font-bold uppercase">Preventive Maintenance Schedule Monitoring</h2>
            <div class="mt-3 text-left text-sm">Date: {{ now()->format('m/d/Y') }}</div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] border-collapse text-xs text-black print:min-w-0">
                <thead>
                    <tr class="bg-gray-100 print:bg-gray-100">
                        <th class="border border-gray-700 px-2 py-2 text-left font-bold">Office</th>
                        <th class="border border-gray-700 px-2 py-2 text-left font-bold">Schedule of Maintenance</th>
                        <th class="border border-gray-700 px-2 py-2 text-left font-bold">Actual Date of Maintenance</th>
                        <th class="border border-gray-700 px-2 py-2 text-left font-bold">Person/s In Charge</th>
                        <th class="border border-gray-700 px-2 py-2 text-left font-bold">Signature</th>
                        <th class="border border-gray-700 px-2 py-2 text-left font-bold">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        @php($completion = $row['completion'])
                        <tr>
                            <td class="border border-gray-700 px-2 py-2 align-top">{{ $row['office'] }}</td>
                            <td class="border border-gray-700 px-2 py-2 align-top">
                                <div>Original: {{ $row['original_schedule'] }}</div>
                                @if($row['override_schedule'])
                                    <div class="mt-1 font-semibold">Override: {{ $row['override_schedule'] }}</div>
                                    <div class="mt-1 text-[10px]">Reason: {{ $row['override_reason'] }}</div>
                                @endif
                            </td>
                            <td class="border border-gray-700 px-2 py-2 align-top">{{ $row['actual_date'] ?: '' }}</td>
                            <td class="border border-gray-700 px-2 py-2 align-top">{{ $completion?->person_in_charge ?: '' }}</td>
                            <td class="border border-gray-700 px-2 py-2 align-top">{{ $completion?->signature ?: '' }}</td>
                            <td class="border border-gray-700 px-2 py-2 align-top">
                                {{ $completion?->remarks ?: ($row['is_complete'] ? 'All equipment checked; completion details pending.' : 'Pending maintenance') }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="border border-gray-700 px-3 py-8 text-center">No schedule records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-8 text-sm">
            <div>Approved by:</div>
            <div class="mt-8 inline-block min-w-[260px] border-b border-black text-center font-semibold">{{ $unitHead?->name ?: '____________________________' }}</div>
            <div class="min-w-[260px] text-center text-xs">{{ $unitHead?->roleLabel() ?: 'Information Technology Officer I' }}</div>
        </div>
    </section>
</div>

<style>
    @media print {
        @page { size: A4 landscape; margin: 12mm; }
        body { background: #fff !important; }
        .report-sheet { font-size: 10px; }
    }
</style>
@endsection
