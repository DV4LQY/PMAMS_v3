@extends('admin.layouts.app')

@section('title', 'Maintenance Attention')
@section('page_title', 'Maintenance Attention')
@section('breadcrumbs')
    <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-600">Dashboard</a><span>/</span>
    <span class="font-medium">Maintenance Attention</span>
@endsection

@section('content')
<div class="space-y-6">
    <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/20">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-amber-950 dark:text-amber-100">Maintenance attention</h1>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-amber-900/80 dark:text-amber-200/80">
                    Local, explainable recommendations for equipment that may need attention before the next preventive-maintenance cycle.
                    The score is advisory only; condemned equipment is excluded and existing review workflows remain unchanged.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="rounded-full bg-amber-200 px-3 py-1 font-semibold text-amber-950 dark:bg-amber-900/70 dark:text-amber-100">
                    @if($loaded) {{ number_format($reviewCount) }} need review @else Not loaded @endif
                </span>
                <span class="rounded-full bg-white/70 px-3 py-1 text-amber-900 dark:bg-gray-900/50 dark:text-amber-200">
                    @if($loaded) {{ number_format($recommendations->total()) }} equipment scored @else Apply a filter to load @endif
                </span>
            </div>
        </div>
    </section>

    <form method="GET" action="{{ route('admin.maintenance-attention.index') }}" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3 md:items-end">
            <div>
                <label for="maintenance-attention-location" class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Location</label>
                <select id="maintenance-attention-location" name="location" onchange="this.form.requestSubmit()" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">All locations</option>
                    @foreach($locations as $availableLocation)
                        <option value="{{ $availableLocation }}" @selected($location === $availableLocation)>{{ $availableLocation }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="maintenance-attention-type" class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Attention filter</label>
                <select id="maintenance-attention-type" name="attention" onchange="this.form.requestSubmit()" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">All recommendations</option>
                    <option value="ram_upgrade" @selected($attention === 'ram_upgrade')>RAM upgrade attention</option>
                    <option value="cracked_license" @selected($attention === 'cracked_license')>Cracked OS / MS Office attention</option>
                    <option value="old_equipment" @selected($attention === 'old_equipment')>Old equipment (5+ years)</option>
                </select>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.maintenance-attention.index', ['reset' => 1]) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">Reset</a>
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Apply a filter or press <strong>Reset</strong> to load maintenance attention. RAM rules apply only when a supported Windows version and a readable memory value are recorded.</p>
    </form>

    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Equipment recommendations</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Higher scores indicate more signals for an earlier review.</p>
            </div>
            @if($recommendations->total() > 0)
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Showing {{ $recommendations->firstItem() }}–{{ $recommendations->lastItem() }} of {{ $recommendations->total() }}
                </span>
            @endif
        </div>

        @if(!$loaded)
            <div class="rounded-xl border border-dashed border-gray-300 px-4 py-12 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                Apply a filter or press <strong>Reset</strong> to load maintenance attention.
            </div>
        @elseif($recommendations->total() > 0)
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                @foreach($recommendations as $attention)
                    @php
                        $device = $attention['device'];
                        $priorityClasses = match ($attention['priority']) {
                            'Critical' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200',
                            'High' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-200',
                            'Medium' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-200',
                            default => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
                        };
                    @endphp
                    <article class="rounded-xl border border-gray-200 bg-gray-50 p-4 transition hover:border-amber-300 hover:shadow-sm dark:border-gray-700 dark:bg-gray-900/40 dark:hover:border-amber-800">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('admin.devices.index', ['q' => $device->property_number]) }}" class="break-words font-semibold text-blue-700 hover:underline dark:text-blue-300">
                                    {{ $device->property_number ?: 'Unnumbered equipment' }}
                                </a>
                                <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                    {{ $device->type?->name ?? 'Equipment' }} <span aria-hidden="true">·</span> {{ $attention['location'] }}
                                </p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $priorityClasses }}">
                                {{ $attention['priority'] }} · {{ $attention['score'] }}/100
                            </span>
                        </div>
                        <ul class="mt-3 space-y-1.5 text-sm text-gray-700 dark:text-gray-300">
                            @foreach($attention['reasons'] as $reason)
                                <li class="flex gap-2"><span class="text-amber-600 dark:text-amber-400" aria-hidden="true">•</span><span>{{ $reason }}</span></li>
                            @endforeach
                        </ul>
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 pt-3 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <span>Last maintenance: {{ $attention['last_maintenance']?->format('M d, Y') ?? 'Not recorded' }}</span>
                            <a href="{{ route('admin.devices.index', ['q' => $device->property_number]) }}" class="font-medium text-blue-700 hover:underline dark:text-blue-300">Review equipment</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="rounded-xl border border-dashed border-gray-300 px-4 py-12 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                No equipment is available for maintenance scoring yet.
            </div>
        @endif

        @if($recommendations->hasPages())
            <div class="mt-6 border-t border-gray-200 pt-4 dark:border-gray-700">
                {{ $recommendations->onEachSide(1)->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
