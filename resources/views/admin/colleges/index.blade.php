@extends('admin.layouts.app')

@section('title', 'Colleges')
@section('page_title', 'Colleges')

@section('content')
@php
    $addBag = $errors->getBag('add');
    $editBag = $errors->getBag('edit');

    $oldNames = old('names', []);
    $oldCodes = old('codes', []);
    $bulkSeedCount = $oldNames ? max(1, min(3, count($oldNames))) : 2;

    $bulkRowsSeed = [];
    for ($i = 0; $i < $bulkSeedCount; $i++) {
        $bulkRowsSeed[] = [
            'name' => $oldNames[$i] ?? '',
            'code' => $oldCodes[$i] ?? '',
            'nameError' => $addBag->first("names.$i"),
            'codeError' => $addBag->first("codes.$i"),
        ];
    }
@endphp
<script>
function registerCollegeManager() {
    if (!window.Alpine) return;

    Alpine.data('collegeManager', () => ({
        addOpen: {{ $addBag->any() ? 'true' : 'false' }},
        editOpen: {{ $editBag->any() ? 'true' : 'false' }},
        deleteOpen: false,
        bulkEnabled: {{ old('names') !== null ? 'true' : 'false' }},

        addSingle: {
            name: @js(old('name', '')),
            code: @js(old('code', '')),
            nameError: @js($addBag->first('name')),
            codeError: @js($addBag->first('code'))
        },

        bulkRows: @json($bulkRowsSeed),

        editCollege: {
            id: @js(old('editing_id') !== null ? (int) old('editing_id') : null),
            name: @js(old('name', '')),
            code: @js(old('code', '')),
            nameError: @js($editBag->first('name')),
            codeError: @js($editBag->first('code'))
        },
        deleteCollegeId: null,

        openAdd() {
            this.addOpen = true;
            this.bulkEnabled = false;
            this.addSingle = { name: '', code: '', nameError: '', codeError: '' };
            this.bulkRows = [
                { name: '', code: '', nameError: '', codeError: '' },
                { name: '', code: '', nameError: '', codeError: '' },
            ];
        },

        addBulkRow() {
            if (this.bulkRows.length < 3) {
                this.bulkRows.push({ name: '', code: '', nameError: '', codeError: '' });
            }
        },

        removeBulkRow() {
            if (this.bulkRows.length > 1) {
                this.bulkRows.pop();
            }
        },

        openEdit(college) {
            this.editCollege = {
                id: college.id,
                name: college.name,
                code: college.code,
                nameError: '',
                codeError: ''
            };
            this.editOpen = true;
        },

        openDelete(id) {
            this.deleteCollegeId = id;
            this.deleteOpen = true;
            this.$nextTick(() => this.$refs.confirmDeleteBtn && this.$refs.confirmDeleteBtn.focus());
        }
    }));
    document.querySelectorAll('[x-data="collegeManager"]').forEach((element) => {
        if (!element._x_dataStack) Alpine.initTree(element);
    });
}
document.addEventListener('alpine:init', registerCollegeManager);
registerCollegeManager();
document.addEventListener('livewire:navigated', registerCollegeManager);
</script>
<div
    x-data="collegeManager"
    class="space-y-5"
