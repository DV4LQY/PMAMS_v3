@extends('admin.layouts.app')

@section('title', 'Checklist Cleanup')
@section('page_title', 'Checklist Cleanup')
@section('breadcrumbs')
    <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-600">Dashboard</a><span>/</span>
    <span class="font-medium">Checklist Cleanup</span>
@endsection

@section('content')
<div class="space-y-6">
    @if($errors->any())
        <div class="notification rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-700/40 dark:bg-red-900/20 dark:text-red-300">{{ $errors->first() }}</div>
    @endif

    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Checklist recovery bin</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Only checklist history deleted from Checked Equipment appears here. Restore records individually or across all filtered pages.</p>
            </div>
            <form method="POST" action="{{ route('admin.maintenance-cleanup.window') }}" class="flex items-end gap-2">
                @csrf
                <label class="text-sm text-gray-700 dark:text-gray-300">Duplicate window (months)
                    <input name="window_months" type="number" min="1" max="36" value="{{ $windowMonths }}" class="mt-1 w-24 rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </label>
                <button class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white">Save</button>
            </form>
        </div>
    </section>

    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <form method="GET" class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-[12rem_12rem_auto]">
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            <input type="date" name="date_to" value="{{ $dateTo }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            <button class="cleanup-filter-button justify-self-start rounded-lg bg-gray-600 px-3 py-2 text-sm font-semibold text-white">Filter deleted history</button>
        </form>

        @if($deletedRecords->total() > 0)
        <div class="mb-4 flex items-center justify-between rounded-xl border border-gray-200 bg-white px-5 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                <input id="deleted-checklist-select-all" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-red-600 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-700" onchange="toggleCleanupAll(this.checked)" aria-label="Select all deleted checklist records matching the current filters">
                Select all deleted checklist records matching the current filters
            </label>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ number_format($deletedRecords->total()) }} matching</span>
        </div>
        @endif

        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Deleted checklist records</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">These records are soft-deleted and remain recoverable until permanently deleted.</p>
            </div>
            @if($deletedRecords->total() > 0)
            <div id="cleanup-bulk-actions" class="hidden flex flex-wrap items-center gap-3">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input id="deleted-checklist-select-page" type="checkbox" class="h-4 w-4" onchange="toggleCleanupPage(this.checked)">
                    Select page
                </label>
                <button type="button" onclick="restoreSelectedDeletedChecklists()" class="rounded-lg bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700">Restore selected</button>
                <button type="button" onclick="openCleanupBulkDeleteRemarks()" class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">Permanently delete selected</button>
            </div>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-600 dark:bg-gray-900/50 dark:text-gray-300">
                    <tr>
                        <th class="px-3 py-2">Select</th>
                        <th class="px-3 py-2">Deleted</th>
                        <th class="px-3 py-2">Maintenance date</th>
                        <th class="px-3 py-2">Property</th>
                        <th class="px-3 py-2">Type</th>
                        <th class="px-3 py-2">Checked by</th>
                        <th class="px-3 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($deletedRecords as $record)
                        <tr>
                            <td class="px-3 py-2"><input type="checkbox" data-deleted-checklist-id="{{ $record->id }}" class="h-4 w-4" onchange="syncCleanupSelection()"></td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $record->deleted_at?->format('M d, Y h:i A') ?? '-' }}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $record->maintenance_date?->format('M d, Y') ?? '-' }}</td>
                            <td class="px-3 py-2 font-medium text-gray-900 dark:text-white">{{ $record->device?->property_number ?? '-' }}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $record->device?->type?->name ?? '-' }}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $record->checkedBy?->name ?? '-' }}</td>
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-action-icon type="button" icon="restore" variant="green" label="Restore checklist" onclick="restoreCleanupRecords([{{ $record->id }}])" />
                                    <x-action-icon type="button" icon="trash" variant="red" label="Permanently delete checklist" onclick="openCleanupDeleteRemarks({{ $record->id }})" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-8 text-center text-gray-500 dark:text-gray-400">No deleted checklist history found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $deletedRecords->links() }}</div>
    </section>

    <form id="deleted-checklist-restore-form" method="POST" action="{{ route('admin.maintenance-cleanup.restoreBulk') }}" class="hidden">
        @csrf
        @method('PATCH')
        <input type="hidden" name="select_all" id="deleted-checklist-restore-select-all" value="0">
        <input type="hidden" name="date_from" value="{{ $dateFrom }}">
        <input type="hidden" name="date_to" value="{{ $dateTo }}">
    </form>
    <form id="deleted-checklist-delete-form" method="POST" action="{{ route('admin.maintenance-cleanup.forceDestroyBulk') }}" class="hidden">
        @csrf
        @method('DELETE')
        <input type="hidden" name="select_all" id="deleted-checklist-delete-select-all" value="0">
        <input type="hidden" name="date_from" value="{{ $dateFrom }}">
        <input type="hidden" name="date_to" value="{{ $dateTo }}">
        <input type="hidden" name="remarks" id="deleted-checklist-delete-remarks" value="">
    </form>

    <div id="cleanup-delete-remarks-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="cleanup-delete-remarks-title">
        <div class="w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="cleanup-delete-remarks-title" class="text-lg font-semibold text-gray-900 dark:text-white">Permanent deletion remarks</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">This action cannot be undone. Add an optional reason for the activity log.</p>
                </div>
                <button type="button" onclick="closeCleanupDeleteRemarks()" class="rounded-lg px-2 py-1 text-2xl leading-none text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-700 dark:hover:text-white" aria-label="Close">&times;</button>
            </div>
            <label for="cleanup-modal-remarks" class="mt-4 block text-sm font-medium text-gray-700 dark:text-gray-200">Deletion remarks <span class="text-gray-500">(optional)</span></label>
            <textarea id="cleanup-modal-remarks" rows="4" maxlength="1000" class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-red-500 focus:ring-2 focus:ring-red-200 dark:border-gray-600 dark:bg-gray-900 dark:text-white" placeholder="Optional reason for permanent deletion"></textarea>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" onclick="closeCleanupDeleteRemarks()" class="rounded-lg bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600">Cancel</button>
                <button type="button" onclick="confirmCleanupDeleteRemarks()" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Permanently delete</button>
            </div>
        </div>
    </div>
