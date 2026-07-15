<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    private const NAME_REGEX = '/^[A-Za-zÑñ0-9][A-Za-zÑñ0-9.,&\'\-\(\)\s]*$/u';
    private const CODE_REGEX = '/^[A-Za-z0-9\-]+$/';

    private function buildSummary(Location $location): array
    {
        return [
            'name' => $location->name,
            'code' => $location->code,
        ];
    }

    public function index()
    {
        $locations = Location::orderBy('name')->paginate(15);
        return view('admin.locations.index', compact('locations'));
    }

    public function create()
    {
        return view('admin.locations.create');
    }

    public function store(Request $request)
    {
        // Single add OR bulk add (arrays of names/codes)
        // Use has() instead of filled() so bulk mode triggers even if values are empty strings.
        $isBulk = $request->has('names') || $request->has('codes');

        if ($isBulk) {
            $names = $request->input('names', []);
            $codes = $request->input('codes', []);

            $count = max(count($names), count($codes));
            $count = min(max($count, 0), 3);

            $rules = [];
            for ($i = 0; $i < $count; $i++) {
                $rules["names.$i"] = ['required', 'string', 'max:150', 'regex:' . self::NAME_REGEX];
                $rules["codes.$i"] = [
                    'required',
                    'string',
                    'max:20',
                    'regex:' . self::CODE_REGEX,
                    Rule::unique('locations', 'code'),
                    // Rule::unique only checks against existing DB rows, so two
                    // duplicate codes submitted together in the same bulk batch
                    // would both pass validation and then blow up with an
                    // uncaught QueryException on the second insert. Catch that
                    // here instead, before it ever reaches the database.
                    function ($attribute, $value, $fail) use ($codes, $i) {
                        if ($value === null || $value === '') {
                            return;
                        }

                        foreach ($codes as $j => $other) {
                            if ($j !== $i && $other !== null && $other !== '' && $other === $value) {
                                $fail('This code is used more than once in this submission.');
                                return;
                            }
                        }
                    },
                ];
            }

            $data = $request->validateWithBag('add', $rules, [
                'names.*.required' => 'The location name is required.',
                'names.*.string' => 'The location name must be text.',
                'names.*.max' => 'The location name may not be longer than 150 characters.',
                'names.*.regex' => 'The location name contains invalid characters.',
                'codes.*.required' => 'The code is required.',
                'codes.*.string' => 'The code must be text.',
                'codes.*.max' => 'The code may not be longer than 20 characters.',
                'codes.*.regex' => 'The code may only contain letters, numbers, and hyphens.',
                'codes.*.unique' => 'This code has already been taken.',
            ], [
                'names.*' => 'location name',
                'codes.*' => 'code',
            ]);


        // If any code is duplicated, Laravel will redirect back with validation errors.
        // We also ensure the message is consistent for both single and bulk modes.

            $items = [];

            foreach (range(0, $count - 1) as $i) {
                $code = $data['codes'][$i] ?? null;
                $code = $code === '' ? null : $code;

                $location = Location::create([
                    'name' => $data['names'][$i],
                    'code' => $code,
                ]);

                $items[] = [
                    'summary' => $this->buildSummary($location),
                ];
            }

            ActivityLog::record(
                'created',
                "Created {$count} location(s) (Bulk Add)",
                null,
                ActivityLog::makePayload([
                    'bulk' => true,
                    'record_type' => 'Location',
                    'items' => $items,
                ])
            );

            return redirect()->route('admin.locations.index')->with('success', 'Locations created.');

        }


        // Single
        $data = $request->validateWithBag('add', [
            'name' => ['required', 'string', 'max:150', 'regex:' . self::NAME_REGEX],
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:' . self::CODE_REGEX,
                Rule::unique('locations', 'code'),
            ],
        ], [
            'name.regex' => 'The location name contains invalid characters.',
            'code.required' => 'The code is required.',
            'code.regex' => 'The code may only contain letters, numbers, and hyphens.',
        ]);


        $code = $data['code'];

        $location = Location::create([
            'name' => $data['name'],
            'code' => $code,
        ]);

        ActivityLog::record(
            'created',
            "Created location \"{$location->name}\"",
            $location,
            ActivityLog::makePayload($this->buildSummary($location))
        );

        return redirect()->route('admin.locations.index')->with('success', 'Location created.');
    }

    public function edit(Location $location)
    {
        return view('admin.locations.edit', compact('location'));
    }

    public function update(Request $request, Location $location)
    {
        $data = $request->validateWithBag('edit', [
            'name' => ['required', 'string', 'max:150', 'regex:' . self::NAME_REGEX],
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:' . self::CODE_REGEX,
                Rule::unique('locations', 'code')->ignore($location->id),
            ],
        ], [
            'name.regex' => 'The location name contains invalid characters.',
            'code.required' => 'The code is required.',
            'code.regex' => 'The code may only contain letters, numbers, and hyphens.',
        ]);


        $code = $data['code'];

        $before = $this->buildSummary($location);

        $location->update([
            'name' => $data['name'],
            'code' => $code,
        ]);

        ActivityLog::record(
            'updated',
            "Updated location \"{$location->name}\"",
            $location,
            ActivityLog::makePayload(
                $this->buildSummary($location),
                ActivityLog::buildChanges($before, $this->buildSummary($location))
            )
        );

        return redirect()->route('admin.locations.index')->with('success', 'Location updated.');
    }

    public function destroy(Location $location)
    {
        $name = $location->name;
        $summary = $this->buildSummary($location);

        ActivityLog::record(
            'deleted',
            "Deleted location \"{$name}\"",
            $location,
            ActivityLog::makePayload($summary)
        );

        $location->delete();

        return redirect()->route('admin.locations.index')->with('success', 'Location deleted.');
    }
}