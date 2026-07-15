<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Staff;
use App\Models\Device;
use App\Models\DeviceAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffDeviceController extends Controller
{
    public function index(Staff $staff)
    {
        $staff->load('office.location');

        $assignments = DeviceAssignment::query()
            ->where('staff_id', $staff->id)
            ->whereNull('returned_at')
            ->with([
                'device.type',
                'device.latestMaintenanceRecord',
            ])
            ->orderByDesc('issued_at')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Available Equipments for Picker
        |--------------------------------------------------------------------------
        | Only show devices that:
        | 1. Have status = available
        | 2. Do not have an active assignment
        */
        $availableDevices = Device::query()
            ->with('type')
            ->where('status', 'available')
            ->whereDoesntHave('currentAssignment')
            ->orderBy('property_number')
            ->get();

        return view('admin.staff.devices', compact('staff', 'assignments', 'availableDevices'));
    }

    public function issue(Request $request, Staff $staff)
    {
        $data = $request->validate([
            'device_id' => ['required', 'exists:devices,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Re-check Availability
        |--------------------------------------------------------------------------
        | This prevents issuing devices that were already issued by another admin,
        | even if they were visible in the modal before the page refreshed.
        */
        $device = Device::query()
            ->where('id', $data['device_id'])
            ->where('status', 'available')
            ->whereDoesntHave('currentAssignment')
            ->first();

        if (!$device) {
            return back()
                ->withErrors([
                    'device_id' => 'This equipment is not available or has already been issued.',
                ])
                ->withInput();
        }

        DeviceAssignment::create([
            'device_id' => $device->id,
            'staff_id' => $staff->id,
            'issued_by' => Auth::id(),
            'issued_at' => now(),
            'remarks' => $data['remarks'] ?? null,
        ]);

        $device->update([
            'status' => 'issued',
        ]);

        $type = strtolower(optional($device->type)->name ?? '');

        $summary = [
            'device' => $device->property_number,
            'device_type' => optional($device->type)->name,
        ];

        if (in_array($type, ['desktop', 'laptop'])) {
            $summary['computer_name'] = $device->computer_name;
        }

        $summary += [
            'brand' => $device->brand,
            'issued_to' => trim($staff->first_name . ' ' . $staff->last_name),
            'office' => optional($staff->office)->name,
            'location' => optional(optional($staff->office)->location)->name,
            'status' => 'Available → Issued',
            'issued_by' => Auth::user()->name,
            'issued_at' => now()->format('M d, Y h:i A'),
        ];

        if (filled($data['remarks'] ?? null)) {
            $summary['remarks'] = $data['remarks'];
        }

        ActivityLog::record(
            'issued',
            "Issued equipment \"{$device->property_number}\"",
            $device,
            $this->makeSummaryPayload($summary, ['status'])
        );
        return back()->with('success', 'Equipment issued successfully.');
    }

    /**
     * Build an ActivityLog "changes" payload where specific fields are
     * flagged as new (renders the (New) badge) without touching the
     * separate field-diff "changes" section, which stays empty on purpose
     * for Issue/Return logs.
     */
    private function makeSummaryPayload(array $summary, array $newFields = []): array
    {
        $normalized = [];

        foreach ($summary as $field => $value) {
            $normalized[$field] = [
                'value' => $value,
                'is_new' => in_array($field, $newFields, true),
            ];
        }

        return [
            'summary' => $normalized,
            'changes' => [],
        ];
    }

    public function return(Staff $staff, DeviceAssignment $assignment)
    {
        abort_unless($assignment->staff_id === $staff->id, 404);

        if ($assignment->returned_at) {
            return back()->with('info', 'Equipment is already returned.');
        }

        $assignment->load('device');

        $assignment->update([
            'returned_at' => now(),
        ]);

        if ($assignment->device) {
            $assignment->device->update([
                'status' => 'available',
            ]);

            $type = strtolower(optional($assignment->device->type)->name ?? '');

            $summary = [
                'device' => $assignment->device->property_number,
                'device_type' => optional($assignment->device->type)->name,
            ];

            if (in_array($type, ['desktop', 'laptop'])) {
                $summary['computer_name'] = $assignment->device->computer_name;
            }

            $summary += [
                'brand' => $assignment->device->brand,
                'returned_from' => trim($staff->first_name . ' ' . $staff->last_name),
                'office' => optional($staff->office)->name,
                'location' => optional(optional($staff->office)->location)->name,
                'status' => 'Issued → Available',
                'returned_by' => Auth::user()->name,
                'returned_at' => now()->format('M d, Y h:i A'),
            ];

            ActivityLog::record(
                'returned',
                "Returned equipment \"{$assignment->device->property_number}\"",
                $assignment->device,
                $this->makeSummaryPayload($summary, ['status'])
            );
        }

        return back()->with('success', 'Equipment returned successfully.');
    }
}