@extends('admin.layouts.app')

@section('title', 'Recycle Bin')
@section('page_title', 'Recycle Bin')

@section('content')
<div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Recycle Bin</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Restore deleted users and equipment. Permanent deletion is available only to Super Admins.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="button" onclick="emptyRecycleBin()" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                Permanently Delete All
            </button>
            <a href="{{ route('admin.users.index') }}" wire:navigate class="inline-flex items-center justify-center rounded-xl bg-gray-700 px-4 py-2 text-sm font-medium text-white hover:bg-gray-600">
                Back to Users
            </a>
        </div>
    </div>

    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
        Restoring returns the record to the active list. Permanent deletion cannot be undone. Equipment photos and checklist history are retained until their parent equipment record is permanently deleted.
    </div>

    @php($deletedUserCount = $deletedUsers->total())
    @php($deletedDeviceCount = $deletedDevices->total())
    @if($deletedUserCount + $deletedDeviceCount > 0)
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200" role="status">
            <p class="font-semibold">Deleted records are still present in the database.</p>
            <p class="mt-1">{{ number_format($deletedUserCount) }} user(s) and {{ number_format($deletedDeviceCount) }} equipment record(s) are in the recycle bin. Use the row buttons, selected buttons, or <strong>Permanently Delete All</strong> to erase them and their retained equipment history.</p>
        </div>
    @endif

    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Deleted Users</h2>
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left dark:bg-gray-900/40">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Select</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Name</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Email</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Role</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Deleted</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($deletedUsers as $user)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3"><input type="checkbox" data-bin-checkbox="users" value="{{ $user->id }}" class="h-4 w-4"></td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $user->roleLabel() }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">{{ $user->deleted_at?->format('M d, Y h:i A') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex flex-wrap items-center gap-2">
                                    <form method="POST" action="{{ route('admin.users.restore', $user->id) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700">Restore</button>
                                    </form>
                                    <button type="button" onclick="permanentDeleteSingle('users', {{ $user->id }})" class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700">Delete Permanently</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">No deleted users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <button type="button" onclick="permanentDeleteSelected('users')" class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">Permanently Delete Selected Users</button>
        <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300"><input id="delete-all-users" type="checkbox" class="h-4 w-4"> Delete all users in recycle bin</label>
    </div>
    {{ $deletedUsers->links() }}

    <h2 class="pt-3 text-lg font-semibold text-gray-900 dark:text-white">Deleted Equipment</h2>
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left dark:bg-gray-900/40">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Select</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Property</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Type</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">History</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Photos</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Deleted</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($deletedDevices as $device)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3"><input type="checkbox" data-bin-checkbox="devices" value="{{ $device->id }}" class="h-4 w-4"></td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $device->property_number ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $device->type?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $device->maintenance_records_including_trashed_count }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $device->maintenance_photos_count }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">{{ $device->deleted_at?->format('M d, Y h:i A') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex flex-wrap items-center gap-2">
                                    <form method="POST" action="{{ route('admin.devices.restore', $device->id) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700">Restore</button>
                                    </form>
                                    <button type="button" onclick="permanentDeleteSingle('devices', {{ $device->id }})" class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700">Delete Permanently</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">No deleted equipment found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <button type="button" onclick="permanentDeleteSelected('devices')" class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">Permanently Delete Selected Equipment</button>
        <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300"><input id="delete-all-devices" type="checkbox" class="h-4 w-4"> Delete all equipment in recycle bin</label>
    </div>
    {{ $deletedDevices->links() }}

    <form id="recycle-bin-permanent-delete-form" method="POST" action="{{ route('admin.recycle-bin.permanentDelete') }}" class="hidden">
        @csrf
        <input type="hidden" name="type" id="recycle-bin-delete-type">
        <input type="hidden" name="select_all" id="recycle-bin-delete-select-all" value="0">
        <input type="hidden" name="remarks" id="recycle-bin-delete-remarks">
    </form>

    <div id="recycle-bin-remarks-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="recycle-bin-remarks-title">
        <div class="w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="recycle-bin-remarks-title" class="text-lg font-semibold text-gray-900 dark:text-white">Permanent deletion remarks</h2>
                    <p id="recycle-bin-remarks-message" class="mt-1 text-sm text-gray-600 dark:text-gray-300">This action cannot be undone. Enter the reason for the activity log.</p>
                </div>
                <button type="button" onclick="closeRecycleBinRemarks()" class="rounded-lg px-2 py-1 text-2xl leading-none text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-700 dark:hover:text-white" aria-label="Close">&times;</button>
            </div>
            <label for="recycle-bin-modal-remarks" class="mt-4 block text-sm font-medium text-gray-700 dark:text-gray-200">Remarks <span class="text-red-600">*</span></label>
            <textarea id="recycle-bin-modal-remarks" rows="4" maxlength="1000" class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-red-500 focus:ring-2 focus:ring-red-200 dark:border-gray-600 dark:bg-gray-900 dark:text-white" placeholder="Why are these records being permanently deleted?"></textarea>
            <p id="recycle-bin-remarks-error" class="mt-1 hidden text-sm text-red-600 dark:text-red-400">Remarks are required before deletion.</p>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" onclick="closeRecycleBinRemarks()" class="rounded-lg bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600">Cancel</button>
                <button type="button" onclick="confirmRecycleBinRemarks()" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Permanently delete</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let pendingRecycleBinDelete = null;

function openRecycleBinRemarks(action) {
    pendingRecycleBinDelete = action;
    const modal = document.getElementById('recycle-bin-remarks-modal');
    const textarea = document.getElementById('recycle-bin-modal-remarks');
    const error = document.getElementById('recycle-bin-remarks-error');
    const message = document.getElementById('recycle-bin-remarks-message');
    if (message) message.textContent = action.empty
        ? 'This will permanently delete every user and equipment record in the recycle bin.'
        : 'This action permanently deletes the selected recycle-bin records and cannot be undone.';
    if (textarea) textarea.value = '';
    if (error) error.classList.add('hidden');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        window.setTimeout(() => textarea?.focus(), 30);
    }
}

function closeRecycleBinRemarks() {
    const modal = document.getElementById('recycle-bin-remarks-modal');
    modal?.classList.add('hidden');
    modal?.classList.remove('flex');
    pendingRecycleBinDelete = null;
}

function confirmRecycleBinRemarks() {
    const textarea = document.getElementById('recycle-bin-modal-remarks');
    const reason = String(textarea?.value || '').trim();
    if (!reason) {
        document.getElementById('recycle-bin-remarks-error')?.classList.remove('hidden');
        textarea?.focus();
        return;
    }
    const action = pendingRecycleBinDelete;
    closeRecycleBinRemarks();
    if (action) submitRecycleBinDelete(action.type, action.ids || [], action.selectAll, reason, action.empty === true);
}

function submitRecycleBinDelete(type, ids, selectAll, reason, emptyAll = false) {
    const form = document.getElementById('recycle-bin-permanent-delete-form');
    form.querySelectorAll('input[name="ids[]"], input[name="empty"]').forEach(input => input.remove());
    document.getElementById('recycle-bin-delete-type').value = type;
    document.getElementById('recycle-bin-delete-select-all').value = selectAll ? '1' : '0';
    document.getElementById('recycle-bin-delete-remarks').value = reason;

    if (!selectAll) {
        ids.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = id;
            form.appendChild(input);
        });
    }

    if (emptyAll) {
        const empty = document.createElement('input');
        empty.type = 'hidden';
        empty.name = 'empty';
        empty.value = '1';
        form.appendChild(empty);
    }

    form.submit();
}

function permanentDeleteSelected(type) {
    const boxes = [...document.querySelectorAll('[data-bin-checkbox="' + type + '"]:checked')];
    const selectAll = document.getElementById('delete-all-' + type)?.checked === true;
    if (!selectAll && boxes.length === 0) {
        alert('Select at least one record or choose the delete-all option.');
        return;
    }
    openRecycleBinRemarks({ type, ids: boxes.map(box => box.value), selectAll, empty: false });
}

function permanentDeleteSingle(type, id) {
    openRecycleBinRemarks({ type, ids: [String(id)], selectAll: false, empty: false });
}

function emptyRecycleBin() {
    openRecycleBinRemarks({ type: 'all', ids: [], selectAll: true, empty: true });
}

document.getElementById('recycle-bin-remarks-modal')?.addEventListener('click', (event) => {
    if (event.target.id === 'recycle-bin-remarks-modal') closeRecycleBinRemarks();
});
</script>
@endpush
@endsection
