<div class="space-y-4">
    @if (session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-2 rounded">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white p-4 rounded shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="text-sm font-medium">College</label>
                <select wire:model="selectedCollegeId" class="mt-1 w-full border rounded px-3 py-2">
                    <option value="">Select college...</option>
                    @foreach ($colleges as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-sm font-medium">Office</label>
                <select wire:model="selectedOfficeId" class="mt-1 w-full border rounded px-3 py-2" @disabled(!$selectedCollegeId)>
                    <option value="">Select office...</option>
                    @foreach ($offices as $o)
                        <option value="{{ $o->id }}">{{ $o->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-sm font-medium">Staff</label>
                <select wire:model="selectedStaffId" class="mt-1 w-full border rounded px-3 py-2" @disabled(!$selectedOfficeId)>
                    <option value="">Select staff...</option>
                    @foreach ($staffList as $s)
                        <option value="{{ $s->id }}">
                            {{ $s->last_name }}, {{ $s->first_name }}{{ $s->position ? ' - '.$s->position : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="bg-white p-4 rounded shadow-sm">
        <div class="flex flex-col md:flex-row gap-3 md:items-end">
            <div class="flex-1">
                <label class="text-sm font-medium">Issue available equipment to selected staff</label>
                <select wire:model="issueDeviceId" class="mt-1 w-full border rounded px-3 py-2" @disabled(!$selectedStaffId)>
                    <option value="">Select equipment...</option>
                    @foreach ($availableDevices as $d)
                        <option value="{{ $d['id'] }}">{{ $d['label'] }}</option>
                    @endforeach
                </select>
                @error('issueDeviceId')
                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            <button wire:click="issueSelectedDevice"
                    class="px-4 py-2 rounded bg-blue-600 text-white text-sm"
                    @disabled(!$selectedStaffId)>
                Issue
            </button>
        </div>
    </div>

    <div class="bg-white p-4 rounded shadow-sm">
        <h2 class="text-lg font-semibold mb-3">Issued Equipment</h2>

        @if (!$selectedStaffId)
            <div class="text-gray-600">Select a staff member to view issued equipment.</div>
        @else
            @if (count($issuedDevices) === 0)
                <div class="text-gray-600">No equipment currently issued.</div>
            @else
                <div class="overflow-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left bg-gray-50">
                            <tr>
                                <th class="p-2 border">Type</th>
                                <th class="p-2 border">Property #</th>
                                <th class="p-2 border">Brand</th>
                                <th class="p-2 border">Model</th>
                                <th class="p-2 border">Unit Price</th>
                                <th class="p-2 border">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($issuedDevices as $row)
                                <tr>
                                    <td class="p-2 border">{{ $row['type'] }}</td>
                                    <td class="p-2 border">{{ $row['property_number'] }}</td>
                                    <td class="p-2 border">{{ $row['brand'] }}</td>
                                    <td class="p-2 border">{{ $row['model'] }}</td>
                                    <td class="p-2 border">
                                        {{ $row['unit_price'] !== null ? number_format((float)$row['unit_price'], 2) : '-' }}
                                    </td>
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