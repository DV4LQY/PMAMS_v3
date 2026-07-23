@extends('admin.layouts.app')

@section('title', 'Checklist Preview')
@section('page_title', 'Checklist Preview')
@section('breadcrumbs')
    <a href="{{ route('admin.reports.checkedEquipment') }}" class="hover:text-blue-600">Checked Equipment</a><span>/</span><span class="font-medium">Preview</span>
@endsection

@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3"><div><h1 class="text-xl font-semibold">Checklist report preview</h1><p class="text-sm text-gray-500">{{ $record->device->property_number }} · {{ $record->maintenance_date?->format('M d, Y') }}</p></div><div class="flex gap-2"><button type="button" onclick="window.print()" class="rounded-lg bg-gray-700 px-4 py-2 text-sm font-semibold text-white">Print preview</button><a data-no-spa="true" target="_blank" href="{{ route('admin.reports.checkedEquipment.pdf', $record) }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Open PDF</a></div></div>
    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3"><div><div class="text-xs uppercase text-gray-500">Property</div><div class="font-semibold">{{ $record->device->property_number }}</div></div><div><div class="text-xs uppercase text-gray-500">Equipment</div><div class="font-semibold">{{ $record->device->type?->name }}</div></div><div><div class="text-xs uppercase text-gray-500">Checked by</div><div class="font-semibold">{{ $record->checkedBy?->name ?? '-' }}</div></div></div>
        <div class="mt-6 overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-gray-100 dark:bg-gray-900/60"><tr><th class="px-3 py-2 text-left">Section</th><th class="px-3 py-2 text-left">Checklist item</th><th class="px-3 py-2 text-center">Result</th><th class="px-3 py-2 text-left">Disposition</th></tr></thead><tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            @php($hardware = data_get($record->checklist_data, 'hardware', [])) @foreach($checklistItems as $key => $item)<tr><td class="px-3 py-2">{{ $item['group'] }}</td><td class="px-3 py-2">{{ $item['label'] }}</td><td class="px-3 py-2 text-center font-semibold">{{ $hardware[$key] ?? '-' }}</td><td class="px-3 py-2">{{ data_get($record->checklist_data, "disposition.$key") ? ucfirst(str_replace('_', ' ', data_get($record->checklist_data, "disposition.$key"))) : '-' }}</td></tr>@endforeach
            @foreach($softwareItems as $key => $label)<tr><td class="px-3 py-2">Software</td><td class="px-3 py-2">{{ $label }}</td><td class="px-3 py-2 text-center font-semibold">{{ data_get($record->checklist_data, "software.$key") === 'check' ? 'Checked' : '-' }}</td><td class="px-3 py-2">-</td></tr>@endforeach
        </tbody></table></div>
        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2"><div><h2 class="font-semibold">Remarks</h2><p class="mt-1 whitespace-pre-line text-sm">{{ $record->remarks ?: '-' }}</p></div><div><h2 class="font-semibold">Corrective action</h2><p class="mt-1 whitespace-pre-line text-sm">{{ $record->corrective_action ?: '-' }}</p></div></div>
        @if($record->photos->isNotEmpty())<div class="mt-6"><h2 class="font-semibold">Maintenance photo</h2><img src="{{ asset('storage/' . $record->photos->first()->photo_path) }}" class="mt-2 max-h-80 rounded-lg object-contain" alt="Maintenance photo"></div>@endif
    </section>
</div>
<style>@media print { aside, header, nav, button, a { display:none !important } body { background:#fff !important } .dark\:bg-gray-800 { background:#fff !important } }</style>
@endsection