</div>
@endsection

<style>
    .cleanup-filter-button { min-height: 36px !important; }
</style>

@push('scripts')
<script>
let pendingCleanupDelete = null;

function cleanupBoxes() {
    return [...document.querySelectorAll('[data-deleted-checklist-id]')];
}

function toggleCleanupPage(checked) {
    cleanupBoxes().forEach((box) => { box.checked = checked; });
    if (!checked) document.getElementById('deleted-checklist-select-all').checked = false;
    syncCleanupSelection();
}

function toggleCleanupAll(checked) {
    document.getElementById('deleted-checklist-select-page').checked = checked;
    cleanupBoxes().forEach((box) => { box.checked = checked; });
    syncCleanupSelection();
}

function syncCleanupSelection() {
    const boxes = cleanupBoxes();
    const allMatching = document.getElementById('deleted-checklist-select-all');
    const selected = boxes.filter((box) => box.checked).length;
    if (allMatching?.checked && boxes.some((box) => !box.checked)) allMatching.checked = false;
    document.getElementById('deleted-checklist-select-page').checked = boxes.length > 0 && selected === boxes.length;
    document.getElementById('cleanup-bulk-actions')?.classList.toggle('hidden', selected === 0 && !allMatching?.checked);
}

function restoreCleanupRecords(ids, selectAll = false) {
    if (!selectAll && (!ids || ids.length === 0)) {
        window.alert('Select at least one deleted checklist history record, or choose the all-pages option.');
        return;
    }
    if (!window.confirm(selectAll
        ? 'Restore every deleted checklist history record matching the current filter across every page?'
        : `Restore ${ids.length} selected deleted checklist history record(s)?`)) return;

    const form = document.getElementById('deleted-checklist-restore-form');
    form.querySelectorAll('input[name="record_ids[]"]').forEach((input) => input.remove());
    document.getElementById('deleted-checklist-restore-select-all').value = selectAll ? '1' : '0';
    if (!selectAll) ids.forEach((id) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'record_ids[]';
        input.value = id;
        form.appendChild(input);
    });
    form.submit();
}

function restoreSelectedDeletedChecklists() {
    const all = document.getElementById('deleted-checklist-select-all').checked;
    const ids = cleanupBoxes().filter((box) => box.checked).map((box) => box.dataset.deletedChecklistId);
    restoreCleanupRecords(ids, all);
}

function openCleanupDeleteRemarks(recordId) {
    pendingCleanupDelete = { ids: [String(recordId)], selectAll: false };
    openCleanupDeleteRemarksModal();
}

function openCleanupBulkDeleteRemarks() {
    const selectAll = document.getElementById('deleted-checklist-select-all')?.checked === true;
    const ids = cleanupBoxes().filter((box) => box.checked).map((box) => box.dataset.deletedChecklistId);

    if (!selectAll && ids.length === 0) {
        window.alert('Select at least one deleted checklist record, or choose select all matching the filter.');
        return;
    }

    pendingCleanupDelete = { ids, selectAll };
    openCleanupDeleteRemarksModal();
}

function openCleanupDeleteRemarksModal() {
    const modal = document.getElementById('cleanup-delete-remarks-modal');
    const textarea = document.getElementById('cleanup-modal-remarks');
    textarea.value = '';
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    window.setTimeout(() => textarea.focus(), 30);
}

function closeCleanupDeleteRemarks() {
    const modal = document.getElementById('cleanup-delete-remarks-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    pendingCleanupDelete = null;
}

function confirmCleanupDeleteRemarks() {
    const remarks = String(document.getElementById('cleanup-modal-remarks').value || '').trim();
    const form = document.getElementById('deleted-checklist-delete-form');
    form.querySelectorAll('input[name="record_ids[]"]').forEach((input) => input.remove());
    document.getElementById('deleted-checklist-delete-remarks').value = remarks;
    document.getElementById('deleted-checklist-delete-select-all').value = pendingCleanupDelete?.selectAll ? '1' : '0';
    (pendingCleanupDelete?.ids || []).forEach((id) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'record_ids[]';
        input.value = id;
        form.appendChild(input);
    });
    closeCleanupDeleteRemarks();
    form.submit();
}

document.getElementById('cleanup-delete-remarks-modal')?.addEventListener('click', (event) => {
    if (event.target.id === 'cleanup-delete-remarks-modal') closeCleanupDeleteRemarks();
});
</script>
@endpush
