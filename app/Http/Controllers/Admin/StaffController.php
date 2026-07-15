<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Office;
use App\Models\Staff;
use Illuminate\Http\Request;

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
            'email' => $staff->email,
            'phone' => $staff->phone,
        ];
    }

    public function index(Office $office)
    {
        $staff = Staff::where('office_id', $office->id)
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(15);

        $office->load('college');

        return view('admin.staff.index', compact('office', 'staff'));
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
            return back()->with('success', 'Staff created.');
        }

        // Single
        $data = $request->validateWithBag('add', [
            'first_name' => $this->nameRules(),
            'last_name' => $this->nameRules(),
            'position' => $this->positionRules(),
            'email' => $this->emailRules(),
            'phone' => $this->phoneRules(),
            'is_active' => ['sometimes', 'boolean'],
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
        ]);

        $staffName = trim($staff->first_name . ' ' . $staff->last_name);

        ActivityLog::record(
            'created',
            "Created staff \"{$staffName}\"",
            $staff,
            ActivityLog::makePayload(
                $this->buildCreateSummary($staff)
            )
        );
        return back()->with('success', 'Staff created.');
    }

    public function edit(Office $office, Staff $staff)
    {
        abort_unless($staff->office_id === $office->id, 404);
        $office->load('college');

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
        ];

        $staff->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'position' => $data['position'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

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
                    ]
                )
            )
        );
        $office->load('college');

        return redirect()->route('admin.staff.index', $office)->with('success', 'Staff updated.');
    }

    public function destroy(Office $office, Staff $staff)
    {
        abort_unless($staff->office_id === $office->id, 404);

        $summary = $this->buildDeleteSummary($staff);

        $staffName = trim($staff->first_name . ' ' . $staff->last_name);

        ActivityLog::record(
            'deleted',
            "Deleted staff \"{$staffName}\"",
            $staff,
            ActivityLog::makePayload($summary)
        );

        $staff->delete();

        return back()->with('success', 'Staff deleted.');
    }
}
