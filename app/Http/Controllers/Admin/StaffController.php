<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DeviceAssignment;
use App\Models\Location;
use App\Models\Office;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    /**
     * Letters (incl. Ñ/ñ), spaces, hyphens, apostrophes, and periods —
     * covers names like "De la Cruz", "O'Brien", "Jr.", "II".
     */
    private const NAME_REGEX = '/^[A-Za-zÑñ][A-Za-zÑñ\.\-\'\s]*$/u';

    /**
     * Philippine mobile number: 09 followed by 9 more digits (11 digits total).
     */
    private const PH_MOBILE_REGEX = '/^09[0-9]{9}$/';

    private function nameRules(): array
    {
        return ['required', 'string', 'max:100', 'regex:' . self::NAME_REGEX];
    }

    private function positionRules(): array
    {
        return ['nullable', 'string', 'max:100'];
    }

    private function emailRules(): array
    {
        return ['nullable', 'email', 'max:255'];
    }

    private function phoneRules(): array
    {
        return ['nullable', 'regex:' . self::PH_MOBILE_REGEX];
    }

    private function officeHeadRules(): array
    {
        return ['sometimes', 'boolean'];
    }

    private function officeHeadValue(array $data): ?bool
    {
        // An inactive person cannot remain the office representative. NULL is
        // used instead of false so the database unique index permits all other
        // staff in the same office to remain unassigned.
        return !empty($data['is_office_head']) && !empty($data['is_active']) ? true : null;
    }

    private function makeOfficeHead(Office $office, Staff $staff, ?bool $isOfficeHead): void
    {
        if ($isOfficeHead !== true) {
            $staff->updateQuietly(['is_office_head' => null]);
            return;
        }

        Staff::query()
            ->where('office_id', $office->id)
            ->whereKeyNot($staff->getKey())
            ->update(['is_office_head' => null]);

        $staff->updateQuietly(['is_office_head' => true]);
    }

    private function fieldMessages(): array
    {
        return [
            'first_name' => 'Please enter a valid first name (letters only).',
            'last_name' => 'Please enter a valid last name (letters only).',
            'email' => 'Please enter a valid email address, e.g. juan.delacruz@example.com.',
            'phone' => 'Please enter a valid PH mobile number, e.g. 09171234567 (11 digits, starts with 09).',
        ];
    }

    private function buildCreateSummary(Staff $staff): array
    {
        $staff->loadMissing('office.college');

        $summary = [
            'first_name' => $staff->first_name,
            'last_name' => $staff->last_name,
            'position' => $staff->position,
            'office' => optional($staff->office)->name,
            'college' => optional(optional($staff->office)->college)->name,
            'active' => $staff->is_active,
            'office_head' => $staff->is_office_head,
            'office_head_title' => $staff->office?->responsibleTitle(),
        ];

        if (!empty($staff->email)) {
            $summary['email'] = $staff->email;
        }

        if (!empty($staff->phone)) {
            $summary['phone'] = $staff->phone;
        }

        return $summary;
    }

    private function buildUpdateSummary(Staff $staff): array
    {
        return $this->buildCreateSummary($staff);
    }

    private function buildDeleteSummary(Staff $staff): array
    {
        $staff->loadMissing('office.college');

        return [
            'first_name' => $staff->first_name,
            'last_name' => $staff->last_name,
            'position' => $staff->position,
            'office' => optional($staff->office)->name,
            'college' => optional(optional($staff->office)->college)->name,
            'active' => $staff->is_active,
            'office_head' => $staff->is_office_head,
            'office_head_title' => $staff->office?->responsibleTitle(),
            'email' => $staff->email,
            'phone' => $staff->phone,
        ];
    }

    public function index(Request $request, Office $office)
    {
        $staff = Staff::where('office_id', $office->id)
            ->withCount('activeAssignments')
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(15);

        $office->load(['location', 'college']);

        // The transfer form needs the complete active location/office tree so
        // the destination office can never be selected independently of its
        // parent location. Soft-deleted locations and offices are excluded by
        // the normal Eloquent relationships.
        $transferLocations = Location::query()
            ->whereHas('offices')
            ->with(['offices' => fn ($query) => $query
                ->select(['id', 'location_id', 'name'])
                ->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $openAddStaff = $request->boolean('open_add');

        return view('admin.staff.index', compact('office', 'staff', 'openAddStaff', 'transferLocations'));
    }

    public function store(Request $request, Office $office)
    {
        // Single add OR bulk add (array of staff rows: staff[0][first_name], etc.)
        $isBulk = $request->has('staff');

        if ($isBulk) {
            $rows = $request->input('staff', []);
            $count = min(max(count($rows), 0), 3);

            $rules = [];
            $messages = [];
            $attributes = [];

            for ($i = 0; $i < $count; $i++) {
                $rules["staff.$i.first_name"] = $this->nameRules();
                $rules["staff.$i.last_name"] = $this->nameRules();
                $rules["staff.$i.position"] = $this->positionRules();
                $rules["staff.$i.email"] = $this->emailRules();
                $rules["staff.$i.phone"] = $this->phoneRules();
                $rules["staff.$i.is_active"] = ['sometimes', 'boolean'];

                $messages["staff.$i.first_name.required"] = 'First name is required.';
                $messages["staff.$i.first_name.regex"] = $this->fieldMessages()['first_name'];
                $messages["staff.$i.last_name.required"] = 'Last name is required.';
                $messages["staff.$i.last_name.regex"] = $this->fieldMessages()['last_name'];
                $messages["staff.$i.email.email"] = $this->fieldMessages()['email'];
                $messages["staff.$i.phone.regex"] = $this->fieldMessages()['phone'];
            }

            $attributes['staff.*.first_name'] = 'first name';
            $attributes['staff.*.last_name'] = 'last name';
            $attributes['staff.*.position'] = 'position';
            $attributes['staff.*.email'] = 'email';
            $attributes['staff.*.phone'] = 'phone';

            $data = $request->validateWithBag('add', $rules, $messages, $attributes);
            $duplicateErrors = [];

            foreach ($data['staff'] as $index => $row) {

                $duplicate = Staff::where('office_id', $office->id)
                    ->whereRaw('LOWER(first_name)=?', [strtolower(trim($row['first_name']))])
                    ->whereRaw('LOWER(last_name)=?', [strtolower(trim($row['last_name']))])
                    ->whereRaw('LOWER(position)=?', [strtolower(trim($row['position'] ?? ''))])
                    ->exists();

                if ($duplicate) {
                    $duplicateErrors["staff.$index.first_name"] =
                        'A staff member with the same name and position already exists.';
                }

                if (!empty($row['email'])) {
                    $exists = Staff::where('office_id', $office->id)
                        ->where('email', $row['email'])
                        ->exists();

                    if ($exists) {
                        $duplicateErrors["staff.$index.email"] =
                            'This email address is already assigned to another staff member.';
                    }
                }

                if (!empty($row['phone'])) {
                    $exists = Staff::where('office_id', $office->id)
                        ->where('phone', $row['phone'])
                        ->exists();

                    if ($exists) {
                        $duplicateErrors["staff.$index.phone"] =
                            'This phone number is already assigned to another staff member.';
                    }
                }
            }

            if (!empty($duplicateErrors)) {
                throw ValidationException::withMessages($duplicateErrors)
                    ->errorBag('add');
            }
            $bulkItems = [];
            foreach ($data['staff'] as $row) {
                $staff = Staff::create([
                    'office_id' => $office->id,
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'position' => $row['position'] ?? null,
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'is_active' => (bool) ($row['is_active'] ?? false),
                    'is_office_head' => null,
                ]);

                $staffName = trim($staff->first_name . ' ' . $staff->last_name);

                $bulkItems[] = [
                    'summary' => $this->buildCreateSummary($staff),
                ];

            }
            ActivityLog::record(
                'created',
                'Created ' . count($bulkItems) . ' staff member(s) (Bulk Add)',
                null,
                ActivityLog::makePayload([
                    'bulk' => true,
                    'record_type' => 'Staff',
                    'items' => $bulkItems,
                ])
            );
            return $this->afterStaffSave($request, 'Staff created.');
        }

        // Single
        $data = $request->validateWithBag('add', [
            'first_name' => $this->nameRules(),
            'last_name' => $this->nameRules(),
            'position' => $this->positionRules(),
            'email' => $this->emailRules(),
            'phone' => $this->phoneRules(),
            'is_active' => ['sometimes', 'boolean'],
            'is_office_head' => $this->officeHeadRules(),
        ], [
            'first_name.regex' => $this->fieldMessages()['first_name'],
            'last_name.regex' => $this->fieldMessages()['last_name'],
            'email.email' => $this->fieldMessages()['email'],
            'phone.regex' => $this->fieldMessages()['phone'],
        ]);

        $errors = [];

        $duplicate = Staff::where('office_id', $office->id)
            ->whereRaw('LOWER(first_name)=?', [strtolower(trim($data['first_name']))])
            ->whereRaw('LOWER(last_name)=?', [strtolower(trim($data['last_name']))])
            ->whereRaw('LOWER(position)=?', [strtolower(trim($data['position'] ?? ''))])
            ->exists();

        if ($duplicate) {
            $errors['first_name'] =
                'A staff member with the same name and position already exists.';
        }

        if (!empty($data['email'])) {
            $exists = Staff::where('office_id', $office->id)
                ->where('email', $data['email'])
                ->exists();

            if ($exists) {
                $errors['email'] =
                    'This email address is already assigned to another staff member.';
            }
        }

        if (!empty($data['phone'])) {
            $exists = Staff::where('office_id', $office->id)
                ->where('phone', $data['phone'])
                ->exists();

            if ($exists) {
                $errors['phone'] =
                    'This phone number is already assigned to another staff member.';
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors)
                ->errorBag('add');
        }

        $staff = Staff::create([
            'office_id' => $office->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'position' => $data['position'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'is_office_head' => null,
        ]);

        $this->makeOfficeHead($office, $staff, $this->officeHeadValue($data));

        $staffName = trim($staff->first_name . ' ' . $staff->last_name);

        ActivityLog::record(
            'created',
            "Created staff \"{$staffName}\"",
            $staff,
            ActivityLog::makePayload(
                $this->buildCreateSummary($staff)
            )
        );
        return $this->afterStaffSave($request, 'Staff created.');
    }

    /**
     * Return to the originating equipment details page when Add Staff was
     * opened from the Reissue dialog. Only local absolute paths are accepted.
     */
    private function afterStaffSave(Request $request, string $message)
    {
        $returnTo = trim((string) $request->input('return_to', ''));

        if ($returnTo !== '' && str_starts_with($returnTo, '/') && ! str_starts_with($returnTo, '//')) {
            return redirect()->to($returnTo)->with('success', $message);
        }

        return back()->with('success', $message);
    }

    public function edit(Office $office, Staff $staff)
    {
        abort_unless($staff->office_id === $office->id, 404);
        $office->load(['location', 'college']);

        return view('admin.staff.edit', compact('office', 'staff'));
    }

    public function update(Request $request, Office $office, Staff $staff)
    {
        abort_unless($staff->office_id === $office->id, 404);

        $data = $request->validateWithBag('edit', [
            'first_name' => $this->nameRules(),
            'last_name' => $this->nameRules(),
            'position' => $this->positionRules(),
            'email' => $this->emailRules(),
            'phone' => $this->phoneRules(),
            'is_active' => ['sometimes', 'boolean'],
            'is_office_head' => $this->officeHeadRules(),
        ], [
            'first_name.regex' => $this->fieldMessages()['first_name'],
            'last_name.regex' => $this->fieldMessages()['last_name'],
            'email.email' => $this->fieldMessages()['email'],
            'phone.regex' => $this->fieldMessages()['phone'],
        ]);

        $errors = [];

        $duplicate = Staff::where('office_id', $office->id)
            ->where('id', '!=', $staff->id)
            ->whereRaw('LOWER(first_name)=?', [strtolower(trim($data['first_name']))])
            ->whereRaw('LOWER(last_name)=?', [strtolower(trim($data['last_name']))])
            ->whereRaw('LOWER(position)=?', [strtolower(trim($data['position'] ?? ''))])
            ->exists();

        if ($duplicate) {
            $errors['first_name'] =
                'Another staff member with the same name and position already exists.';
        }

        if (!empty($data['email'])) {
            $exists = Staff::where('office_id', $office->id)
                ->where('id', '!=', $staff->id)
                ->where('email', $data['email'])
                ->exists();

            if ($exists) {
                $errors['email'] =
                    'This email address is already assigned to another staff member.';
            }
        }

        if (!empty($data['phone'])) {
            $exists = Staff::where('office_id', $office->id)
                ->where('id', '!=', $staff->id)
                ->where('phone', $data['phone'])
                ->exists();

            if ($exists) {
                $errors['phone'] =
                    'This phone number is already assigned to another staff member.';
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors)
                ->errorBag('edit');
        }

        $before = [
            'first_name' => $staff->first_name,
            'last_name' => $staff->last_name,
            'position' => $staff->position,
            'email' => $staff->email,
            'phone' => $staff->phone,
            'active' => $staff->is_active,
            'office_head' => $staff->is_office_head,
        ];

        $staff->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'position' => $data['position'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            // Clear the current flag before assigning it below. This also
            // makes an unchecked edit immediately remove the designation.
            'is_office_head' => null,
        ]);

        $this->makeOfficeHead($office, $staff, $this->officeHeadValue($data));

        $staffName = trim($staff->first_name . ' ' . $staff->last_name);

        ActivityLog::record(
            'updated',
            "Updated staff \"{$staffName}\"",
            $staff,
            ActivityLog::makePayload(
                $this->buildUpdateSummary($staff),
                ActivityLog::buildChanges(

                    $before,
                    [
                        'first_name' => $staff->first_name,
                        'last_name' => $staff->last_name,
                        'position' => $staff->position,
                        'email' => $staff->email,
                        'phone' => $staff->phone,
                        'active' => $staff->is_active,
                        'office_head' => $staff->is_office_head,
                    ]
                )
            )
        );
        $office->load('college');

        return redirect()->route('admin.staff.index', $office)->with('success', 'Staff updated.');
    }

    /**
     * Move a staff directory record to another active office/location.
     *
     * Active equipment is automatically returned as part of the same
     * transaction. The assignment rows remain as historical issuance records,
     * while their devices become available for a new issue.
     */
    public function transfer(Request $request, Office $office, Staff $staff)
    {
        abort_unless($staff->office_id === $office->id, 404);

        $data = $request->validateWithBag('transfer', [
            'transfer_staff_id' => ['required', 'integer', Rule::in([$staff->id])],
            'destination_location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'destination_office_id' => [
                'required',
                'integer',
                Rule::exists('offices', 'id')->where(function ($query) use ($request): void {
                    $query
                        ->where('location_id', (int) $request->input('destination_location_id'))
                        ->whereNull('deleted_at');
                }),
            ],
            'preserve_assignments' => ['sometimes', 'boolean'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ], [
            'transfer_staff_id.in' => 'The selected staff member is no longer available. Refresh and try again.',
            'destination_location_id.exists' => 'Select an active destination location.',
            'destination_office_id.exists' => 'Select an active office belonging to the selected location.',
        ]);

        $sourceOffice = $office->loadMissing('location');
        $destinationOffice = Office::query()
            ->with('location')
            ->findOrFail((int) $data['destination_office_id']);

        if ((int) $destinationOffice->id === (int) $sourceOffice->id) {
            return back()
                ->withErrors(['destination_office_id' => 'Choose an office different from the current office.'], 'transfer')
                ->withInput();
        }

        $activeAssignmentCount = $staff->activeAssignments()->count();
        if ($activeAssignmentCount > 0 && ! $request->boolean('preserve_assignments')) {
            return back()
                ->withErrors([
                    'preserve_assignments' => "This staff member has {$activeAssignmentCount} active equipment assignment(s). Confirm that the equipment will be returned and marked available while the assignment history is retained.",
                ], 'transfer')
                ->withInput();
        }

        $staffName = $staff->display_name;
        $wasOfficeHead = $staff->is_office_head === true;
        $before = [
            'location' => $sourceOffice->location?->name,
            'office' => $sourceOffice->name,
            'is_office_head' => $staff->is_office_head,
        ];
        $after = [
            'location' => $destinationOffice->location?->name,
            'office' => $destinationOffice->name,
            // Office-head status is scoped to the office. Clearing it avoids
            // carrying a designation into another office without review.
            'is_office_head' => null,
        ];

        $releasedAssignments = [];

        DB::transaction(function () use (&$releasedAssignments, $staff, $destinationOffice, $staffName, $before, $after, $wasOfficeHead): void {
            $activeAssignments = DeviceAssignment::query()
                ->where('staff_id', $staff->id)
                ->whereNull('returned_at')
                ->with('device.type')
                ->lockForUpdate()
                ->get();

            $returnedAt = now();
            foreach ($activeAssignments as $assignment) {
                $device = $assignment->device;
                $previousDeviceStatus = $device?->status;
                $otherActiveAssignment = DeviceAssignment::query()
                    ->where('device_id', $assignment->device_id)
                    ->whereNull('returned_at')
                    ->where('id', '<>', $assignment->id)
                    ->exists();

                $assignment->update([
                    'returned_at' => $returnedAt,
                    'remarks' => trim(($assignment->remarks ? $assignment->remarks . ' ' : '')
                        . 'Automatically returned during staff transfer on ' . $returnedAt->format('M d, Y h:i A') . '.'),
                ]);

                // A valid equipment record has one active assignment. Keep a
                // conflicting device issued rather than masking another
                // active assignment if legacy data violates that invariant.
                if ($device && ! $otherActiveAssignment) {
                    $device->update(['status' => 'available']);
                }

                $deviceLabel = $device?->property_number ?: 'Device #' . $assignment->device_id;
                $releasedAssignments[] = [
                    'assignment_id' => $assignment->id,
                    'device_id' => $assignment->device_id,
                    'property_number' => $device?->property_number,
                    'status' => $device && ! $otherActiveAssignment ? 'Available' : 'Not changed (conflicting active assignment)',
                ];

                ActivityLog::record(
                    'returned',
                    "Automatically returned equipment \"{$deviceLabel}\" during staff transfer of \"{$staffName}\"",
                    $device ?: $assignment,
                    ActivityLog::makePayload([
                        'property_number' => $device?->property_number,
                        'device_type' => $device?->type?->name,
                        'returned_from' => $staffName,
                        'from_office' => $before['office'],
                        'from_location' => $before['location'],
                        'status' => $device && ! $otherActiveAssignment ? 'Issued → Available' : 'Assignment closed; device status unchanged',
                        'reason' => 'Staff transfer',
                        'returned_at' => $returnedAt->format('M d, Y h:i A'),
                    ], [
                        'status' => [
                            'old' => $previousDeviceStatus,
                            'new' => $device?->status,
                        ],
                    ])
                );
            }

            $staff->update([
                'office_id' => $destinationOffice->id,
                'is_office_head' => null,
            ]);

            ActivityLog::record(
                'transferred',
                "Transferred staff \"{$staffName}\" from {$before['office']} to {$after['office']}",
                $staff,
                ActivityLog::makePayload(
                    [
                        'staff' => $staffName,
                        'from_location' => $before['location'],
                        'from_office' => $before['office'],
                        'to_location' => $after['location'],
                        'to_office' => $after['office'],
                        'active_assignments_released' => count($releasedAssignments),
                        'released_equipment' => collect($releasedAssignments)
                            ->pluck('property_number')
                            ->filter()
                            ->values()
                            ->all(),
                        'office_head_cleared' => $wasOfficeHead,
                    ],
                    ActivityLog::buildChanges($before, $after)
                )
            );
        });

        $message = "Staff \"{$staffName}\" transferred to {$destinationOffice->name} ({$destinationOffice->location?->name}).";
        $releasedAssignmentCount = count($releasedAssignments);
        if ($releasedAssignmentCount > 0) {
            $availableDeviceCount = collect($releasedAssignments)
                ->where('status', 'Available')
                ->count();
            $message .= " {$availableDeviceCount} equipment device(s) were automatically returned and marked available; assignment history was retained.";

            if ($availableDeviceCount < $releasedAssignmentCount) {
                $message .= ' A conflicting active assignment prevented one or more device status updates; review the activity log.';
            }
        }
        if ($wasOfficeHead) {
            $message .= ' The previous office-head designation was cleared; assign the designation separately if needed.';
        }

        $returnTo = trim((string) ($data['return_to'] ?? ''));
        if ($returnTo !== ''
            && str_starts_with($returnTo, '/')
            && ! str_starts_with($returnTo, '//')
            && ! str_starts_with($returnTo, '/\\')
            && ! str_contains($returnTo, "\r")
            && ! str_contains($returnTo, "\n")) {
            return redirect()->to($returnTo)->with('success', $message);
        }

        return redirect()
            ->route('admin.staff.index', $destinationOffice)
            ->with('success', $message);
    }

    public function destroy(Office $office, Staff $staff)
    {
        abort_unless($staff->office_id === $office->id, 404);

        if (DeviceAssignment::query()
            ->where('staff_id', $staff->id)
            ->whereNull('returned_at')
            ->exists()) {
            return back()->with('error', 'Staff cannot be moved to the recycle bin while active equipment is assigned. Return or reissue the equipment first.');
        }

        $summary = $this->buildDeleteSummary($staff);

        $staffName = trim($staff->first_name . ' ' . $staff->last_name);

        ActivityLog::record(
            'deleted',
            "Moved staff \"{$staffName}\" to the recycle bin",
            $staff,
            ActivityLog::makePayload($summary)
        );

        $staff->delete();

        return back()->with('success', 'Staff moved to the recycle bin.');
    }
}
