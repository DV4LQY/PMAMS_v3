<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\DeviceMaintenanceRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeviceChecklistController extends Controller
{
    public function create(Device $device)
    {
        $device->load([
            'type',
            'currentAssignment.staff.office.college',
        ]);

        return view('admin.devices.checklist-form', [
            'device' => $device,
            'checklistItems' => $this->checklistItems(),
            'softwareItems' => $this->softwareItems(),
            'defaultDate' => now()->toDateString(),
        ]);
    }

    public function form(Device $device)
    {
        return $this->create($device);
    }

    public function store(Request $request, Device $device)
    {
        $data = $request->validate([
            'date_checked' => ['nullable', 'date', 'before_or_equal:today'],
            'maintenance_date' => ['nullable', 'date', 'before_or_equal:today'],

            'hardware' => ['nullable', 'array'],
            'hardware.*' => ['nullable', 'string', 'in:OK,Not OK'],

            'software' => ['nullable', 'array'],
            'software.*' => ['nullable', 'string', 'in:check,dash'],

            'remarks' => ['nullable', 'string', 'max:1000'],
            'corrective_action' => ['nullable', 'string', 'max:1000'],
        ]);

        $dateChecked = $data['date_checked']
            ?? $data['maintenance_date']
            ?? now()->toDateString();

        $hardwareResponses = $data['hardware'] ?? [];
        $softwareResponses = $data['software'] ?? [];
        $remarks = $data['remarks'] ?? 'Preventive maintenance checklist completed.';
        $correctiveAction = $data['corrective_action'] ?? null;

        $record = DeviceMaintenanceRecord::create([
            'device_id' => $device->id,
            'maintenance_date' => $dateChecked,
            'maintenance_type' => 'Checked',
            'condition' => $device->condition ?? 'serviceable',
            'remarks' => $remarks,
            'corrective_action' => $correctiveAction,
            'checklist_data' => [
                'hardware' => $hardwareResponses,
                'software' => $softwareResponses,
            ],
            'checked_by' => Auth::id(),
        ]);

        $device->update([
            'last_maintenance_date' => $dateChecked,
            'maintenance_remarks' => $remarks,
        ]);

        ActivityLog::record(
            'updated',
            "Marked device \"{$device->property_number}\" as checked with checklist",
            $device
        );

        return redirect()
            ->route('admin.devices.show', $device)
            ->with('success', 'Equipment has been marked as checked. Checklist saved.');
    }

    public function generate(Request $request, Device $device)
    {
        return $this->store($request, $device);
    }

    public function generatePdf(Request $request, Device $device)
    {
        return $this->store($request, $device);
    }

    private function checklistItems(): array
    {
        return [
            'system_unit_power_on' => [
                'group' => 'System Unit',
                'label' => 'Check for power on',
            ],
            'monitor_display' => [
                'group' => 'Monitor',
                'label' => 'Check display',
            ],
            'keyboard_keys' => [
                'group' => 'Keyboard',
                'label' => 'Check for keys',
            ],
            'mouse_buttons' => [
                'group' => 'Mouse',
                'label' => 'Check mouse left/right buttons',
            ],
            'avr_ups_power_recovery' => [
                'group' => 'AVR/UPS',
                'label' => 'Check for power recovery',
            ],
            'printer_printout' => [
                'group' => 'Printer',
                'label' => 'Check printout',
            ],
        ];
    }

    private function softwareItems(): array
    {
        return [
            'setup_antivirus' => 'Setup Anti-Virus',
            'system_scan_removal' => 'System Scan and Removal of Malicious Software',
        ];
    }
}
