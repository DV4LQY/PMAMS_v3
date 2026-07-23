@extends('admin.layouts.app')

@section('title', 'Checklist History Management')
@section('page_title', 'Checklist History Management')
@section('breadcrumbs')
    <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-600">Dashboard</a><span>/</span>
    <span class="font-medium">Checklist History Management</span>
@endsection

@section('content')
<div x-data="{ selected: [], pageIds: @js($records->pluck('id')->values()), dateFrom: @js($dateFrom), dateTo: @js($dateTo), allPageSelected() { return this.pageIds.length && this.pageIds.every(id => this.selected.includes(id)); }, togglePage(value) { this.selected = value ? [...this.pageIds] : []; }, canDelete() { return this.selected.length > 0 || this.dateFrom || this.dateTo; } }" class="space-y-6">
    @if(session('success'))<div class="rounded-xl border border-green-700/40 bg-green-900/20 px-4 py-3 text-sm text-green-300">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-700/40 bg-red-900/20 px-4 py-3 text-sm text-red-300">{{ $errors->first() }}</div>@endif

    <section class="rounded-2xl border border-gray-700 bg-gray-800 p-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div><h2 class="text-lg font-semibold text-white">Duplicate checklist window</h2><p class="mt-1 text-sm text-gray-400">A new checklist within this window prompts for verification, while every history record remains stored.</p></div>
            <form method="POST" action="{{ route('admin.maintenance-cleanup.window') }}" class="flex items-end gap-2">@csrf
                <label class="text-sm text-gray-300">Months<input name="window_months" type="number" min="1" max="36" value="{{ $windowMonths }}" class="mt-1 w-24 rounded-lg border border-gray-600 bg-gray-700 px-3 py-2 text-white"></label>
                <button class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white">Save</button>
            </form>
        </div>
    </section>

    <section class="rounded-2xl border border-gray-700 bg-gray-800 p-5">
        <form method="GET" class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-[12rem_12rem_auto]"><input type="date" name="date_from" value="{{ $dateFrom }}" class="rounded-lg border border-gray-600 bg-gray-700 px-3 py-2 text-white"><input type="date" name="date_to" value="{{ $dateTo }}" class="rounded-lg border border-gray-600 bg-gray-700 px-3 py-2 text-white"><button class="rounded-lg bg-gray-600 px-3 py-2 text-sm font-semibold text-white">Filter</button></form>
        <form method="POST" action="{{ route('admin.maintenance-cleanup.destroy') }}" onsubmit="return confirm('Delete the selected checklist history and linked maintenance photos? This cannot be undone.')">@csrf @method('DELETE')
            <input type="hidden" name="date_from" value="{{ $dateFrom }}"><input type="hidden" name="date_to" value="{{ $dateTo }}">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3"><label class="inline-flex items-center gap-2 text-sm text-gray-300"><input type="checkbox" x-bind:checked="allPageSelected()" x-on:change="togglePage($event.target.checked)" class="h-4 w-4"> Select page</label><button type="submit" x-bind:disabled="!canDelete()" class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50">Delete selected / date range</button></div>
            <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="text-left text-gray-300"><tr><th class="px-3 py-2">Select</th><th class="px-3 py-2">Date</th><th class="px-3 py-2">Property</th><th class="px-3 py-2">Type</th><th class="px-3 py-2">Checked by</th><th class="px-3 py-2">Remarks</th></tr></thead><tbody class="divide-y divide-gray-700">@forelse($records as $record)<tr><td class="px-3 py-2"><input type="checkbox" name="record_ids[]" value="{{ $record->id }}" x-model.number="selected" class="h-4 w-4"></td><td class="px-3 py-2 text-gray-300">{{ $record->maintenance_date?->format('M d, Y') }}</td><td class="px-3 py-2 text-white">{{ $record->device?->property_number ?? '-' }}</td><td class="px-3 py-2 text-gray-300">{{ $record->device?->type?->name ?? '-' }}</td><td class="px-3 py-2 text-gray-300">{{ $record->checkedBy?->name ?? '-' }}</td><td class="px-3 py-2 text-gray-300">{{ $record->remarks ?? '-' }}</td></tr>@empty<tr><td colspan="6" class="px-3 py-8 text-center text-gray-400">No checklist history found.</td></tr>@endforelse</tbody></table></div>
        </form>
        <div class="mt-4">{{ $records->links() }}</div>
    </section>
</div>
@endsection
