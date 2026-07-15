<div class="space-y-4">
    @if (session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-2 rounded">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- LEFT: Browser lists --}}
        <div class="lg:col-span-1 space-y-4">

            {{-- Colleges --}}
            <div class="bg-white rounded shadow-sm p-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold">Colleges</h2>
                    <button wire:click="startCreateCollege" class="text-sm px-3 py-1 rounded bg-gray-900 text-white">
                        + Add
                    </button>
                </div>

                <div class="space-y-1">
                    @foreach($colleges as $c)
                        <div class="flex items-center justify-between p-2 rounded hover:bg-gray-50
                            {{ $selectedCollegeId === $c->id ? 'bg-gray-100' : '' }}">
                            <button class="text-left flex-1" wire:click="selectCollege({{ $c->id }})">
                                {{ $c->name }}
                            </button>
                            <div class="flex gap-1">
                                <button wire:click="startEditCollege({{ $c->id }})" class="text-xs px-2 py-1 rounded bg-blue-600 text-white">Edit</button>
                                <button wire:click="deleteCollege({{ $c->id }})" class="text-xs px-2 py-1 rounded bg-red-600 text-white"
                                        onclick="return confirm('Delete this college?')">Del</button>
                            </div>
                        </div>
                    @endforeach

                    @if($colleges->count() === 0)
                        <div class="text-sm text-gray-600">No colleges yet.</div>
                    @endif
                </div>

                <div class="mt-4 border-t pt-4">
                    <div class="font-medium mb-2">{{ $editingCollegeId ? 'Edit College' : 'Add College' }}</div>
                    <div class="space-y-2">
                        <input wire:model="collegeName" class="w-full border rounded px-3 py-2" placeholder="College name">
                        <input wire:model="collegeCode" class="w-full border rounded px-3 py-2" placeholder="Code (optional)">
                        <button wire:click="saveCollege" class="w-full px-3 py-2 rounded bg-gray-900 text-white">
                            Save
                        </button>
                    </div>
                </div>
            </div>

            {{-- Offices --}}
            <div class="bg-white rounded shadow-sm p-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold">Offices</h2>
                    <button wire:click="resetOfficeForm" class="text-sm px-3 py-1 rounded bg-gray-900 text-white"
                            @disabled(!$selectedCollegeId)>
                        + Add
                    </button>
                </div>

                @if(!$selectedCollegeId)
                    <div class="text-sm text-gray-600">Select a college first.</div>
                @else
                    <div class="space-y-1">
                        @foreach($offices as $o)
                            <div class="flex items-center justify-between p-2 rounded hover:bg-gray-50
                                {{ $selectedOfficeId === $o->id ? 'bg-gray-100' : '' }}">
                                <button class="text-left flex-1" wire:click="selectOffice({{ $o->id }})">
                                    {{ $o->name }}
                                </button>
                                <div class="flex gap-1">
                                    <button wire:click="startEditOffice({{ $o->id }})" class="text-xs px-2 py-1 rounded bg-blue-600 text-white">Edit</button>
                                    <button wire:click="deleteOffice({{ $o->id }})" class="text-xs px-2 py-1 rounded bg-red-600 text-white"
                                            onclick="return confirm('Delete this office?')">Del</button>
                                </div>
                            </div>
                        @endforeach
                        @if($offices->count() === 0)
                            <div class="text-sm text-gray-600">No offices yet.</div>
                        @endif
                    </div>

                    <div class="mt-4 border-t pt-4">
                        <div class="font-medium mb-2">{{ $editingOfficeId ? 'Edit Office' : 'Add Office' }}</div>
                        <div class="space-y-2">
                            <input wire:model="officeName" class="w-full border rounded px-3 py-2" placeholder="Office name">
                            <button wire:click="saveOffice" class="w-full px-3 py-2 rounded bg-gray-900 text-white">
                                Save
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Staff --}}
            <div class="bg-white rounded shadow-sm p-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold">Staff</h2>
                    <button wire:click="resetStaffForm" class="text-sm px-3 py-1 rounded bg-gray-900 text-white"
                            @disabled(!$selectedOfficeId)>
                        + Add
                    </button>
                </div>

                @if(!$selectedOfficeId)
                    <div class="text-sm text-gray-600">Select an office first.</div>
                @else
                    <div class="space-y-1">
                        @foreach($staff as $s)
                            <div class="flex items-center justify-between p-2 rounded hover:bg-gray-50
                                {{ $selectedStaffId === $s->id ? 'bg-gray-100' : '' }}">
                                <button class="text-left flex-1" wire:click="selectStaff({{ $s->id }})">
                                    {{ $s->last_name }}, {{ $s->first_name }}
                                </button>
                                <div class="flex gap-1">
                                    <button wire:click="startEditStaff({{ $s->id }})" class="text-xs px-2 py-1 rounded bg-blue-600 text-white">Edit</button>
                                    <button wire:click="deleteStaff({{ $s->id }})" class="text-xs px-2 py-1 rounded bg-red-600 text-white"
                                            onclick="return confirm('Delete this staff?')">Del</button>
                                </div>
                            </div>
                        @endforeach
                        @if($staff->count() === 0)
                            <div class="text-sm text-gray-600">No staff yet.</div>
                        @endif
                    </div>

                    <div class="mt-4 border-t pt-4">
                        <div class="font-medium mb-2">{{ $editingStaffId ? 'Edit Staff' : 'Add Staff' }}</div>
                        <div class="grid grid-cols-1 gap-2">
                            <input wire:model="staffFirstName" class="w-full border rounded px-3 py-2" placeholder="First name">
                            <input wire:model="staffLastName" class="w-full border rounded px-3 py-2" placeholder="Last name">
                            <input wire:model="staffPosition" class="w-full border rounded px-3 py-2" placeholder="Position (optional)">
                            <input wire:model="staffEmail" class="w-full border rounded px-3 py-2" placeholder="Email (optional)">
                            <input wire:model="staffPhone" class="w-full border rounded px-3 py-2" placeholder="Phone (optional)">

                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="staffIsActive">
                                Active
                            </label>

                            <button wire:click="saveStaff" class="w-full px-3 py-2 rounded bg-gray-900 text-white">
                                Save
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- RIGHT: Issued equipment for selected staff --}}
        <div class="lg:col-span-2 space-y-4">

            <div class="bg-white rounded shadow-sm p-4">
                <h2 class="font-semibold mb-3">Issued Equipment</h2>

                @if(!$selectedStaffId)
                    <div class="text-sm text-gray-600">Select a staff member to view issued equipment.</div>
                @else
                    <div class="flex gap-2 items-end mb-4">
                        <div class="flex-1">
                            <label class="text-sm font-medium">Issue available equipment</label>
                            <select wire:model="issueDeviceId" class="mt-1 w-full border rounded px-3 py-2">
                                <option value="">Select equipment...</option>
                                @foreach($availableDevices as $d)
                                    <option value="{{ $d['id'] }}">{{ $d['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <button wire:click="issueSelectedDevice" class="px-4 py-2 rounded bg-blue-600 text-white">
                            Issue
                        </button>
                    </div>

                    @if(count($issuedDevices) === 0)
                        <div class="text-sm text-gray-600">No equipment currently issued.</div>
                    @else
                        <div class="overflow-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 text-left">
                                    <tr>
                                        <th class="p-2 border">Type</th>
                                        <th class="p-2 border">Property #</th>
                                        <th class="p-2 border">Brand</th>
                                        <th class="p-2 border">Model</th>
                                        <th class="p-2 border">Unit Price</th>
                                        <th class="p-2 border">Issued At</th>
                                        <th class="p-2 border">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($issuedDevices as $row)
                                        <tr>
                                            <td class="p-2 border">{{ $row['type'] }}</td>
                                            <td class="p-2 border">{{ $row['property_number'] }}</td>
                                            <td class="p-2 border">{{ $row['brand'] }}</td>
                                            <td class="p-2 border">{{ $row['model'] }}</td>
                                            <td class="p-2 border">
                                                {{ $row['unit_price'] !== null ? number_format((float)$row['unit_price'], 2) : '-' }}
                                            </td>
                                            <td class="p-2 border">{{ $row['issued_at'] }}</td>
                                            <td class="p-2 border">
                                                <button wire:click="returnDevice({{ $row['assignment_id'] }})"
                                                        class="px-3 py-1 rounded bg-gray-900 text-white">
                                                    Return
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </div>

        </div>
    </div>
</div>