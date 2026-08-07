@extends('admin.layouts.app')

@section('title', 'Quality Objective Monitoring')
@section('page_title', 'Quality Objective Monitoring')

@section('breadcrumbs')
    <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Dashboard</a>
    <span>/</span>
    <a href="{{ route('admin.reports.index') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Reports</a>
    <span>/</span>
    <span class="font-medium text-gray-800 dark:text-gray-200">Quality Objective Monitoring</span>
@endsection

@section('content')
<div class="space-y-6">
    <section class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Quality Objective Monitoring</h1>
            <p class="mt-1 max-w-4xl text-sm text-gray-500 dark:text-gray-400">
                QO worksheet values are linked to published PM Plans and saved checklist progress. Transfer counts are shown separately and do not change the actual maintained count.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.reports.maintenanceQuality.export', request()->query()) }}" data-no-spa="true" download class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-400">
                Export Excel
            </a>
            <a href="{{ route('admin.reports.maintenanceQuality.pdf', request()->query()) }}" data-no-spa="true" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400">
                Open PDF
            </a>
        </div>
    </section>

    <form method="GET" class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:flex-row lg:flex-nowrap lg:items-end">
        <label class="min-w-0 text-sm font-medium text-gray-700 dark:text-gray-200 lg:flex-1">
            Year
            <input type="number" name="year" min="2000" max="2100" value="{{ $filters['year'] }}" class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:ring-blue-900">
        </label>
        <label class="min-w-0 text-sm font-medium text-gray-700 dark:text-gray-200 lg:flex-[1.1]">
            Semester
            <select name="semester" class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:ring-blue-900">
                <option value="1" @selected($filters['semester'] === 1)>1st Semi-Annually (Jan-Jun)</option>
                <option value="2" @selected($filters['semester'] === 2)>2nd Semi-Annually (Jul-Dec)</option>
            </select>
        </label>
        <label class="min-w-0 text-sm font-medium text-gray-700 dark:text-gray-200 lg:flex-[1.1]">
            Location
            <select name="location_id" class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:ring-blue-900"
                    onchange="const officeField = this.form.querySelector('[name=office_id]'); if (officeField) officeField.value = ''; this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
                <option value="">All locations</option>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected($filters['location_id'] === $location->id)>{{ $location->code ? $location->code . ' - ' : '' }}{{ $location->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="min-w-0 text-sm font-medium text-gray-700 dark:text-gray-200 lg:flex-[1.1]">
            Office
            @if($filters['location_id'])
                <select name="office_id" class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:focus:ring-blue-900"
                        onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
                    <option value="">All offices in selected location</option>
                    @foreach($offices as $office)
                        <option value="{{ $office->id }}" @selected($filters['office_id'] === $office->id)>{{ $office->name }}</option>
                    @endforeach
                </select>
            @else
                <div class="mt-1 flex min-h-[48px] items-center overflow-hidden rounded-xl border border-dashed border-gray-300 px-3 py-3 text-sm text-gray-500 whitespace-nowrap dark:border-gray-600 dark:text-gray-400" title="Select a location to filter by office">
                    Select a location to filter by office
                </div>
            @endif
        </label>
        <div class="flex shrink-0 items-end gap-2">
            <button type="submit" class="inline-flex  items-center justify-center rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400">Filter</button>
            <a href="{{ route('admin.reports.maintenanceQuality') }}" class="inline-flex items-center justify-center rounded-xl bg-gray-200 px-4 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600">Reset</a>
        </div>
    </form>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
        @foreach([
            ['label' => 'Target', 'value' => $summary['target'], 'class' => 'text-blue-600 dark:text-blue-400'],
            ['label' => 'Condemned (Computer Set only)', 'value' => $summary['condemned'], 'class' => 'text-rose-600 dark:text-rose-400'],
            ['label' => 'Unserviceable', 'value' => $summary['unserviceable'], 'class' => 'text-orange-600 dark:text-orange-400'],
            ['label' => 'Additional', 'value' => $summary['additional'], 'class' => 'text-cyan-600 dark:text-cyan-400'],
            ['label' => 'Actual maintained', 'value' => $summary['actual'], 'class' => 'text-emerald-600 dark:text-emerald-400'],
            ['label' => 'Transferred IN', 'value' => $summary['transferred_in'], 'class' => 'text-violet-600 dark:text-violet-400'],
            ['label' => 'Transferred OUT', 'value' => $summary['transferred_out'], 'class' => 'text-amber-600 dark:text-amber-400'],
            ['label' => 'Accomplishment', 'value' => $summary['rate'] === null ? 'N/A' : number_format($summary['rate'] * 100, 2) . '%', 'class' => $summary['status'] === 'Complied' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'],
        ] as $card)
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="text-[11px] font-semibold uppercase tracking-wide {{ $card['class'] }}">{{ $card['label'] }}</div>
                <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $card['value'] }}</div>
            </div>
        @endforeach
    </div>

    @if(count($summary['warnings']))
        <section class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-200">
            <div class="font-semibold">Data review recommended</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach($summary['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach
            </ul>
        </section>
    @endif

    @php
        $chartPoints = $chart['points'];
        $chartLeft = 70;
        $chartRight = 900;
        $chartTop = 24;
        $chartBottom = 222;
        $chartX = [180, 790];
        $actualY = array_map(fn ($point) => $chartBottom - ($point['actual'] * ($chartBottom - $chartTop)), $chartPoints);
        $targetY = array_map(fn ($point) => $chartBottom - ($point['target'] * ($chartBottom - $chartTop)), $chartPoints);
    @endphp
    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="mb-3">
            <h2 class="font-semibold text-gray-900 dark:text-white">Performance monitoring</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Actual accomplishment versus the controlled-form 100% target line.</p>
        </div>
        <div class="overflow-x-auto">
            <svg viewBox="0 0 960 300" class="min-w-[720px] w-full" role="img" aria-label="Actual versus target quality-objective accomplishment">
                <title>Actual versus target quality-objective accomplishment</title>
                <rect x="{{ $chartLeft }}" y="{{ $chartTop }}" width="{{ $chartRight - $chartLeft }}" height="{{ $chartBottom - $chartTop }}" fill="currentColor" class="text-white dark:text-gray-800" stroke="#e5e7eb" />
                @for($tick = 0; $tick <= 10; $tick++)
                    @php($tickY = $chartBottom - ($tick * (($chartBottom - $chartTop) / 10)))
                    <line x1="{{ $chartLeft }}" y1="{{ $tickY }}" x2="{{ $chartRight }}" y2="{{ $tickY }}" stroke="#d1d5db" stroke-width="1" />
                    <text x="{{ $chartLeft - 10 }}" y="{{ $tickY + 4 }}" text-anchor="end" font-size="11" fill="currentColor" class="text-gray-500 dark:text-gray-400">{{ $tick * 10 }}%</text>
                @endfor
                <polyline points="{{ $chartX[0] }},{{ $actualY[0] }} {{ $chartX[1] }},{{ $actualY[1] }}" fill="none" stroke="#2563eb" stroke-width="3" />
                <polyline points="{{ $chartX[0] }},{{ $targetY[0] }} {{ $chartX[1] }},{{ $targetY[1] }}" fill="none" stroke="#dc2626" stroke-width="3" />
                @foreach($chartPoints as $index => $point)
                    <circle cx="{{ $chartX[$index] }}" cy="{{ $actualY[$index] }}" r="4" fill="#2563eb" />
                    <circle cx="{{ $chartX[$index] }}" cy="{{ $targetY[$index] }}" r="4" fill="#dc2626" />
                    <text x="{{ $chartX[$index] }}" y="{{ $chartBottom + 25 }}" text-anchor="middle" font-size="11" fill="currentColor" class="text-gray-600 dark:text-gray-300">{{ $point['label'] }}</text>
                    <text x="{{ $chartX[$index] }}" y="{{ max($chartTop + 12, $actualY[$index] - 9) }}" text-anchor="middle" font-size="11" fill="#2563eb">{{ $point['actual_label'] }}</text>
                @endforeach
            </svg>
        </div>
        <div class="mt-2 flex flex-wrap gap-4 text-xs text-gray-500 dark:text-gray-400">
            <span><span class="mr-1 inline-block h-2.5 w-2.5 rounded-full bg-blue-600"></span>Actual</span>
            <span><span class="mr-1 inline-block h-2.5 w-2.5 rounded-full bg-red-600"></span>Target</span>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
            <h2 class="font-semibold text-gray-900 dark:text-white">{{ $period['label'] }} — QO worksheet</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">The yellow instruction row in the reference workbook is intentionally not displayed or exported.</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Target uses the previous PM Plan cycle. Actual maintained uses the current cycle, adjusted for transfers in/out and unserviceable equipment; condemned equipment is excluded.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-[1760px] w-full border-collapse text-sm">
                <thead class="bg-sky-50 text-xs font-semibold text-gray-700 dark:bg-gray-900/70 dark:text-gray-200">
                    <tr>
                        <th class="px-3 py-4 text-left">Office / Unit</th>
                        <th class="px-3 py-4 text-center">Target for maintenance</th>
                        <th class="px-3 py-4 text-center">Condemned</th>
                        <th class="px-3 py-4 text-center">Unserviceable</th>
                        <th class="px-3 py-4 text-center">Additional (new)</th>
                        <th class="px-3 py-4 text-center">Actual maintained</th>
                        <th class="px-3 py-4 text-center">Transferred IN</th>
                        <th class="px-3 py-4 text-center">Transferred OUT</th>
                        <th class="px-3 py-4 text-left">Date maintenance conducted</th>
                        <th class="px-3 py-4 text-left">Remarks</th>
                        <th class="px-3 py-4 text-center">Complied</th>
                        <th class="px-3 py-4 text-center">Not Complied</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($rows as $row)
                        <tr class="align-top text-gray-700 dark:text-gray-200">
                            <td class="px-3 py-4 font-medium text-gray-900 dark:text-white">{{ $row['office'] }}</td>
                            <td class="px-3 py-4 text-center">{{ $row['target'] }}</td>
                            <td class="px-3 py-4 text-center">{{ $row['condemned'] }}</td>
                            <td class="px-3 py-4 text-center">{{ $row['unserviceable'] }}</td>
                            <td class="px-3 py-4 text-center">{{ $row['additional'] }}</td>
                            <td class="px-3 py-4 text-center font-semibold">{{ $row['actual'] }}</td>
                            <td class="px-3 py-4 text-center">{{ $row['transferred_in'] }}</td>
                            <td class="px-3 py-4 text-center">{{ $row['transferred_out'] }}</td>
                            <td class="px-3 py-4">{{ $row['dates'] ?: '—' }}</td>
                            <td class="px-3 py-4">
                                @if($row['remarks'])<div>{{ $row['remarks'] }}</div>@endif
                                @if(count($row['warnings']))
                                    <ul class="mt-1 list-disc pl-4 text-xs text-amber-700 dark:text-amber-300">
                                        @foreach($row['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach
                                    </ul>
                                @endif
                                @if(!$row['remarks'] && !count($row['warnings']))<span class="text-gray-400">—</span>@endif
                            </td>
                            <td class="px-3 py-4 text-center text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ $row['status'] === 'Complied' ? '✓' : '' }}</td>
                            <td class="px-3 py-4 text-center text-xl font-bold text-rose-600 dark:text-rose-400">{{ $row['status'] === 'Not Complied' ? 'X' : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">No published PM Plan schedules matched the selected period and filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex flex-col gap-3 border-t border-gray-200 bg-gray-50 px-5 py-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-100 sm:flex-row sm:items-center sm:justify-between">
            <span>
                @if($rows->total() > 0)
                    Showing {{ $rows->firstItem() }}–{{ $rows->lastItem() }} of {{ $rows->total() }} schedules
                @else
                    No schedules found
                @endif
            </span>
            @if($rows->hasPages())
                {{ $rows->onEachSide(1)->links() }}
            @else
                <span class="text-gray-500 dark:text-gray-400">Page 1 of 1</span>
            @endif
        </div>
        <div class="grid gap-2 border-t border-gray-200 bg-gray-50 px-5 py-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-100 sm:grid-cols-3">
            <div><span class="font-semibold">Equipment checked:</span> {{ $summary['actual'] }}</div>
            <div><span class="font-semibold">Equipment target:</span> {{ $summary['target'] }}</div>
            <div><span class="font-semibold">Percentage of accomplishment:</span> {{ $summary['rate'] === null ? 'N/A' : number_format($summary['rate'] * 100, 2) . '%' }}</div>
        </div>
    </section>
</div>
@endsection
