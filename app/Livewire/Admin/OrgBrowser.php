<?php

namespace App\Livewire\Admin;

use App\Models\College;
use App\Models\Office;
use App\Models\Staff;
use App\Models\Device;
use App\Models\DeviceAssignment;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class OrgBrowser extends Component
{
    public $colleges = [];
    public $offices = [];
    public $staffList = [];

    public ?int $selectedCollegeId = null;
    public ?int $selectedOfficeId = null;
    public ?int $selectedStaffId = null;

    public $issuedDevices = [];

    public ?int $issueDeviceId = null;
    public $availableDevices = [];

    public function mount(): void
    {
        $this->colleges = College::orderBy('name')->get();
    }

    public function updatedSelectedCollegeId($value): void
    {
        $this->reset(['selectedOfficeId', 'selectedStaffId', 'issuedDevices', 'offices', 'staffList']);
        if ($value) {
            $this->offices = Office::where('college_id', $value)->orderBy('name')->get();
        }
    }

    public function updatedSelectedOfficeId($value): void
    {
        $this->reset(['selectedStaffId', 'issuedDevices', 'staffList']);
        if ($value) {
            $this->staffList = Staff::where('office_id', $value)
                ->where('is_active', true)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();
        }
    }

    public function updatedSelectedStaffId($value): void
    {
        $this->loadIssuedDevices();
        $this->loadAvailableDevices();
    }

    private function loadIssuedDevices(): void
    {
        $this->issuedDevices = [];

        if (!$this->selectedStaffId) return;

        $assignments = DeviceAssignment::query()
            ->where('staff_id', $this->selectedStaffId)
            ->whereNull('returned_at')
            ->with(['device.type'])
            ->orderByDesc('issued_at')
            ->get();

        $this->issuedDevices = $assignments->map(function ($a) {
            return [
                'assignment_id' => $a->id,
                'issued_at' => optional($a->issued_at)->format('Y-m-d H:i'),
                'type' => $a->device->type->name ?? '-',
                'property_number' => $a->device->property_number,
                'brand' => $a->device->brand ?? '-',
                'model' => $a->device->model ?? '-',
                'unit_price' => $a->device->unit_price,
            ];
        })->toArray();
    }

    private function loadAvailableDevices(): void
    {
        $this->availableDevices = Device::query()
            ->with('type')
            ->where('status', 'available')
            ->orderBy('property_number')
            ->limit(200)
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'label' => ($d->type->name ?? 'Equipment') . " | {$d->property_number}" .
                    ($d->brand ? " | {$d->brand}" : '') .
                    ($d->model ? " {$d->model}" : ''),
            ])
            ->toArray();
    }

    public function issueSelectedDevice(): void
    {
        if (!$this->selectedStaffId || !$this->issueDeviceId) {
            $this->addError('issueDeviceId', 'Select a staff member and equipment.');
            return;
        }

        $device = Device::find($this->issueDeviceId);
        if (!$device || $device->status !== 'available') {
            $this->addError('issueDeviceId', 'Equipment is not available.');
            return;
        }

        $staff = Staff::with('office')->find($this->selectedStaffId);
        if (!$staff) {
            $this->addError('issueDeviceId', 'The selected staff member could not be found.');
            return;
        }

        DeviceAssignment::create([
            'device_id' => $device->id,
            'staff_id' => $staff->id,
            'office_id' => $staff->office_id,
            'location_id' => $staff->office?->location_id,
            'issued_by' => Auth::id(),
            'issued_at' => now(),
        ]);

        $device->update(['status' => 'issued']);

        $this->issueDeviceId = null;
        $this->loadIssuedDevices();
        $this->loadAvailableDevices();

        session()->flash('success', 'Equipment issued successfully.');
    }

    public function returnDevice(int $assignmentId): void
    {
        $assignment = DeviceAssignment::with('device')->find($assignmentId);
        if (!$assignment || $assignment->returned_at) return;

        $assignment->update(['returned_at' => now()]);

        if ($assignment->device) {
            $assignment->device->update(['status' => 'available']);
        }

        $this->loadIssuedDevices();
        $this->loadAvailableDevices();

        session()->flash('success', 'Equipment returned successfully.');
    }

    public function render()
    {
        return view('livewire.admin.org-browser');
    }
}