>


    {{-- Top section --}}
    <div class="flex items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Colleges</h1>
        </div>

        @if(auth()->user()->isAdmin())
            <button
                type="button"
                class="shrink-0 inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                @click="openAdd()"
            >
                + Add College
            </button>
        @endif
    </div>

    {{-- Mobile cards --}}
    <div class="grid grid-cols-1 gap-3 md:hidden">
        @forelse ($colleges as $c)
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="space-y-3">
                    <div>
                        <a
                            class="font-semibold text-blue-700 hover:underline dark:text-blue-400"
                            href="{{ route('admin.offices.index', $c) }}"
                        >
                            {{ $c->name }}
                        </a>
                    </div>

                    <div class="text-sm">
                        <div class="text-gray-500 dark:text-gray-400">Code</div>
                        <div class="text-gray-900 dark:text-white">{{ $c->code ?: '-' }}</div>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-1">
                        @if(auth()->user()->isAdmin())
                            <button
                                type="button"
                                class="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-black dark:bg-gray-700 dark:hover:bg-gray-600"
                                @click="openEdit({
                                    id: {{ $c->id }},
                                    name: @js($c->name),
                                    code: @js($c->code ?? '')
                                })"
                            >
                                Edit
                            </button>

                            <button
                                type="button"
                                class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600"
                                @click="openDelete({{ $c->id }})"
                            >
                                Delete
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-gray-200 bg-white p-6 text-center text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                No colleges found.
            </div>
        @endforelse
    </div>

    {{-- Desktop table --}}
    <div class="hidden md:block overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left dark:bg-gray-900/40">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Name</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Code</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($colleges as $c)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3">
                                <a
                                    class="font-medium text-blue-700 hover:underline dark:text-blue-400"
                                    href="{{ route('admin.offices.index', $c) }}"
                                >
                                    {{ $c->name }}
                                </a>
                            </td>

                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $c->code ?: '-' }}</td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    @if(auth()->user()->isAdmin())
                                        <button
                                            type="button"
                                            class="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-black dark:bg-gray-700 dark:hover:bg-gray-600"
                                            @click="openEdit({
                                                id: {{ $c->id }},
                                                name: @js($c->name),
                                                code: @js($c->code ?? '')
                                            })"
                                        >
                                            Edit
                                        </button>

                                        <button
                                            type="button"
                                            class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600"
                                            @click="openDelete({{ $c->id }})"
                                        >
                                            Delete
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">View only</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                No colleges found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>
        {{ $colleges->links() }}
    </div>

    {{-- Add modal --}}
    <x-modal show="addOpen" title="Add College">
        <form method="POST" action="{{ route('admin.colleges.store') }}" class="space-y-3">
            @csrf

            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Add multiple colleges</span>
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium border"
                    :class="bulkEnabled ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600'"
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
                        class="rounded-lg bg-gray-100 px-3 py-1.5 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        @click="removeBulkRow()"
                    >-
                    </button>

                    <input type="hidden" name="count" :value="bulkRows.length">

                    <div class="text-sm text-gray-700 dark:text-gray-300">
                        Records: <span class="font-semibold" x-text="bulkRows.length"></span>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg bg-gray-100 px-3 py-1.5 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        @click="addBulkRow()"
                    >+
                    </button>
                </div>

                <!-- Bulk form -->
                <template x-if="bulkEnabled">
                    <div class="space-y-5">
                        <template x-for="(row, idx) in bulkRows" :key="idx">
                            <div class="space-y-3" :class="idx > 0 ? 'pt-4 border-t border-gray-200 dark:border-gray-700' : ''">
                                <div>
                                    <label class="text-sm font-medium dark:text-gray-300">College Name <span class="text-red-500">*</span></label>
                                    <input
                                        :name="`names[${idx}]`"
                                        x-model="row.name"
                                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        required
                                        maxlength="150"
                                        pattern="[A-Za-zÑñ0-9][A-Za-zÑñ0-9.,&'\-\(\)\s]*"
                                        title="Letters, numbers, and basic punctuation only"
                                        placeholder="e.g. College of Science"
                                    >
                                    <div class="mt-1 text-sm text-red-600 dark:text-red-400" x-show="row.nameError" x-text="row.nameError"></div>
                                </div>

                                <div>
                                    <label class="text-sm font-medium dark:text-gray-300">Code (optional)</label>
                                    <input
                                        :name="`codes[${idx}]`"
                                        x-model="row.code"
                                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        maxlength="20"
                                        pattern="[A-Za-z0-9\-]*"
                                        title="Letters, numbers, and hyphens only (no spaces)"
                                        placeholder="e.g. COS"
                                        @input="row.code = row.code.toUpperCase().replace(/[^A-Z0-9\-]/g, '')"
                                    >
                                    <div class="mt-1 text-sm text-red-600 dark:text-red-400" x-show="row.codeError" x-text="row.codeError"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Single form -->
                <template x-if="!bulkEnabled">
                    <div class="space-y-3">
                        <div>
                            <label class="text-sm font-medium dark:text-gray-300">College Name <span class="text-red-500">*</span></label>
                            <input
                                name="name"
                                x-model="addSingle.name"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                required
                                maxlength="150"
                                pattern="[A-Za-zÑñ0-9][A-Za-zÑñ0-9.,&'\-\(\)\s]*"
                                title="Letters, numbers, and basic punctuation only"
                                placeholder="e.g. College of Science"
                            >
                            <div class="mt-1 text-sm text-red-600 dark:text-red-400" x-show="addSingle.nameError" x-text="addSingle.nameError"></div>
                        </div>

                        <div>
                            <label class="text-sm font-medium dark:text-gray-300">Code (optional)</label>
                            <input
                                name="code"
                                x-model="addSingle.code"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                maxlength="20"
                                pattern="[A-Za-z0-9\-]*"
                                title="Letters, numbers, and hyphens only (no spaces)"
                                placeholder="e.g. COS"
                                @input="addSingle.code = addSingle.code.toUpperCase().replace(/[^A-Z0-9\-]/g, '')"
                            >
                            <div class="mt-1 text-sm text-red-600 dark:text-red-400" x-show="addSingle.codeError" x-text="addSingle.codeError"></div>
                        </div>
                    </div>
                </template>
            </div>


            <div class="flex gap-2 pt-2">
                <button class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600">Save</button>
                <button type="button" class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600" @click="addOpen = false">
                    Cancel
                </button>
            </div>


        </form>
    </x-modal>


    {{-- Edit modal --}}
    <x-modal show="editOpen" title="Edit College" >
        <form
            method="POST"
            action="{{ route('admin.colleges.update', '__ID__') }}"
            x-bind:action="'{{ route('admin.colleges.update', '__ID__') }}'.replace('__ID__', editCollege.id)"
            class="space-y-3"
        >
            @csrf
            @method('PUT')

            <input type="hidden" name="editing_id" :value="editCollege.id">

            <div>
                <label class="text-sm font-medium dark:text-gray-300">College Name <span class="text-red-500">*</span></label>
                <input
                    name="name"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    x-model="editCollege.name"
                    required
                    maxlength="150"
                    pattern="[A-Za-zÑñ0-9][A-Za-zÑñ0-9.,&'\-\(\)\s]*"
                    title="Letters, numbers, and basic punctuation only"
                >
                <div class="mt-1 text-sm text-red-600 dark:text-red-400" x-show="editCollege.nameError" x-text="editCollege.nameError"></div>
            </div>

            <div>
                <label class="text-sm font-medium dark:text-gray-300">Code (optional)</label>
                <input
                    name="code"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    x-model="editCollege.code"
                    maxlength="20"
                    pattern="[A-Za-z0-9\-]*"
                    title="Letters, numbers, and hyphens only (no spaces)"
                    @input="editCollege.code = editCollege.code.toUpperCase().replace(/[^A-Z0-9\-]/g, '')"
                >
                <div class="mt-1 text-sm text-red-600 dark:text-red-400" x-show="editCollege.codeError" x-text="editCollege.codeError"></div>
            </div>

            <div class="flex gap-2 pt-2">
                <button class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600">Update</button>
                <button type="button" class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600" @click="editOpen = false">
                    Cancel
                </button>
            </div>
        </form>
    </x-modal>

    {{-- Delete modal --}}
    <x-modal show="deleteOpen" title="Delete College">
        <div class="space-y-3">
            <div class="text-sm text-gray-700 dark:text-gray-300">
                Are you sure you want to delete this college?
            </div>



            <form
                method="POST"
                :action="`{{ route('admin.colleges.destroy', ['college' => '__ID__']) }}`.replace('__ID__', deleteCollegeId)"
                @submit="if (!deleteCollegeId) $event.preventDefault()"
                class="flex gap-2"
            >

                @csrf
                @method('DELETE')

                <button type="submit" x-ref="confirmDeleteBtn" class="rounded-lg bg-red-600 px-4 py-2 text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600">Confirm</button>

                <button type="button" class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600" @click="deleteOpen = false">
                    Cancel
                </button>
            </form>
        </div>
    </x-modal>
</div>
@endsection
