@extends('admin.layouts.app')

@section('title', 'Offices')
@section('page_title', 'Offices')

@section('content')
@php
    $addBag = $errors->getBag('add');
    $editBag = $errors->getBag('edit');

    $oldNames = old('names', []);
    $bulkSeedCount = $oldNames ? max(1, min(3, count($oldNames))) : 2;

    $bulkRowsSeed = [];
    for ($i = 0; $i < $bulkSeedCount; $i++) {
        $bulkRowsSeed[] = [
            'name' => $oldNames[$i] ?? '',
            'nameError' => $addBag->first("names.$i"),
        ];
    }
@endphp
<script>
function registerOfficeManager() {
    if (!window.Alpine) return;

    Alpine.data('officeManager', () => ({
        addOpen: {{ $addBag->any() ? 'true' : 'false' }},
        editOpen: {{ $editBag->any() ? 'true' : 'false' }},
        deleteOpen: false,
        bulkEnabled: {{ old('names') !== null ? 'true' : 'false' }},

        addSingle: {
            name: @js(old('name', '')),
            nameError: @js($addBag->first('name'))
        },

        bulkRows: @json($bulkRowsSeed),

        editOffice: {
            id: @js(old('editing_id') !== null ? (int) old('editing_id') : null),
            name: @js(old('name', '')),
            nameError: @js($editBag->first('name'))
        },
        deleteOfficeId: null,

        openAdd() {
            this.addOpen = true;
            this.bulkEnabled = false;
            this.addSingle = { name: '', nameError: '' };
            this.bulkRows = [
                { name: '', nameError: '' },
                { name: '', nameError: '' },
            ];
        },

        addBulkRow() {
            if (this.bulkRows.length < 3) {
                this.bulkRows.push({ name: '', nameError: '' });
            }
        },

        removeBulkRow() {
            if (this.bulkRows.length > 1) {
                this.bulkRows.pop();
            }
        },

        openEdit(office) {
            this.editOffice = {
                id: office.id,
                name: office.name,
                nameError: ''
            };
            this.editOpen = true;
        },

        openDelete(id) {
            this.deleteOfficeId = id;
            this.deleteOpen = true;
            this.$nextTick(() => this.$refs.confirmDeleteBtn && this.$refs.confirmDeleteBtn.focus());
        }
    }));
    initializeOfficeManagerTree();
}

function initializeOfficeManagerTree() {
    if (!window.Alpine) return;

    document.querySelectorAll('[x-data="officeManager"]').forEach((element) => {
        const data = element._x_dataStack?.[0];

        // Reinitialize an incomplete root after SPA navigation, but leave a
        // working tree untouched so each office button has one handler only.
        if (data && typeof data.openAdd === 'function') return;

        if (data && typeof window.Alpine.destroyTree === 'function') {
            window.Alpine.destroyTree(element);
        }

        window.Alpine.initTree(element);
    });
}
document.addEventListener('alpine:init', registerOfficeManager);
registerOfficeManager();
document.addEventListener('livewire:navigated', () => {
    registerOfficeManager();
    window.setTimeout(initializeOfficeManagerTree, 0);
});
</script>
<div
    x-data="officeManager"
    class="space-y-5"
