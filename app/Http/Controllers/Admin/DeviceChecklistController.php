<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\DeviceMaintenancePhoto;
use App\Models\DeviceMaintenanceRecord;
use App\Models\SystemSetting;
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
            'linkedPeripherals.type',
            'currentAssignment.staff.office.location',
            'currentAssignment.office.location',
            'currentAssignment.location',
        ]);

        abort_unless($this->isComputerDevice($device->type?->name), 404);

        $linkablePeripherals = Device::query()
            ->with('type:id,name')
            ->whereHas('type', fn ($query) => $query->whereIn('name', [
                'Monitor',
                'AVR',
                'UPS',
                'Printer',
            ]))
            ->orderBy('property_number')
            ->get(['id', 'device_type_id', 'property_number', 'serial_number', 'computer_name', 'part_of_property_number']);

        return view('admin.devices.checklist-form', [
            'device' => $device,
            'linkedPeripherals' => $device->linkedPeripherals
                ->sortBy(fn ($peripheral) => strtolower($peripheral->type?->name ?? ''))
                ->values(),
            'linkablePeripherals' => $linkablePeripherals,
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
        $device->load(['type', 'linkedPeripherals.type']);
        abort_unless($this->isComputerDevice($device->type?->name), 404);

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

        $dispositionRules = [];
        foreach ($this->dispositionItems() as $key => $item) {
            // Accept the legacy value during the transition; it is converted
            // to the Condition column below and is never persisted as a status.
            $dispositionRules["disposition.{$key}"] = ['nullable', 'string', Rule::in(['repair', 'condemn', 'not_in_use'])];
        }
        $conditionRules = [];
        foreach ($this->dispositionItems() as $key => $item) {
            $conditionRules["condition.{$key}"] = ['nullable', 'string', Rule::in(['serviceable', 'unserviceable', 'condemned'])];
        }

        $data = $request->validate(array_merge([
            'date_checked' => ['nullable', 'date', 'before_or_equal:today'],
            'maintenance_date' => ['nullable', 'date', 'before_or_equal:today'],

            'hardware' => ['required', 'array'],

            'software' => ['required', 'array'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'corrective_action' => ['nullable', 'string', 'max:1000'],
            'maintenance_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'confirm_duplicate' => ['nullable', 'boolean'],
            'verification_reason' => ['nullable', 'string', 'max:1000'],
            'disposition' => ['nullable', 'array'],
            'condition' => ['nullable', 'array'],
        ], $hardwareRules, $softwareRules, $dispositionRules, $conditionRules));

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
        $dispositionResponses = array_filter(
            array_intersect_key($data['disposition'] ?? [], $this->dispositionItems()),
            static fn ($value) => in_array($value, ['repair', 'condemn', 'not_in_use'], true)
        );
        $conditionResponses = array_filter(
            array_intersect_key($data['condition'] ?? [], $this->dispositionItems()),
            static fn ($value) => in_array($value, ['serviceable', 'unserviceable', 'condemned'], true)
        );

        foreach ($dispositionResponses as $key => $disposition) {
            if ($disposition === 'condemn') {
                $conditionResponses[$key] = 'condemned';
                unset($dispositionResponses[$key]);
            }
        }

        // A Not Available row is intentionally outside the linked-property
        // disposition workflow. Ignore stale browser values so an old
        // condition/disposition cannot make the property number required.
        foreach ($hardwareResponses as $key => $response) {
            if ($response === 'Not Available') {
                unset($dispositionResponses[$key], $conditionResponses[$key]);
            }
        }

        // OK rows are always serviceable. A Not OK row defaults to
        // unserviceable unless the checker explicitly selects Condemned.
        foreach ($this->dispositionItems() as $key => $item) {
            $response = $hardwareResponses[$key] ?? null;
            if ($response === 'OK' || $response === 'Not Available') {
                $conditionResponses[$key] = 'serviceable';
            } elseif ($response === 'Not OK' && ! isset($conditionResponses[$key])) {
                $conditionResponses[$key] = 'unserviceable';
            }
        }
        foreach ($dispositionResponses as $key => $disposition) {
            $conditionResponses[$key] = $disposition === 'repair' ? 'unserviceable' : 'serviceable';
        }

        // Resolve each checklist section before any Not Available row can
        // unlink its peripheral. The same snapshot is then used for
        // validation, history, and current-property updates.
        $targetDevicesByKey = [];
        foreach (array_keys($this->dispositionItems()) as $key) {
            $targetDevicesByKey[$key] = $this->checklistTargetDevices($device, $key)->values();
        }

        foreach ($dispositionResponses as $key => $disposition) {
            if (($hardwareResponses[$key] ?? null) !== 'Not OK') {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors(["disposition.{$key}" => 'Repair or Not in Use can only be selected for a Not OK checklist item.']);
            }

            if ($key !== 'system_unit_power_on' && ($targetDevicesByKey[$key] ?? collect())->isEmpty()) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors(["disposition.{$key}" => 'Link the section property number to this system unit before applying a disposition.']);
            }
        }

        foreach ($conditionResponses as $key => $condition) {
            if ($key !== 'system_unit_power_on'
                && ($hardwareResponses[$key] ?? null) === 'Not OK'
                && ($targetDevicesByKey[$key] ?? collect())->isEmpty()) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors(["condition.{$key}" => 'Link the section property number to this system unit before applying a condition.']);
            }
        }

        // The parent system-unit disposition is independent from each linked
        // peripheral disposition. Every row is persisted by checklist key and
        // applied to the property number(s) represented by that section.
        $parentDisposition = $dispositionResponses['system_unit_power_on'] ?? null;
        $parentCondition = $conditionResponses['system_unit_power_on'] ?? 'serviceable';
        $remarks = trim((string) ($data['remarks'] ?? ''));
        $correctiveAction = trim((string) ($data['corrective_action'] ?? ''));
        $verificationReason = trim((string) ($data['verification_reason'] ?? ''));

        // A checklist can be submitted again, but only after the user has
        // explicitly confirmed that it is a verification within the same
        // three-month maintenance window. Every submission remains a new
        // maintenance record; this query is only used for the warning.
        $date = Carbon::parse($dateChecked);
        $windowMonths = max(1, min(36, (int) SystemSetting::getValue('maintenance_checklist_duplicate_window_months', 3)));
        $duplicateRecords = $device->maintenanceRecords()
            ->whereDate('maintenance_date', '>=', $date->copy()->subMonthsNoOverflow($windowMonths)->toDateString())
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
                    'window_months' => $windowMonths,
                ]);
        }

        if ($duplicateRecord && $verificationReason === '') {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['verification_reason' => 'Please provide a reason before verifying this duplicate checklist.'])
                ->with('duplicate_warning', [
                    'date' => $duplicateRecord->maintenance_date?->format('F j, Y'),
                    'record_id' => $duplicateRecord->id,
                    'window_months' => $windowMonths,
                ]);
        }

        $avrUpsUnavailable = ($hardwareResponses['avr_ups_power_recovery'] ?? null) === 'Not Available';
        $printerUnavailable = ($hardwareResponses['printer_printout'] ?? null) === 'Not Available';
        $defectiveSections = collect($this->checklistItems())
            ->filter(fn (array $item, string $key) => ($hardwareResponses[$key] ?? null) === 'Not OK')
            ->pluck('group')
            ->values()
            ->all();

        // Keep user-entered remarks intact. When no remarks are supplied,
        // describe defective sections first, then apply the existing
        // not-available defaults.
        if ($remarks === '') {
            $remarks = count($defectiveSections) > 0
                ? 'Defective ' . $this->formatSectionList($defectiveSections)
                : ($avrUpsUnavailable
                    ? 'not available UPS/AVR'
                    : ($printerUnavailable ? null : 'Serviceable'));
        }

        if ($correctiveAction === '' && ($avrUpsUnavailable || $printerUnavailable)) {
            $correctiveAction = 'office is advised to procure the equipment';
        }

        $assignment = $device->currentAssignment()
            ->with(['staff.office.location', 'office.location', 'location'])
            ->first();
        $staff = $assignment?->staff;
        $office = $assignment?->office ?: $staff?->office;
        $location = $assignment?->location ?: $office?->location;
        // Preserve the exact condition selected for the parent record. A
        // Condemned selection must remain Condemned in history and on the
        // linked property instead of being flattened to Unserviceable.
        $historicalCondition = $parentCondition;

        $snapshot = [
            'property_number' => $device->property_number,
            'parent_property_number' => $device->property_number,
            'equipment_type' => $device->type?->name,
            'linked_peripherals' => $device->linkedPeripherals
                ->sortBy(fn ($peripheral) => strtolower($peripheral->type?->name ?? ''))
                ->map(fn ($peripheral) => [
                    'property_number' => $peripheral->property_number,
                    'equipment_type' => $peripheral->type?->name,
                    'serial_number' => $peripheral->serial_number,
                ])
                ->values()
                ->all(),
            'computer_name' => $device->computer_name ?: data_get($device->specs, 'computer_name'),
            'condition' => $historicalCondition,
            'status' => match ($parentDisposition) {
                'repair' => 'repair',
                'not_in_use' => 'not_in_use',
                default => $device->status,
            },
            'disposition' => $parentDisposition,
            'dispositions_by_section' => collect($dispositionResponses)
                ->mapWithKeys(fn ($disposition, $key) => [
                    $key => [
                        'disposition' => $disposition,
                        'property_numbers' => ($targetDevicesByKey[$key] ?? collect())
                            ->pluck('property_number')
                            ->values()
                            ->all(),
                    ],
                ])
                ->all(),
            'conditions_by_section' => collect($conditionResponses)
                ->mapWithKeys(fn ($condition, $key) => [
                    $key => [
                        'condition' => $condition,
                        'property_numbers' => ($targetDevicesByKey[$key] ?? collect())
                            ->pluck('property_number')
                            ->values()
                            ->all(),
                    ],
                ])
                ->all(),
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
            'condition' => $historicalCondition,
            'remarks' => $remarks,
            'corrective_action' => $correctiveAction,
            'checklist_data' => [
                'hardware' => $hardwareResponses,
                'software' => $softwareResponses,
                'disposition' => $dispositionResponses,
                'condition' => $conditionResponses,
                'snapshot' => $snapshot,
                'duplicate_check' => [
                    'matched_record_id' => $duplicateRecord?->id,
                    'matches_in_three_months' => $duplicateRecords->count(),
                    'window_months' => $windowMonths,
                ],
            ],
            'checked_by' => Auth::id(),
        ]);

        // A peripheral marked Not Available is no longer part of this
        // system-unit grouping. This happens after the checklist snapshot is
        // written so historical reports retain the property number that was
        // linked at the time of the check.
        foreach ($hardwareResponses as $key => $response) {
            if ($response !== 'Not Available') {
                continue;
            }

            foreach (($targetDevicesByKey[$key] ?? collect()) as $targetDevice) {
                $previousParent = $targetDevice->part_of_property_number;
                if (blank($previousParent)) {
                    continue;
                }

                $targetDevice->update(['part_of_property_number' => null]);
                ActivityLog::record(
                    'updated',
                    "Unlinked {$targetDevice->type?->name} \"{$targetDevice->property_number}\" because the checklist marked it Not Available",
                    $targetDevice,
                    ActivityLog::makePayload([
                        'property_number' => $targetDevice->property_number,
                        'device_type' => $targetDevice->type?->name,
                        'previous_parent_property_number' => $previousParent,
                        'reason' => 'Checklist marked Not Available',
                        'maintenance_date' => $dateChecked,
                    ])
                );
            }
        }

        if ($request->hasFile('maintenance_photo')) {
            $photoPath = $request->file('maintenance_photo')->store('maintenance-photos', 'public');
            DeviceMaintenancePhoto::create([
                'device_id' => $device->id,
                'maintenance_record_id' => $record->id,
                'uploaded_by' => Auth::id(),
                'photo_path' => $photoPath,
                'captured_at' => Carbon::parse($dateChecked),
                'caption' => 'Preventive maintenance checklist photo',
            ]);
        }

        $latestRecord = $device->maintenanceRecords()
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->first();

        $deviceUpdates = [
            'last_maintenance_date' => $latestRecord?->maintenance_date,
            'maintenance_remarks' => $latestRecord?->remarks,
        ];

        // Apply the same condition value selected in the checklist to the
        // system-unit property. This keeps Serviceable, Unserviceable, and
        // Condemned distinct in the current record and its history.
        $deviceUpdates['condition'] = $parentCondition;
        if ($parentDisposition === 'repair') {
            $deviceUpdates['status'] = 'repair';
        } elseif ($parentDisposition === 'not_in_use') {
            $deviceUpdates['status'] = 'not_in_use';
        }

        $device->update($deviceUpdates);

        $affectedSectionKeys = array_values(array_unique(array_merge(
            array_keys($dispositionResponses),
            array_keys($conditionResponses)
        )));
        foreach ($affectedSectionKeys as $key) {
            $rowDisposition = $dispositionResponses[$key] ?? null;
            if ($key === 'system_unit_power_on') {
                continue;
            }

            // Not Available rows were deliberately unlinked above and must
            // not receive a current-property update after being detached.
            if (($hardwareResponses[$key] ?? null) === 'Not Available') {
                continue;
            }

            foreach (($targetDevicesByKey[$key] ?? collect()) as $targetDevice) {
                $rowCondition = $conditionResponses[$key] ?? 'serviceable';
                $targetUpdates = [
                    'last_maintenance_date' => $dateChecked,
                    'maintenance_remarks' => $remarks,
                    // Keep the selected condition aligned with the linked
                    // property's own inventory condition.
                    'condition' => $rowCondition,
                ];

                if ($rowDisposition === 'repair') {
                    $targetUpdates['status'] = 'repair';
                } elseif ($rowDisposition === 'not_in_use') {
                    $targetUpdates['status'] = 'not_in_use';
                }

                $targetDevice->update($targetUpdates);
                ActivityLog::record(
                    'updated',
                    "Updated linked peripheral \"{$targetDevice->property_number}\" from checklist",
                    $targetDevice,
                    ActivityLog::makePayload([
                        'property_number' => $targetDevice->property_number,
                        'section' => $this->checklistItems()[$key]['group'] ?? $key,
                        'disposition' => $rowDisposition,
                        'condition' => $rowCondition,
                        'parent_property_number' => $device->property_number,
                        'maintenance_date' => $dateChecked,
                    ])
                );
            }
        }

        $activityDescription = "Marked device \"{$device->property_number}\" as checked with checklist";
        if ($dispositionResponses !== []) {
            $activityDescription .= '. Dispositions: ' . collect($dispositionResponses)
                ->map(fn ($value, $key) => ($this->checklistItems()[$key]['group'] ?? $key) . ' = ' . ($value === 'not_in_use' ? 'Not in Use' : ucfirst($value)))
                ->join(', ');
        }
        if ($conditionResponses !== []) {
            $activityDescription .= '. Conditions: ' . collect($conditionResponses)
                ->map(fn ($value, $key) => ($this->checklistItems()[$key]['group'] ?? $key) . ' = ' . ucfirst($value))
                ->join(', ');
        }
        if ($duplicateRecord) {
            $activityDescription .= ". Verification reason: {$verificationReason}";
        }

        ActivityLog::record('updated', $activityDescription, $device);

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

    private function dispositionItems(): array
    {
        return collect($this->checklistItems())
            ->reject(fn (array $item) => in_array($item['group'] ?? '', ['Keyboard', 'Mouse'], true))
            ->all();
    }

    /**
     * Resolve the equipment property number(s) represented by a checklist row.
     */
    private function checklistTargetDevices(Device $device, string $key)
    {
        $children = $device->linkedPeripherals ?? collect();

        return match ($key) {
            'system_unit_power_on' => collect([$device]),
            'monitor_display' => $children->filter(fn ($child) => strtolower($child->type?->name ?? '') === 'monitor')->values(),
            'avr_ups_power_recovery' => $children->filter(fn ($child) => in_array(strtolower($child->type?->name ?? ''), ['avr', 'ups'], true))->values(),
            'printer_printout' => $children->filter(fn ($child) => strtolower($child->type?->name ?? '') === 'printer')->values(),
            default => collect(),
        };
    }

    private function formatSectionList(array $sections): string
    {
        $sections = array_values(array_filter(array_map('strval', $sections)));

        return match (count($sections)) {
            0 => '',
            1 => $sections[0],
            2 => $sections[0] . ' and ' . $sections[1],
            default => implode(', ', array_slice($sections, 0, -1)) . ', and ' . end($sections),
        };
    }

    private function isComputerDevice(?string $deviceType): bool
    {
        return in_array(strtolower((string) $deviceType), ['desktop', 'laptop'], true);
    }
}
