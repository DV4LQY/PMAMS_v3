<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\College;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CollegeController extends Controller
{
    private const NAME_REGEX = '/^[A-Za-zÑñ0-9][A-Za-zÑñ0-9.,&\'\-\(\)\s]*$/u';
    private const CODE_REGEX = '/^[A-Za-z0-9\-]+$/';

private function buildSummary(College $college, bool $includeEmptyOptional = false): array
{
    $summary = [
        'college' => $college->name,
        'code' => $college->code,
    ];

    if (!$includeEmptyOptional && empty($summary['code'])) {
        unset($summary['code']);
    }

    return $summary;
}

private function buildCreateSummary(College $college): array
{
    return $this->buildSummary($college);
}

private function buildUpdateSummary(College $college): array
{
    return $this->buildSummary($college);
}

private function buildDeleteSummary(College $college): array
{
    return $this->buildSummary($college, true);
}

    public function index()
    {
        $colleges = College::orderBy('name')->paginate(15);
        return view('admin.colleges.index', compact('colleges'));
    }

    public function create()
    {
        return view('admin.colleges.create');
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
                    'nullable',
                    'string',
                    'max:20',
                    'regex:' . self::CODE_REGEX,
                    Rule::unique('colleges', 'code'),
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
                'names.*.required' => 'The college name is required.',
                'names.*.string' => 'The college name must be text.',
                'names.*.max' => 'The college name may not be longer than 150 characters.',
                'names.*.regex' => 'The college name contains invalid characters.',
                'codes.*.string' => 'The code must be text.',
                'codes.*.max' => 'The code may not be longer than 20 characters.',
                'codes.*.regex' => 'The code may only contain letters, numbers, and hyphens.',
                'codes.*.unique' => 'This code has already been taken.',
            ], [
                'names.*' => 'college name',
                'codes.*' => 'code',
            ]);


            // If any code is duplicated, Laravel will redirect back with validation errors.
            // We also ensure the message is consistent for both single and bulk modes.


            $bulkItems = [];

foreach (range(0, $count - 1) as $i) {

    $code = $data['codes'][$i] ?? null;
    $code = $code === '' ? null : $code;

    $college = College::create([
        'name' => $data['names'][$i],
        'code' => $code,
    ]);

    $bulkItems[] = [
    'summary' => $this->buildCreateSummary($college),
];
}

ActivityLog::record(
    'created',
    "Created {$count} college(s) (Bulk Add)",
    null,
    ActivityLog::makePayload([
        'bulk' => true,
        'record_type' => 'College',
        'items' => $bulkItems,
    ])
);

            return redirect()->route('admin.colleges.index')->with('success', 'Colleges created.');

        }


        // Single
        $data = $request->validateWithBag('add', [
            'name' => ['required', 'string', 'max:150', 'regex:' . self::NAME_REGEX],
            'code' => [
                'nullable',
                'string',
                'max:20',
                'regex:' . self::CODE_REGEX,
                Rule::unique('colleges', 'code'),
            ],
        ], [
            'name.regex' => 'The college name contains invalid characters.',
            'code.regex' => 'The code may only contain letters, numbers, and hyphens.',
        ]);


        $code = $data['code'] ?? null;
        $code = $code === '' ? null : $code;

        $college = College::create([
            'name' => $data['name'],
            'code' => $code,
        ]);

        ActivityLog::record(
            'created',
            "Created college \"{$college->name}\"",
            $college,
            ActivityLog::makePayload(
    $this->buildCreateSummary($college)
)
        );

        return redirect()->route('admin.colleges.index')->with('success', 'College created.');
    }

    public function edit(College $college)
    {
        return view('admin.colleges.edit', compact('college'));
    }

    public function update(Request $request, College $college)
    {
        $data = $request->validateWithBag('edit', [
            'name' => ['required', 'string', 'max:150', 'regex:' . self::NAME_REGEX],
            'code' => [
                'nullable',
                'string',
                'max:20',
                'regex:' . self::CODE_REGEX,
                Rule::unique('colleges', 'code')->ignore($college->id),
            ],
        ], [
            'name.regex' => 'The college name contains invalid characters.',
            'code.regex' => 'The code may only contain letters, numbers, and hyphens.',
        ]);


        $code = $data['code'] ?? null;
        $code = $code === '' ? null : $code;

        $before = [
    'college' => $college->name,
    'code' => $college->code,
];

        $college->update([
            'name' => $data['name'],
            'code' => $code,
        ]);

        ActivityLog::record(
    'updated',
    "Updated college \"{$college->name}\"",
    $college,
    ActivityLog::makePayload(
        $this->buildUpdateSummary($college),
        ActivityLog::buildChanges(
            $before,
            [
                'college' => $college->name,
                'code' => $college->code,
            ]
        )
    )
);

        return redirect()->route('admin.colleges.index')
            ->with('success', 'College updated.');
    }

    public function destroy(College $college)
    {
        $summary = $this->buildDeleteSummary($college);

        $name = $college->name;

        ActivityLog::record(
            'deleted',
            "Deleted college \"{$name}\"",
            $college,
            ActivityLog::makePayload($summary)
        );

        $college->delete();

        return redirect()->route('admin.colleges.index')->with('success', 'College deleted.');
    }
}