>
    {{-- Breadcrumb --}}
    <div class="text-sm text-gray-500 leading-6 break-words">
        <a class="text-blue-600 hover:underline" href="{{ route('admin.locations.index') }}">Locations</a>
        <span class="mx-1">/</span>
        <span class="text-gray-700 font-medium">{{ $location->name }}</span>
        <span class="mx-1">/</span>
        <span>Offices</span>
    </div>

    {{-- Top section --}}
    <div class="flex items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Offices in {{ $location->name }}</h1>
        </div>

        @if(auth()->user()->isAdmin())
            <button
                type="button"
                class="shrink-0 inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
                @click="openAdd()"
            >
                + Add Office
            </button>
        @endif
    </div>

    {{-- Mobile cards --}}
    <div class="grid grid-cols-1 gap-3 md:hidden">
        @forelse ($offices as $o)
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="space-y-3">
                    <div>
                        <a
                            class="font-semibold text-blue-700 hover:underline"
                            href="{{ route('admin.staff.index', $o) }}"
                        >
                            {{ $o->name }}
                        </a>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-1">
                        @if(auth()->user()->isAdmin())
                            <button
                                type="button"
                                class="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-black"
                                @click="openEdit({
                                    id: {{ $o->id }},
                                    name: @js($o->name)
                                })"
                            >
                                Edit
                            </button>

                            <button
                                type="button"
                                class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700"
                                @click="openDelete({{ $o->id }})"
                            >
                                Delete
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-gray-200 bg-white p-6 text-center text-gray-500 shadow-sm">
                No offices found.
            </div>
        @endforelse
    </div>

    {{-- Desktop table --}}
    <div class="hidden md:block overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-gray-700">Office Name</th>
                        <th class="px-4 py-3 font-semibold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($offices as $o)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <a
                                    class="font-medium text-blue-700 hover:underline"
                                    href="{{ route('admin.staff.index', $o) }}"
                                >
                                    {{ $o->name }}
                                </a>
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    @if(auth()->user()->isAdmin())
                                        <button
                                            type="button"
                                            class="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-black"
                                            @click="openEdit({
                                                id: {{ $o->id }},
                                                name: @js($o->name)
                                            })"
                                        >
                                            Edit
                                        </button>

                                        <button
                                            type="button"
                                            class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700"
                                            @click="openDelete({{ $o->id }})"
                                        >
                                            Delete
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400">View only</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-6 py-8 text-center text-gray-500">
                                No offices found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>
        {{ $offices->links() }}
    </div>

    {{-- Add modal --}}
    <x-modal show="addOpen" title="Add Office">
        <form method="POST" action="{{ route('admin.offices.store', $location) }}" class="space-y-3">
            @csrf

            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700">Add multiple offices</span>
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium border"
                    :class="bulkEnabled ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300'"
                    @click="bulkEnabled = !bulkEnabled"
                >
                    <span x-text="bulkEnabled ? 'Bulk: On' : 'Bulk: Off'"></span>
                </button>
            </div>

            <div class="space-y-3">
                <!-- Bulk controls -->
                <div x-show="bulkEnabled" class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-lg bg-gray-100 px-3 py-1.5 text-gray-700 hover:bg-gray-200"
                        @click="removeBulkRow()"
                    >-
                    </button>

                    <input type="hidden" name="count" :value="bulkRows.length">

                    <div class="text-sm text-gray-700">
                        Records: <span class="font-semibold" x-text="bulkRows.length"></span>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg bg-gray-100 px-3 py-1.5 text-gray-700 hover:bg-gray-200"
                        @click="addBulkRow()"
                    >+
                    </button>
                </div>

                <!-- Bulk form -->
                <template x-if="bulkEnabled">
                    <div class="space-y-5">
                        <template x-for="(row, idx) in bulkRows" :key="idx">
                            <div class="space-y-3" :class="idx > 0 ? 'pt-4 border-t border-gray-200' : ''">
                                <div>
                                    <label class="text-sm font-medium">Office Name <span class="text-red-500">*</span></label>
                                    <input
                                        :name="`names[${idx}]`"
                                        x-model="row.name"
                                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"
                                        required
                                        maxlength="150"
                                        pattern="[A-Za-zÑñ0-9][A-Za-zÑñ0-9.,&'\-\(\)\s]*"
                                        title="Letters, numbers, and basic punctuation only"
                                        placeholder="e.g. Office of the Dean"
                                    >
                                    <div class="mt-1 text-sm text-red-600" x-show="row.nameError" x-text="row.nameError"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Single form -->
                <template x-if="!bulkEnabled">
                    <div class="space-y-3">
                        <div>
                            <label class="text-sm font-medium">Office Name <span class="text-red-500">*</span></label>
                            <input
                                name="name"
                                x-model="addSingle.name"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"
                                required
                                maxlength="150"
                                pattern="[A-Za-zÑñ0-9][A-Za-zÑñ0-9.,&'\-\(\)\s]*"
                                title="Letters, numbers, and basic punctuation only"
                                placeholder="e.g. Office of the Dean"
                            >
                            <div class="mt-1 text-sm text-red-600" x-show="addSingle.nameError" x-text="addSingle.nameError"></div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex gap-2 pt-2">
                <button class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Save</button>
                <button
                    type="button"
                    class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200"
                    @click="addOpen = false"
                >
                    Cancel
                </button>
            </div>
        </form>
    </x-modal>

    {{-- Edit modal --}}
    <x-modal show="editOpen" title="Edit Office">
        <form
            method="POST"
            :action="'{{ route('admin.offices.update', ['location' => $location->id, 'office' => '__OFFICE__']) }}'.replace('__OFFICE__', editOffice.id)"
            class="space-y-3"
        >
            @csrf
            @method('PUT')

            <input type="hidden" name="editing_id" :value="editOffice.id">

            <div>
                <label class="text-sm font-medium">Office Name <span class="text-red-500">*</span></label>
                <input
                    name="name"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"
                    x-model="editOffice.name"
                    required
                    maxlength="150"
                    pattern="[A-Za-zÑñ0-9][A-Za-zÑñ0-9.,&'\-\(\)\s]*"
                    title="Letters, numbers, and basic punctuation only"
                >
                <div class="mt-1 text-sm text-red-600" x-show="editOffice.nameError" x-text="editOffice.nameError"></div>
            </div>

            <div class="flex gap-2 pt-2">
                <button class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Update</button>
                <button
                    type="button"
                    class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200"
                    @click="editOpen = false"
                >
                    Cancel
                </button>
            </div>
        </form>
    </x-modal>

    {{-- Delete modal --}}
    <x-modal show="deleteOpen" title="Delete Office">
        <div class="space-y-3">
            <div class="text-sm text-gray-700">
                Are you sure you want to delete this office?
            </div>

            <form
                method="POST"
                :action="'{{ route('admin.offices.destroy', ['location' => $location->id, 'office' => '__OFFICE__']) }}'.replace('__OFFICE__', deleteOfficeId)"
                @submit="if (!deleteOfficeId) $event.preventDefault()"
                class="flex gap-2"
            >
                @csrf
                @method('DELETE')

                <button type="submit" x-ref="confirmDeleteBtn" class="rounded-lg bg-red-600 px-4 py-2 text-white hover:bg-red-700">Confirm</button>
                <button
                    type="button"
                    class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200"
                    @click="deleteOpen = false"
                >
                    Cancel
                </button>
            </form>
        </div>
    </x-modal>
</div>
@endsection
