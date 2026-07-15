<?php

namespace App\Livewire\Admin;

use App\Models\College;
use App\Models\Office;
use App\Models\Staff;
use App\Models\Device;
use App\Models\DeviceAssignment;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CollegeBrowser extends Component
{
    public ?int $selectedCollegeId = null;
    public ?int $selectedOfficeId  = null;
    public ?int $selectedStaffId   = null;

    // Lists
    public $colleges = [];
    public $offices  = [];
    public $staff    = [];
    public $issuedDevices = [];

    // College form
    public ?int $editingCollegeId = null;
    public string $collegeName = '';
    public string $collegeCode = '';

    // Office form
    public ?int $editingOfficeId = null;
    public string $officeName = '';

    // Staff form
    public ?int $editingStaffId = null;
    public string $staffFirstName = '';
    public string $staffLastName  = '';
    public string $staffPosition  = '';
    public string $staffEmail     = '';
    public string $staffPhone     = '';
    public bool $staffIsActive    = true;

    // Issue equipment
    public ?int $issueDeviceId = null;
    public $availableDevices = [];

    public function mount(): void
    {
        $this->loadColleges();
    }

    /* -------------------- Loaders -------------------- */

    private function loadColleges(): void
    {
        $this->colleges = College::orderBy('name')->get();
    }

    private function loadOffices(): void
    {
        $this->offices = $this->selectedCollegeId
            ? Office::where('college_id', $this->selectedCollegeId)->orderBy('name')->get()
            : collect();
    }

    private function loadStaff(): void
    {
        $this->staff = $this->selectedOfficeId
            ? Staff::where('office_id', $this->selectedOfficeId)
                ->orderBy('last_name')->orderBy('first_name')->get()
            : collect();
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

        $this->issuedDevices = $assignments->map(fn($a) => [
            'assignment_id' => $a->id,
            'issued_at' => optional($a->issued_at)->format('Y-m-d H:i'),
            'type' => $a->device->type->name ?? '-',
            'property_number' => $a->device->property_number,
            'brand' => $a->device->brand ?? '-',
            'model' => $a->device->model ?? '-',
            'unit_price' => $a->device->unit_price,
        ])->toArray();
    }

    private function loadAvailableDevices(): void
    {
        $this->availableDevices = Device::query()
            ->with('type')
            ->where('status', 'available')
            ->orderBy('property_number')
            ->limit(300)
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'label' => ($d->type->name ?? 'Equipment') . " | {$d->property_number}"
                    . ($d->brand ? " | {$d->brand}" : '')
                    . ($d->model ? " {$d->model}" : ''),
            ])
            ->toArray();
    }

    /* -------------------- Selection -------------------- */

    public function selectCollege(int $id): void
    {
        $this->selectedCollegeId = $id;
        $this->selectedOfficeId = null;
        $this->selectedStaffId = null;
        $this->issuedDevices = [];
        $this->loadOffices();
        $this->staff = collect();

        // reset forms
        $this->resetOfficeForm();
        $this->resetStaffForm();
    }

    public function selectOffice(int $id): void
    {
        $this->selectedOfficeId = $id;
        $this->selectedStaffId = null;
        $this->issuedDevices = [];
        $this->loadStaff();

        $this->resetStaffForm();
    }

    public function selectStaff(int $id): void
    {
        $this->selectedStaffId = $id;
        $this->loadIssuedDevices();
        $this->loadAvailableDevices();
    }

    /* -------------------- College CRUD -------------------- */

    public function startCreateCollege(): void
    {
        $this->editingCollegeId = null;
        $this->collegeName = '';
        $this->collegeCode = '';
    }

    public function startEditCollege(int $id): void
    {
        $c = College::findOrFail($id);
        $this->editingCollegeId = $c->id;
        $this->collegeName = $c->name;
        $this->collegeCode = $c->code ?? '';
    }

    public function saveCollege(): void
    {
        $rules = [
            'collegeName' => ['required', 'string', 'max:255'],
            'collegeCode' => ['nullable', 'string', 'max:255'],
        ];
        $this->validate($rules);

        if ($this->editingCollegeId) {
            College::whereKey($this->editingCollegeId)->update([
                'name' => $this->collegeName,
                'code' => $this->collegeCode ?: null,
            ]);
        } else {
            College::create([
                'name' => $this->collegeName,
                'code' => $this->collegeCode ?: null,
            ]);
        }

        $this->startCreateCollege();
        $this->loadColleges();

        session()->flash('success', 'College saved.');
    }

    public function deleteCollege(int $id): void
    {
        College::whereKey($id)->delete();

        if ($this->selectedCollegeId === $id) {
            $this->selectedCollegeId = null;
            $this->selectedOfficeId = null;
            $this->selectedStaffId = null;
            $this->offices = collect();
            $this->staff = collect();
            $this->issuedDevices = [];
        }

        $this->loadColleges();
        session()->flash('success', 'College deleted.');
    }

    /* -------------------- Office CRUD -------------------- */

    public function resetOfficeForm(): void
    {
        $this->editingOfficeId = null;
        $this->officeName = '';
    }

    public function startEditOffice(int $id): void
    {
        $o = Office::findOrFail($id);
        $this->editingOfficeId = $o->id;
        $this->officeName = $o->name;
    }

    public function saveOffice(): void
    {
        if (!$this->selectedCollegeId) return;

        $this->validate([
            'officeName' => ['required', 'string', 'max:255'],
        ]);

        if ($this->editingOfficeId) {
            Office::whereKey($this->editingOfficeId)->update([
                'name' => $this->officeName,
            ]);
        } else {
            Office::create([
                'college_id' => $this->selectedCollegeId,
                'name' => $this->officeName,
            ]);
        }

        $this->resetOfficeForm();
        $this->loadOffices();

        session()->flash('success', 'Office saved.');
    }

    public function deleteOffice(int $id): void
    {
        Office::whereKey($id)->delete();

        if ($this->selectedOfficeId === $id) {
            $this->selectedOfficeId = null;
            $this->selectedStaffId = null;
            $this->staff = collect();
            $this->issuedDevices = [];
        }

        $this->loadOffices();
        session()->flash('success', 'Office deleted.');
    }

    /* -------------------- Staff CRUD -------------------- */

    public function resetStaffForm(): void
    {
        $this->editingStaffId = null;
        $this->staffFirstName = '';
        $this->staffLastName  = '';
        $this->staffPosition  = '';
        $this->staffEmail     = '';
        $this->staffPhone     = '';
        $this->staffIsActive  = true;
    }

    public function startEditStaff(int $id): void
    {
        $s = Staff::findOrFail($id);
        $this->editingStaffId = $s->id;
        $this->staffFirstName = $s->first_name;
        $this->staffLastName  = $s->last_name;
        $this->staffPosition  = $s->position ?? '';
        $this->staffEmail     = $s->email ?? '';
        $this->staffPhone     = $s->phone ?? '';
        $this->staffIsActive  = (bool) $s->is_active;
    }

    public function saveStaff(): void
    {
        if (!$this->selectedOfficeId) return;

        $this->validate([
            'staffFirstName' => ['required', 'string', 'max:255'],
            'staffLastName'  => ['required', 'string', 'max:255'],
            'staffPosition'  => ['nullable', 'string', 'max:255'],
            'staffEmail'     => ['nullable', 'email', 'max:255'],
            'staffPhone'     => ['nullable', 'string', 'max:255'],
            'staffIsActive'  => ['boolean'],
        ]);

        $payload = [
            'office_id' => $this->selectedOfficeId,
            'first_name' => $this->staffFirstName,
            'last_name' => $this->staffLastName,
            'position' => $this->staffPosition ?: null,
            'email' => $this->staffEmail ?: null,
            'phone' => $this->staffPhone ?: null,
            'is_active' => $this->staffIsActive,
        ];

        if ($this->editingStaffId) {
            Staff::whereKey($this->editingStaffId)->update($payload);
        } else {
            Staff::create($payload);
        }

        $this->resetStaffForm();
        $this->loadStaff();

        session()->flash('success', 'Staff saved.');
    }

    public function deleteStaff(int $id): void
    {
        Staff::whereKey($id)->delete();

        if ($this->selectedStaffId === $id) {
            $this->selectedStaffId = null;
            $this->issuedDevices = [];
        }

        $this->loadStaff();
        session()->flash('success', 'Staff deleted.');
    }

    /* -------------------- Issue / Return -------------------- */

    public function issueSelectedDevice(): void
    {
        if (!$this->selectedStaffId || !$this->issueDeviceId) return;

        $device = Device::find($this->issueDeviceId);
        if (!$device || $device->status !== 'available') return;

        DeviceAssignment::create([
            'device_id' => $device->id,
            'staff_id' => $this->selectedStaffId,
            'issued_by' => Auth::id(),
            'issued_at' => now(),
        ]);

        $device->update(['status' => 'issued']);

        $this->issueDeviceId = null;
        $this->loadIssuedDevices();
        $this->loadAvailableDevices();

        session()->flash('success', 'Equipment issued.');
    }

    public function returnDevice(int $assignmentId): void
    {
        $a = DeviceAssignment::with('device')->find($assignmentId);
        if (!$a || $a->returned_at) return;

        $a->update(['returned_at' => now()]);
        if ($a->device) $a->device->update(['status' => 'available']);

        $this->loadIssuedDevices();
        $this->loadAvailableDevices();

        session()->flash('success', 'Equipment returned.');
    }

    public function render()
    {
        // keep lists fresh
        $this->loadColleges();
        $this->loadOffices();
        $this->loadStaff();

        return view('livewire.admin.college-browser');
    }
}
