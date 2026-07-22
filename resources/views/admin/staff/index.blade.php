@extends('admin.layouts.app')

@section('title', 'Staff')
@section('page_title', 'Staff')

@section('content')
    @php
        // Common administrative/academic positions found across Philippine college
        // departments/offices. Kept generic since these roles repeat across every
        // college type (College of Science, College of Education, etc.).
        $commonPositions = [
            'Dean',
            'Associate Dean',
            'Department Chairperson',
            'Program Coordinator',
            'College Secretary',
            'Administrative Officer',
            'Administrative Aide',
            'Records Officer',
            'Guidance Counselor',
            'Librarian',
            'Library Assistant',
            'Registrar Staff',
            'Faculty / Instructor',
            'Assistant Professor',
            'Associate Professor',
            'Professor',
            'Laboratory Technician',
            'IT / MIS Staff',
            'Property Custodian',
            'Budget Officer',
            'Accounting Staff',
            'Cashier',
            'Human Resources Officer',
            'Clerk',
            'Utility Worker',
            'Security Guard',
            'Driver',
        ];

        // Resolve a stored/old position string into [selectValue, otherText] so
        // the dropdown can pre-select a known option, or fall back to "Other".
        $resolvePosition = function (?string $value) use ($commonPositions) {
            if ($value === null || $value === '') {
                return ['', ''];
            }

            if (in_array($value, $commonPositions, true)) {
                return [$value, ''];
            }

            return ['__other__', $value];
        };

        $addBag = $errors->getBag('add');
        $editBag = $errors->getBag('edit');

        [$addSinglePosition, $addSinglePositionOther] = $resolvePosition(old('position'));
        [$editPosition, $editPositionOther] = $resolvePosition(old('position'));

        $oldStaffRows = old('staff', []);
        $bulkSeedCount = $oldStaffRows ? max(1, min(3, count($oldStaffRows))) : 2;

        $bulkRowsSeed = [];
        for ($i = 0; $i < $bulkSeedCount; $i++) {
            [$rowPosition, $rowPositionOther] = $resolvePosition($oldStaffRows[$i]['position'] ?? null);

            $bulkRowsSeed[] = [
                'first_name' => $oldStaffRows[$i]['first_name'] ?? '',
                'last_name' => $oldStaffRows[$i]['last_name'] ?? '',
                'position' => $rowPosition,
                'positionOther' => $rowPositionOther,
                'email' => $oldStaffRows[$i]['email'] ?? '',
                'phone' => $oldStaffRows[$i]['phone'] ?? '',
                'is_active' => $oldStaffRows ? isset($oldStaffRows[$i]['is_active']) : true,
                'firstNameError' => $addBag->first("staff.$i.first_name"),
                'lastNameError' => $addBag->first("staff.$i.last_name"),
                'positionError' => $addBag->first("staff.$i.position"),
                'emailError' => $addBag->first("staff.$i.email"),
                'phoneError' => $addBag->first("staff.$i.phone"),
            ];
        }
    @endphp
    <script>
        function registerStaffManager() {
            if (!window.Alpine) return;

            Alpine.data('staffManager', () => ({
                addOpen: {{ ($addBag->any() || ($openAddStaff ?? false)) ? 'true' : 'false' }},
                editOpen: {{ $editBag->any() ? 'true' : 'false' }},
                deleteOpen: false,
                bulkEnabled: {{ old('staff') !== null ? 'true' : 'false' }},

                commonPositions: @json($commonPositions),

                // Resolve a position string into {position, positionOther} for the
                // dropdown — used when opening Edit on an existing staff record.
                resolvePosition(value) {
                    if (!value) {
                        return { position: '', positionOther: '' };
                    }
                    if (this.commonPositions.includes(value)) {
                        return { position: value, positionOther: '' };
                    }
                    return { position: '__other__', positionOther: value };
                },

                addSingle: {
                    first_name: @js(old('first_name', '')),
                    last_name: @js(old('last_name', '')),
                    position: @js($addSinglePosition),
                    positionOther: @js($addSinglePositionOther),
                    email: @js(old('email', '')),
                    phone: @js(old('phone', '')),
                    is_active: {{ old('first_name') !== null ? (old('is_active') ? 'true' : 'false') : 'true' }},
                    firstNameError: @js($addBag->first('first_name')),
                    lastNameError: @js($addBag->first('last_name')),
                    positionError: @js($addBag->first('position')),
                    emailError: @js($addBag->first('email')),
                    phoneError: @js($addBag->first('phone'))
                },

                bulkRows: @json($bulkRowsSeed),

                editStaff: {
                    id: @js(old('editing_id') !== null ? (int) old('editing_id') : null),
                    first_name: @js(old('first_name', '')),
                    last_name: @js(old('last_name', '')),
                    position: @js($editPosition),
                    positionOther: @js($editPositionOther),
                    email: @js(old('email', '')),
                    phone: @js(old('phone', '')),
                    is_active: {{ old('editing_id') !== null ? (old('is_active') ? 'true' : 'false') : 'true' }},
                    firstNameError: @js($editBag->first('first_name')),
                    lastNameError: @js($editBag->first('last_name')),
                    positionError: @js($editBag->first('position')),
                    emailError: @js($editBag->first('email')),
                    phoneError: @js($editBag->first('phone'))
                },

                deleteStaffId: null,

                blankRow() {
                    return {
                        first_name: '', last_name: '', position: '', positionOther: '', email: '', phone: '',
                        is_active: true,
                        firstNameError: '', lastNameError: '', positionError: '', emailError: '', phoneError: ''
                    };
                },

                openAdd() {
                    this.addOpen = true;
                    this.bulkEnabled = false;
                    this.addSingle = this.blankRow();
                    this.bulkRows = [this.blankRow(), this.blankRow()];
                },

                addBulkRow() {
                    if (this.bulkRows.length < 3) {
                        this.bulkRows.push(this.blankRow());
                    }
                },

                removeBulkRow() {
                    if (this.bulkRows.length > 1) {
                        this.bulkRows.pop();
                    }
                },

                openEdit(staff) {
                    const resolved = this.resolvePosition(staff.position ?? '');

                    this.editStaff = {
                        id: staff.id,
                        first_name: staff.first_name ?? '',
                        last_name: staff.last_name ?? '',
                        position: resolved.position,
                        positionOther: resolved.positionOther,
                        email: staff.email ?? '',
                        phone: staff.phone ?? '',
                        is_active: !!staff.is_active,
                        firstNameError: '',
                        lastNameError: '',
                        positionError: '',
                        emailError: '',
                        phoneError: ''
                    };
                    this.editOpen = true;
                },

                openDelete(id) {
                    this.deleteStaffId = id;
                    this.deleteOpen = true;
                    this.$nextTick(() => this.$refs.confirmDeleteBtn && this.$refs.confirmDeleteBtn.focus());
                }
            }));
            initializeStaffManagerTree();
        }

        function initializeStaffManagerTree() {
            if (!window.Alpine) return;

            document.querySelectorAll('[x-data="staffManager"]').forEach((element) => {
                const data = element._x_dataStack?.[0];

                // A Livewire SPA transition can replace the page before the
                // page-local Alpine factory has initialized the new root.
                // Rebuild only an incomplete root so every staff button keeps
                // its handler without duplicating an already working tree.
                if (data && typeof data.openAdd === 'function') return;

                if (data && typeof window.Alpine.destroyTree === 'function') {
                    window.Alpine.destroyTree(element);
                }

                window.Alpine.initTree(element);
            });
        }
        document.addEventListener('alpine:init', registerStaffManager);
        registerStaffManager();
        document.addEventListener('livewire:navigated', () => {
            registerStaffManager();
            window.setTimeout(initializeStaffManagerTree, 0);
        });
    </script>
    <div x-data="staffManager" class="space-y-5">
        {{-- Breadcrumb --}}
        <div class="text-sm text-gray-500 leading-6 break-words">
            <a class="text-blue-600 hover:underline" href="{{ route('admin.colleges.index') }}">Colleges</a>
            <span class="mx-1">/</span>
            <a class="text-blue-600 hover:underline" href="{{ route('admin.offices.index', $office->college) }}">
                {{ $office->college->name }}
            </a>
            <span class="mx-1">/</span>
            <span class="text-gray-700 font-medium">{{ $office->name }}</span>
            <span class="mx-1">/</span>
            <span>Staff</span>
        </div>

        {{-- Top section --}}
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Staff in {{ $office->name }}</h1>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.offices.preventiveMaintenance.export', $office) }}" data-no-spa="true"
                    class="shrink-0 inline-flex items-center rounded-xl bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700">
                    Export Excel Report
                </a>

                @if(auth()->user()->isAdmin())
                    <button type="button"
                        class="shrink-0 inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
                        @click="openAdd()">
                        + Add Staff
                    </button>
                @endif
            </div>
        </div>

        {{-- Mobile cards --}}
        <div class="grid grid-cols-1 gap-3 md:hidden">
            @forelse($staff as $s)
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <a class="font-semibold text-blue-700 hover:underline"
                                href="{{ route('admin.staff.devices.index', $s) }}">
                                {{ $s->last_name }}, {{ $s->first_name }}
                            </a>

                            <div class="mt-1 text-sm text-gray-500">
                                {{ $s->position ?: 'No position set' }}
                            </div>
                        </div>

                        <div>
                            @if($s->is_active)
                                <span class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700">
                                    Active
                                </span>
                            @else
                                <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">
                                    Inactive
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 text-sm">
                        <div>
                            <div class="text-gray-500">Email</div>
                            <div class="break-all text-gray-900">{{ $s->email ?: '-' }}</div>
                        </div>

                        <div>
                            <div class="text-gray-500">Phone</div>
                            <div class="text-gray-900">{{ $s->phone ?: '-' }}</div>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('admin.staff.devices.index', $s) }}"
                            class="rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700">
                            Equipment
                        </a>

                        @if(auth()->user()->isAdmin())
                            <button type="button"
                                class="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-black" @click="openEdit({
                                            id: {{ $s->id }},
                                            first_name: @js($s->first_name),
                                            last_name: @js($s->last_name),
                                            position: @js($s->position ?? ''),
                                            email: @js($s->email ?? ''),
                                            phone: @js($s->phone ?? ''),
                                            is_active: {{ $s->is_active ? 'true' : 'false' }}
                                        })">
                                Edit
                            </button>

                            <button type="button"
                                class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700"
                                @click="openDelete({{ $s->id }})">
                                Delete
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-gray-200 bg-white p-6 text-center text-gray-500 shadow-sm">
                    No staff found.
                </div>
            @endforelse
        </div>

        {{-- Desktop table --}}
        <div class="hidden md:block overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left">
                        <tr>
                            <th class="px-4 py-3 font-semibold text-gray-700">Name</th>
                            <th class="px-4 py-3 font-semibold text-gray-700">Position</th>
                            <th class="px-4 py-3 font-semibold text-gray-700">Email</th>
                            <th class="px-4 py-3 font-semibold text-gray-700">Phone</th>
                            <th class="px-4 py-3 font-semibold text-gray-700">Status</th>
                            <th class="px-4 py-3 font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200">
                        @forelse($staff as $s)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a class="font-medium text-blue-700 hover:underline"
                                        href="{{ route('admin.staff.devices.index', $s) }}">
                                        {{ $s->last_name }}, {{ $s->first_name }}
                                    </a>
                                </td>

                                <td class="px-4 py-3 text-gray-700">{{ $s->position ?: '-' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $s->email ?: '-' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $s->phone ?: '-' }}</td>

                                <td class="px-4 py-3">
                                    @if($s->is_active)
                                        <span
                                            class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700">
                                            Active
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">
                                            Inactive
                                        </span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.staff.devices.index', $s) }}"
                                            class="rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700">
                                            Equipment
                                        </a>

                                        @if(auth()->user()->isAdmin())
                                            <button type="button"
                                                class="rounded-lg bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-black"
                                                @click="openEdit({
                                                            id: {{ $s->id }},
                                                            first_name: @js($s->first_name),
                                                            last_name: @js($s->last_name),
                                                            position: @js($s->position ?? ''),
                                                            email: @js($s->email ?? ''),
                                                            phone: @js($s->phone ?? ''),
                                                            is_active: {{ $s->is_active ? 'true' : 'false' }}
                                                        })">
                                                Edit
                                            </button>

                                            <button type="button"
                                                class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700"
                                                @click="openDelete({{ $s->id }})">
                                                Delete
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    No staff found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            {{ $staff->links() }}
        </div>

        {{-- Add modal --}}
        <x-modal show="addOpen" title="Add Staff">
            <form method="POST" action="{{ route('admin.staff.store', $office) }}" class="space-y-3">
                @csrf

                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Add multiple staff</span>
                    <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-medium border"
                        :class="bulkEnabled ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300'"
                        @click="bulkEnabled = !bulkEnabled">
                        <span x-text="bulkEnabled ? 'Bulk: On' : 'Bulk: Off'"></span>
                    </button>
                </div>

                <div class="space-y-3">
                    <!-- Bulk controls -->
                    <div x-show="bulkEnabled" class="flex items-center gap-2">
                        <button type="button" class="rounded-lg bg-gray-100 px-3 py-1.5 text-gray-700 hover:bg-gray-200"
                            @click="removeBulkRow()">-
                        </button>

                        <div class="text-sm text-gray-700">
                            Records: <span class="font-semibold" x-text="bulkRows.length"></span>
                        </div>

                        <button type="button" class="rounded-lg bg-gray-100 px-3 py-1.5 text-gray-700 hover:bg-gray-200"
                            @click="addBulkRow()">+
                        </button>
                    </div>

                    <!-- Bulk form -->
                    <template x-if="bulkEnabled">
                        <div class="space-y-5">
                            <template x-for="(row, idx) in bulkRows" :key="idx">
                                <div class="space-y-3" :class="idx > 0 ? 'pt-4 border-t border-gray-200' : ''">
                                    <div>
                                        <label class="text-sm font-medium">First Name <span
                                                class="text-red-500">*</span></label>
                                        <input :name="`staff[${idx}][first_name]`" x-model="row.first_name"
                                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" required
                                            maxlength="100" pattern="[A-Za-zÑñ][A-Za-zÑñ.\-'\s]*"
                                            title="Letters only (no numbers or symbols other than . - ')"
                                            placeholder="e.g. Juan"
                                            @input="row.first_name = row.first_name.replace(/[^A-Za-zÑñ.\-'\s]/g, '')">
                                        <div class="mt-1 text-sm text-red-600" x-show="row.firstNameError"
                                            x-text="row.firstNameError"></div>
                                    </div>

                                    <div>
                                        <label class="text-sm font-medium">Last Name <span
                                                class="text-red-500">*</span></label>
                                        <input :name="`staff[${idx}][last_name]`" x-model="row.last_name"
                                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" required
                                            maxlength="100" pattern="[A-Za-zÑñ][A-Za-zÑñ.\-'\s]*"
                                            title="Letters only (no numbers or symbols other than . - ')"
                                            placeholder="e.g. Dela Cruz"
                                            @input="row.last_name = row.last_name.replace(/[^A-Za-zÑñ.\-'\s]/g, '')">
                                        <div class="mt-1 text-sm text-red-600" x-show="row.lastNameError"
                                            x-text="row.lastNameError"></div>
                                    </div>

                                    <div>
                                        <label class="text-sm font-medium">Position <span
                                                class="text-red-500">*</span></label>
                                        <select x-model="row.position"
                                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" required
                                            @change="row.positionError = ''">
                                            <option value="">Select position</option>
                                            <template x-for="opt in commonPositions" :key="opt">
                                                <option :value="opt" x-text="opt"></option>
                                            </template>
                                            <option value="__other__">Other (please specify)</option>
                                        </select>

                                        <input type="hidden" :name="`staff[${idx}][position]`"
                                            :value="row.position === '__other__' ? row.positionOther : row.position">

                                        <input x-show="row.position === '__other__'" x-model="row.positionOther" type="text"
                                            maxlength="100" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2"
                                            placeholder="Specify position" :required="row.position === '__other__'">
                                        <div class="mt-1 text-sm text-red-600" x-show="row.positionError"
                                            x-text="row.positionError"></div>
                                    </div>

                                    <div>
                                        <label class="text-sm font-medium">Email</label>
                                        <input :name="`staff[${idx}][email]`" x-model="row.email" type="email"
                                            maxlength="255" pattern="[^\s@]+@[^\s@]+\.[^\s@]+"
                                            title="Enter a complete email address, e.g. juan.delacruz@example.com"
                                            placeholder="e.g. juan.delacruz@example.com"
                                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                                        <div class="mt-1 text-sm text-red-600" x-show="row.emailError"
                                            x-text="row.emailError"></div>
                                    </div>

                                    <div>
                                        <label class="text-sm font-medium">Phone</label>
                                        <input :name="`staff[${idx}][phone]`" x-model="row.phone" inputmode="numeric"
                                            maxlength="11" pattern="09[0-9]{9}"
                                            title="11-digit PH mobile number starting with 09, e.g. 09171234567"
                                            placeholder="09XXXXXXXXX"
                                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"
                                            @input="row.phone = row.phone.replace(/[^0-9]/g, '').slice(0, 11)">
                                        <div class="mt-1 text-sm text-red-600" x-show="row.phoneError"
                                            x-text="row.phoneError"></div>
                                    </div>

                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" :name="`staff[${idx}][is_active]`" value="1"
                                            x-model="row.is_active">
                                        Active
                                    </label>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- Single form -->
                    <template x-if="!bulkEnabled">
                        <div class="space-y-3">
                            <div>
                                <label class="text-sm font-medium">First Name <span class="text-red-500">*</span></label>
                                <input name="first_name" x-model="addSingle.first_name"
                                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" required maxlength="100"
                                    pattern="[A-Za-zÑñ][A-Za-zÑñ.\-'\s]*"
                                    title="Letters only (no numbers or symbols other than . - ')" placeholder="e.g. Juan"
                                    @input="addSingle.first_name = addSingle.first_name.replace(/[^A-Za-zÑñ.\-'\s]/g, '')">
                                <div class="mt-1 text-sm text-red-600" x-show="addSingle.firstNameError"
                                    x-text="addSingle.firstNameError"></div>
                            </div>

                            <div>
                                <label class="text-sm font-medium">Last Name <span class="text-red-500">*</span></label>
                                <input name="last_name" x-model="addSingle.last_name"
                                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" required maxlength="100"
                                    pattern="[A-Za-zÑñ][A-Za-zÑñ.\-'\s]*"
                                    title="Letters only (no numbers or symbols other than . - ')"
                                    placeholder="e.g. Dela Cruz"
                                    @input="addSingle.last_name = addSingle.last_name.replace(/[^A-Za-zÑñ.\-'\s]/g, '')">
                                <div class="mt-1 text-sm text-red-600" x-show="addSingle.lastNameError"
                                    x-text="addSingle.lastNameError"></div>
                            </div>

                            <div>
                                <label class="text-sm font-medium">Position <span class="text-red-500">*</span></label>
                                <select x-model="addSingle.position"
                                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2" required
                                    @change="addSingle.positionError = ''">
                                    <option value="">Select position</option>
                                    <template x-for="opt in commonPositions" :key="opt">
                                        <option :value="opt" x-text="opt"></option>
                                    </template>
                                    <option value="__other__">Other (please specify)</option>
                                </select>

                                <input type="hidden" name="position"
                                    :value="addSingle.position === '__other__' ? addSingle.positionOther : addSingle.position">

                                <input x-show="addSingle.position === '__other__'" x-model="addSingle.positionOther"
                                    type="text" maxlength="100"
                                    class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2"
                                    placeholder="Specify position" :required="addSingle.position === '__other__'">
                                <div class="mt-1 text-sm text-red-600" x-show="addSingle.positionError"
                                    x-text="addSingle.positionError"></div>
                            </div>

                            <div>
                                <label class="text-sm font-medium">Email</label>
                                <input name="email" type="email" x-model="addSingle.email" maxlength="255"
                                    pattern="[^\s@]+@[^\s@]+\.[^\s@]+"
                                    title="Enter a complete email address, e.g. juan.delacruz@example.com"
                                    placeholder="e.g. juan.delacruz@example.com"
                                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                                <div class="mt-1 text-sm text-red-600" x-show="addSingle.emailError"
                                    x-text="addSingle.emailError"></div>
                            </div>

                            <div>
                                <label class="text-sm font-medium">Phone</label>
                                <input name="phone" x-model="addSingle.phone" inputmode="numeric" maxlength="11"
                                    pattern="09[0-9]{9}"
                                    title="11-digit PH mobile number starting with 09, e.g. 09171234567"
                                    placeholder="09XXXXXXXXX"
                                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"
                                    @input="addSingle.phone = addSingle.phone.replace(/[^0-9]/g, '').slice(0, 11)">
                                <div class="mt-1 text-sm text-red-600" x-show="addSingle.phoneError"
                                    x-text="addSingle.phoneError"></div>
                            </div>

                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="is_active" value="1" x-model="addSingle.is_active">
                                Active
                            </label>
                        </div>
                    </template>
                </div>

                <div class="flex gap-2 pt-2">
                    <button class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Save</button>
                    <button type="button" class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200"
                        @click="addOpen=false">Cancel</button>
                </div>
            </form>
        </x-modal>

        {{-- Edit modal --}}
        <x-modal show="editOpen" title="Edit Staff">
            <form method="POST" :action="'{{ route('admin.staff.update', ['office' => $office->id, 'staff' => '__STAFF__']) }}'.replace('__STAFF__', editStaff.id)" class="space-y-3">
                @csrf
                @method('PUT')

                <input type="hidden" name="editing_id" :value="editStaff.id">

                <div>
                    <label class="text-sm font-medium">First Name <span class="text-red-500">*</span></label>
                    <input name="first_name" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"
                        x-model="editStaff.first_name" required maxlength="100" pattern="[A-Za-zÑñ][A-Za-zÑñ.\-'\s]*"
                        title="Letters only (no numbers or symbols other than . - ')"
                        @input="editStaff.first_name = editStaff.first_name.replace(/[^A-Za-zÑñ.\-'\s]/g, '')">
                    <div class="mt-1 text-sm text-red-600" x-show="editStaff.firstNameError"
                        x-text="editStaff.firstNameError"></div>
                </div>

                <div>
                    <label class="text-sm font-medium">Last Name <span class="text-red-500">*</span></label>
                    <input name="last_name" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"
                        x-model="editStaff.last_name" required maxlength="100" pattern="[A-Za-zÑñ][A-Za-zÑñ.\-'\s]*"
                        title="Letters only (no numbers or symbols other than . - ')"
                        @input="editStaff.last_name = editStaff.last_name.replace(/[^A-Za-zÑñ.\-'\s]/g, '')">
                    <div class="mt-1 text-sm text-red-600" x-show="editStaff.lastNameError"
                        x-text="editStaff.lastNameError"></div>
                </div>

                <div>
                    <label class="text-sm font-medium">Position <span class="text-red-500">*</span></label>
                    <select x-model="editStaff.position" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"
                        required @change="editStaff.positionError = ''">
                        <option value="">Select position</option>
                        <template x-for="opt in commonPositions" :key="opt">
                            <option :value="opt" x-text="opt"></option>
                        </template>
                        <option value="__other__">Other (please specify)</option>
                    </select>

                    <input type="hidden" name="position"
                        :value="editStaff.position === '__other__' ? editStaff.positionOther : editStaff.position">

                    <input x-show="editStaff.position === '__other__'" x-model="editStaff.positionOther" type="text"
                        maxlength="100" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2"
                        placeholder="Specify position" :required="editStaff.position === '__other__'">
                    <div class="mt-1 text-sm text-red-600" x-show="editStaff.positionError"
                        x-text="editStaff.positionError"></div>
                </div>


                <div>
                    <label class="text-sm font-medium">Email</label>
                    <input name="email" type="email" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"
                        x-model="editStaff.email" maxlength="255" pattern="[^\s@]+@[^\s@]+\.[^\s@]+"
                        title="Enter a complete email address, e.g. juan.delacruz@example.com">
                    <div class="mt-1 text-sm text-red-600" x-show="editStaff.emailError" x-text="editStaff.emailError">
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium">Phone</label>
                    <input name="phone" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2"
                        x-model="editStaff.phone" inputmode="numeric" maxlength="11" pattern="09[0-9]{9}"
                        title="11-digit PH mobile number starting with 09, e.g. 09171234567" placeholder="09XXXXXXXXX"
                        @input="editStaff.phone = editStaff.phone.replace(/[^0-9]/g, '').slice(0, 11)">
                    <div class="mt-1 text-sm text-red-600" x-show="editStaff.phoneError" x-text="editStaff.phoneError">
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" x-model="editStaff.is_active">
                    Active
                </label>

                <div class="flex gap-2 pt-2">
                    <button class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Update</button>
                    <button type="button" class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200"
                        @click="editOpen=false">Cancel</button>
                </div>
            </form>
        </x-modal>

        {{-- Delete modal --}}
        <x-modal show="deleteOpen" title="Delete Staff">
            <div class="space-y-3">
                <div class="text-sm text-gray-700">
                    Are you sure you want to delete this staff member?
                </div>

                <form method="POST" :action="'{{ route('admin.staff.destroy', ['office' => $office->id, 'staff' => '__STAFF__']) }}'.replace('__STAFF__', deleteStaffId)"
                    @submit="if (!deleteStaffId) $event.preventDefault()" class="flex gap-2">
                    @csrf
                    @method('DELETE')

                    <button type="submit" x-ref="confirmDeleteBtn"
                        class="rounded-lg bg-red-600 px-4 py-2 text-white hover:bg-red-700">Confirm</button>
                    <button type="button" class="rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200"
                        @click="deleteOpen=false">Cancel</button>
                </form>
            </div>
        </x-modal>
    </div>
@endsection
