<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\DeviceMaintenanceRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DeviceChecklistController extends Controller
{
    public function create(Device $device)
    {
        $device->load([
            'type',
            'currentAssignment.staff.office.location',
            'currentAssignment.location',
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
        $hardwareRules = [];
        $softwareRules = [];
        foreach ($this->checklistItems() as $key => $item) {
            $allowedValues = ['OK', 'Not OK'];
            if ($item['not_available'] ?? false) {
                $allowedValues[] = 'Not Available';
            }

            $hardwareRules["hardware.{$key}"] = ['required', 'string', Rule::in($allowedValues)];
        }

        foreach ($this->softwareItems() as $key => $label) {
            $softwareRules["software.{$key}"] = ['required', 'string', Rule::in(['check', 'dash'])];
        }

        $data = $request->validate(array_merge([
            'date_checked' => ['nullable', 'date', 'before_or_equal:today'],
            'maintenance_date' => ['nullable', 'date', 'before_or_equal:today'],

            'hardware' => ['required', 'array'],

            'software' => ['required', 'array'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'corrective_action' => ['nullable', 'string', 'max:1000'],
            'confirm_duplicate' => ['nullable', 'boolean'],
            'verification_reason' => ['required', 'string', 'max:1000'],
        ], $hardwareRules, $softwareRules));

        $dateChecked = $data['date_checked']
            ?? $data['maintenance_date']
            ?? now()->toDateString();

        $hardwareResponses = array_intersect_key(
            $data['hardware'] ?? [],
            $this->checklistItems()
        );
        $softwareResponses = array_intersect_key(
            $data['software'] ?? [],
            $this->softwareItems()
        );
        $remarks = trim((string) ($data['remarks'] ?? ''));
        $correctiveAction = trim((string) ($data['corrective_action'] ?? ''));
        $verificationReason = trim((string) $data['verification_reason']);

        // A checklist can be submitted again, but only after the user has
        // explicitly confirmed that it is a verification within the same
        // three-month maintenance window. Every submission remains a new
        // maintenance record; this query is only used for the warning.
        $date = Carbon::parse($dateChecked);
        $duplicateRecords = $device->maintenanceRecords()
            ->whereDate('maintenance_date', '>=', $date->copy()->subMonthsNoOverflow(3)->toDateString())
            ->whereDate('maintenance_date', '<=', $date->toDateString())
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->get();
        $duplicateRecord = $duplicateRecords->first();

        if ($duplicateRecord && ! $request->boolean('confirm_duplicate')) {
            return redirect()
                ->back()
                ->withInput()
                ->with('duplicate_warning', [
                    'date' => $duplicateRecord->maintenance_date?->format('F j, Y'),
                    'record_id' => $duplicateRecord->id,
                ]);
        }

        $avrUpsUnavailable = ($hardwareResponses['avr_ups_power_recovery'] ?? null) === 'Not Available';
        $printerUnavailable = ($hardwareResponses['printer_printout'] ?? null) === 'Not Available';

        // Printer being unavailable must not add a default remark. The UPS/AVR
        // default is applied only when the user did not supply their own text.
        if ($remarks === '') {
            $remarks = $avrUpsUnavailable
                ? 'not available UPS/AVR'
                : ($printerUnavailable ? null : 'Preventive maintenance checklist completed.');
        }

        if ($correctiveAction === '' && ($avrUpsUnavailable || $printerUnavailable)) {
            $correctiveAction = 'office is advised to procure the equipment';
        }

        $assignment = $device->currentAssignment()
            ->with(['staff.office.location', 'location'])
            ->first();
        $staff = $assignment?->staff;
        $office = $staff?->office;
        $location = $assignment?->location ?? $office?->location;

        $snapshot = [
            'property_number' => $device->property_number,
            'equipment_type' => $device->type?->name,
            'computer_name' => $device->computer_name ?: data_get($device->specs, 'computer_name'),
            'condition' => $device->condition,
            'staff_id' => $staff?->id,
            'end_user' => $staff ? trim($staff->first_name . ' ' . $staff->last_name) : null,
            'staff_email' => $staff?->email,
            'office_id' => $office?->id,
            'office' => $office?->name,
            'location_id' => $location?->id,
            'location' => $location?->name,
            'location_code' => $location?->code,
        ];

        $record = DeviceMaintenanceRecord::create([
            'device_id' => $device->id,
            'staff_id' => $staff?->id,
            'office_id' => $office?->id,
            'location_id' => $location?->id,
            'maintenance_date' => $dateChecked,
            'maintenance_type' => 'Checked',
            'condition' => $device->condition ?? 'serviceable',
            'remarks' => $remarks,
            'corrective_action' => $correctiveAction,
            'checklist_data' => [
                'hardware' => $hardwareResponses,
                'software' => $softwareResponses,
                'snapshot' => $snapshot,
                'duplicate_check' => [
                    'matched_record_id' => $duplicateRecord?->id,
                    'matches_in_three_months' => $duplicateRecords->count(),
                    'window_months' => 3,
                ],
            ],
            'checked_by' => Auth::id(),
        ]);

        $latestRecord = $device->maintenanceRecords()
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->first();

        $device->update([
            'last_maintenance_date' => $latestRecord?->maintenance_date,
            'maintenance_remarks' => $latestRecord?->remarks,
        ]);

        ActivityLog::record(
            'updated',
            "Marked device \"{$device->property_number}\" as checked with checklist. Reason: {$verificationReason}",
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
                'not_available' => true,
            ],
            'printer_printout' => [
                'group' => 'Printer',
                'label' => 'Check printout',
                'not_available' => true,
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
