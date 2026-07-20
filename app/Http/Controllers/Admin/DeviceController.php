<?php

namespace App\Http\Controllers\Admin;

use App\Exports\EquipmentInventoryExport;
use App\Exports\EquipmentImportTemplateExport;
use App\Exports\PreventiveMaintenanceReportExport;
use App\Imports\DeviceInventoryImport;
use App\Models\ActivityLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\DeviceMaintenanceRecord;
use App\Models\Location;
use App\Models\DeviceType;
use App\Models\Office;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DeviceController extends Controller
{
    private const IMPORT_MAX_ERRORS = 50;
    private const IMPORT_MAX_COLUMNS = 40;

    /** @var array<string, array<string, mixed>> */
    private array $importLookupCache = [
        'types' => [],
        'staff' => [],
        'locations' => [],
        'offices' => [],
    ];

    public function index(Request $request)
    {
        $q = $request->string('q')->toString();
        $typeId = $request->integer('type');
        $locationId = $request->integer('location') ?: $request->integer('college');
        $collegeId = $locationId; // backward-compatible variable for existing views
        $officeId = $request->integer('office_id') ?: null;
        $status = $request->query('status');
        $condition = $request->query('condition');

        if (!in_array($status, ['available', 'issued', 'repair', 'retired'], true)) {
            $status = null;
        }

        if (!in_array($condition, ['serviceable', 'unserviceable', 'condemned'], true)) {
            $condition = null;
        }

        // Only locations with a code are usable in this filter dropdown —
        // a location with no code would render as a blank, unselectable-
        // looking option, so it's excluded here rather than in the view.
        $locations = Location::whereNotNull('code')
            ->where('code', '!=', '')
            ->orderBy('name')
            ->get();

        $colleges = $locations; // backward-compatible variable for existing device views
        $selectedLocation = $locationId
            ? $locations->firstWhere('id', $locationId)
            : null;
        $showOfficeFilter = strtoupper(trim((string) ($selectedLocation?->code ?? ''))) === 'ADMIN';
        $offices = $showOfficeFilter
            ? Office::where('location_id', $locationId)
                ->with('location')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
            : collect();

        if (!$showOfficeFilter) {
            $officeId = null;
        }

        $filters = [
            'q' => $q,
            'type_id' => $typeId,
            'location_id' => $locationId,
            'office_id' => $officeId,
            'status' => $status,
            'condition' => $condition,
        ];

        $deviceQuery = Device::query()
            ->with([
                'type',
                'currentAssignment.staff.office.location',
                'currentAssignment.office.location',
                'currentAssignment.location',
                'latestMaintenanceRecord',
            ])
            ->filterInventory($filters);

        $filteredEquipmentCount = (clone $deviceQuery)->count();

        $devices = $deviceQuery
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $types = $this->allowedDeviceTypes();

        return view('admin.devices.index', compact(
            'devices',
            'filteredEquipmentCount',
            'q',
            'typeId',
            'locationId',
            'collegeId',
            'officeId',
            'status',
            'condition',
            'types',
            'locations',
            'colleges',
            'offices',
            'showOfficeFilter'
        ));
    }

    public function staffLookup(Request $request)
    {
        $tokens = $this->searchTokens($request->string('q')->toString());
        $officeId = $request->integer('office_id') ?: null;
        $locationId = $request->integer('location_id') ?: null;

        $staff = Staff::query()
            ->select(['id', 'office_id', 'first_name', 'last_name', 'position', 'email', 'phone', 'is_active'])
            ->with([
                'office:id,location_id,name',
                'office.location:id,name,code',
            ])
            ->where('is_active', true)
            ->when($officeId, fn ($query) => $query->where('office_id', $officeId))
            ->when($locationId, fn ($query) => $query->whereHas('office', fn ($office) => $office->where('location_id', $locationId)))
            ->when($tokens !== [], function ($query) use ($tokens) {
                foreach ($tokens as $token) {
                    $query->where(function ($sub) use ($token) {
                        $like = "%{$token}%";

                        $sub->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('position', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('phone', 'like', $like)
                            ->orWhereHas('office', function ($office) use ($like) {
                                $office->where('name', 'like', $like)
                                    ->orWhereHas('location', function ($location) use ($like) {
                                        $location->where('name', 'like', $like)
                                            ->orWhere('code', 'like', $like);
                                    });
                            });
                    });
                }
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(10)
            ->get()
            ->map(fn (Staff $staff) => $this->staffLookupResult($staff))
            ->values();

        return response()->json(['results' => $staff]);
    }

    public function availableLookup(Request $request)
    {
        $tokens = $this->searchTokens($request->string('q')->toString());

        $devices = Device::query()
            ->select([
                'id',
                'device_type_id',
                'property_number',
                'serial_number',
                'computer_name',
                'brand',
                'model',
                'status',
                'condition',
            ])
            ->with('type:id,name')
            ->where('status', 'available')
            ->whereDoesntHave('currentAssignment')
            ->when($tokens !== [], function ($query) use ($tokens) {
                foreach ($tokens as $token) {
                    $query->where(function ($sub) use ($token) {
                        $like = "%{$token}%";

                        $sub->where('property_number', 'like', $like)
                            ->orWhere('serial_number', 'like', $like)
                            ->orWhere('computer_name', 'like', $like)
                            ->orWhere('brand', 'like', $like)
                            ->orWhere('model', 'like', $like)
                            ->orWhereHas('type', fn ($type) => $type->where('name', 'like', $like));
                    });
                }
            })
            ->orderBy('property_number')
            ->limit(10)
            ->get()
            ->map(function (Device $device) {
                $brandModel = trim(($device->brand ?? '') . ' ' . ($device->model ?? ''));
                $name = $device->type?->name ?? 'Equipment';

                return [
                    'id' => $device->id,
                    'name' => $name,
                    'property_number' => $device->property_number,
                    'serial_number' => $device->serial_number,
                    'computer_name' => $device->computer_name,
                    'brand_model' => $brandModel,
                    'condition' => $device->condition,
                    'label' => collect([
                        $name,
                        $device->property_number ? 'Property #: ' . $device->property_number : null,
                        $device->serial_number ? 'Serial #: ' . $device->serial_number : null,
                        $brandModel ?: null,
                    ])->filter()->join(' | '),
                ];
            })
            ->values();

        return response()->json(['results' => $devices]);
    }

    /**
     * Fast property-number lookup used when linking a peripheral to the
     * equipment record it belongs to (for example Monitor -> Desktop).
     */
    public function propertyLookup(Request $request)
    {
        $tokens = $this->searchTokens($request->string('q')->toString());
        $excludeId = $request->integer('exclude_id') ?: null;

        $devices = Device::query()
            ->select(['id', 'device_type_id', 'property_number', 'part_of_property_number', 'serial_number', 'brand', 'model'])
            ->with('type:id,name')
            ->whereNull('part_of_property_number')
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->when($tokens !== [], function ($query) use ($tokens) {
                foreach ($tokens as $token) {
                    $query->where(function ($sub) use ($token) {
                        $like = "%{$token}%";

                        $sub->where('property_number', 'like', $like)
                            ->orWhere('part_of_property_number', 'like', $like)
                            ->orWhere('serial_number', 'like', $like)
                            ->orWhere('brand', 'like', $like)
                            ->orWhere('model', 'like', $like)
                            ->orWhereHas('type', fn ($type) => $type->where('name', 'like', $like));
                    });
                }
            })
            ->orderBy('property_number')
            ->limit(15)
            ->get()
            ->map(function (Device $device) {
                $brandModel = trim(($device->brand ?? '') . ' ' . ($device->model ?? ''));
                $typeName = $device->type?->name ?? 'Equipment';

                return [
                    'id' => $device->id,
                    'property_number' => $device->property_number,
                    'type' => $typeName,
                    'serial_number' => $device->serial_number,
                    'label' => collect([
                        $device->property_number,
                        $typeName,
                        $brandModel ?: null,
                        $device->serial_number ? 'Serial #: ' . $device->serial_number : null,
                    ])->filter()->join(' | '),
                ];
            })
            ->values();

        return response()->json(['results' => $devices]);
    }

    public function create()
    {
        $types = $this->allowedDeviceTypes();

        return view('admin.devices.create', compact('types'));
    }

    public function store(StoreDeviceRequest $request)
    {
        $data = $request->validated();
        unset($data['equipment_photo']);

        /*
        |--------------------------------------------------------------------------
        | Default Device Availability
        |--------------------------------------------------------------------------
        | Every newly added device is automatically available.
        | Do not let the form decide this.
        */
        $data['status'] = 'available';

        /*
        |--------------------------------------------------------------------------
        | Default Device Condition
        |--------------------------------------------------------------------------
        | Device condition is separate from availability.
        | condition = serviceable / unserviceable
        | status = available / issued / repair / retired
        */
        $data['condition'] = $data['condition'] ?? 'serviceable';

        $data = $this->cleanDeviceDataByType($data);

        if ($photoPath = $this->storeEquipmentPhoto($request)) {
            $data['photo_path'] = $photoPath;
        }

        if (blank($data['property_number'] ?? null) && filled($data['part_of_property_number'] ?? null)) {
            $data['property_number'] = $this->generateLinkedPropertyNumber(
                (string) $data['part_of_property_number'],
                (int) $data['device_type_id']
            );
        }

        $device = Device::create($data);
        $device->load('type');

        $summary = [
            'property_number' => $device->property_number,
            'device_type' => optional($device->type)->name,
            'brand' => $device->brand,
        ];

        if ($this->isComputerDevice(optional($device->type)->name)) {
            $summary['computer_name'] =
                $device->computer_name ?: data_get($device->specs, 'computer_name');
        }

        foreach ([
            'model' => $device->model,
            'serial_number' => $device->serial_number,
            'mac_address' => $device->mac_address,
            'windows_version' => $device->os_version,
            'windows_license' => $device->os_license,
            'ms_office_version' => $device->ms_office_version,
            'ms_office_license' => $device->ms_office_license,
            'memory' => data_get($device->specs, 'memory'),
            'storage' => data_get($device->specs, 'storage'),
            'form_factor' => data_get($device->specs, 'form_factor'),
            'unit_price' => $device->unit_price,
            'condition' => $device->condition,
            'part_of_property_number' => $device->part_of_property_number,
            'status' => $device->status,
            'maintenance_remarks' => $device->maintenance_remarks,
            'photo' => $device->photo_path ? 'Uploaded' : null,
            'notes' => $device->notes,
        ] as $key => $value) {
            if (filled($value)) {
                $summary[$key] = $value;
            }
        }

        ActivityLog::record(
            'created',
            "Added device \"{$device->property_number}\"",
            $device,
            ActivityLog::makePayload($summary)
        );

        return redirect()
            ->back()
            ->with('success', 'Equipment added successfully.');
    }

    public function show(Device $device)
    {
        $device->load([
            'type',
            'currentAssignment.staff.office.location',
            'currentAssignment.office.location',
            'currentAssignment.location',
            'latestMaintenanceRecord',
        ]);

        $types = $this->allowedDeviceTypes();
        return view('admin.devices.show', compact('device', 'types'));
    }

    public function reissue(Request $request, Device $device)
    {
        $data = $request->validate([
            'staff_id' => ['required', 'exists:staff,id'],
            'remarks' => ['required', 'string', 'max:1000'],
        ], [
            'staff_id.required' => 'Please select a registered end user.',
            'staff_id.exists' => 'The selected end user could not be found.',
            'remarks.required' => 'Please enter reissue remarks.',
        ]);

        $staff = Staff::query()
            ->with('office.location')
            ->findOrFail($data['staff_id']);

        $assignment = $device->currentAssignment()
            ->with(['staff.office.location', 'office.location', 'location'])
            ->first();
        $from = $this->assignmentContext($assignment);
        // Reissue has no location input. The location follows the selected
        // end user's registered office/location instead of a stale relocation.
        $reissueLocation = $staff->office?->location;
        $reissueLocationId = $reissueLocation?->id;
        $reissueLocationLabel = $reissueLocation
            ? (($reissueLocation->code ? $reissueLocation->code . ' - ' : '') . $reissueLocation->name)
            : null;

        if ($assignment && (int) $assignment->staff_id === (int) $staff->id) {
            return back()->withErrors(['staff_id' => 'This equipment is already assigned to the selected end user.'])->withInput();
        }

        $reissueRemarks = trim($data['remarks']);

        if ($assignment) {
            $assignment->update([
                'returned_at' => now(),
                'remarks' => trim(($assignment->remarks ? $assignment->remarks . ' ' : '') . 'Reissued on ' . now()->format('M d, Y h:i A') . '.'),
            ]);
        }

        DeviceAssignment::create([
            'device_id' => $device->id,
            'staff_id' => $staff->id,
            'office_id' => $staff->office_id,
            'location_id' => $reissueLocationId,
            'issued_by' => Auth::id(),
            'issued_at' => now(),
            'remarks' => $reissueRemarks,
        ]);

        $device->update(['status' => 'issued']);

        ActivityLog::record(
            'reissued',
            "Reissued equipment \"{$device->property_number}\"",
            $device,
            ActivityLog::makePayload([
                'property_number' => $device->property_number,
                'from_end_user' => $from['staff_name'],
                'from_office' => $from['office_name'],
                'from_location' => $from['location_name'],
                'to_end_user' => trim($staff->first_name . ' ' . $staff->last_name),
                'to_office' => $staff->office?->name,
                'to_location' => $reissueLocationLabel,
                'remarks' => $reissueRemarks,
                'reissued_by' => Auth::user()?->name,
                'reissued_at' => now()->format('M d, Y h:i A'),
            ])
        );

        return back()->with('success', 'Equipment reissued successfully.');
    }

    public function issue(Request $request, Device $device)
    {
        $locationId = $request->integer('location_id') ?: null;

        $data = $request->validate([
            'staff_id' => ['required', 'exists:staff,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'remarks' => [$locationId ? 'required' : 'nullable', 'string', 'max:1000'],
        ], [
            'staff_id.required' => 'Please select a staff member to issue this equipment to.',
            'staff_id.exists' => 'The selected staff member could not be found.',
            'remarks.required' => 'Please enter issuance remarks.',
        ]);

        $device = Device::query()
            ->with('type')
            ->whereKey($device->id)
            ->where('status', 'available')
            ->whereDoesntHave('currentAssignment')
            ->first();

        if (!$device) {
            return back()
                ->withErrors([
                    'staff_id' => 'This equipment is not available or has already been issued.',
                ])
                ->withInput();
        }

        $staff = Staff::query()
            ->with('office.location')
            ->findOrFail($data['staff_id']);

        $staffLocationId = $staff->office?->location_id;
        if ($locationId && (int) $staffLocationId !== (int) $locationId) {
            return back()
                ->withErrors(['staff_id' => 'The selected end user does not belong to this location.'])
                ->withInput();
        }

        DeviceAssignment::create([
            'device_id' => $device->id,
            'staff_id' => $staff->id,
            'office_id' => $staff->office_id,
            'location_id' => $staffLocationId,
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

        if (in_array($type, ['desktop', 'laptop'], true)) {
            $summary['computer_name'] = $device->computer_name ?: data_get($device->specs, 'computer_name');
        }

        $summary += [
            'brand' => $device->brand,
            'issued_to' => trim($staff->first_name . ' ' . $staff->last_name),
            'office' => optional($staff->office)->name,
            'location' => optional(optional($staff->office)->location)->name,
            'status' => 'Available → Issued',
            'issued_by' => Auth::user()?->name,
            'issued_at' => now()->format('M d, Y h:i A'),
        ];

        if (filled($data['remarks'] ?? null)) {
            $summary['remarks'] = $data['remarks'];
        }

        ActivityLog::record(
            'issued',
            "Issued equipment \"{$device->property_number}\"",
            $device,
            ActivityLog::makePayload($summary, [
                'status' => [
                    'old' => 'available',
                    'new' => 'issued',
                ],
            ])
        );

        return back()->with('success', 'Equipment issued successfully.');
    }

    public function edit(Device $device)
    {
        $device->load('type');

        $types = $this->allowedDeviceTypes();

        return view('admin.devices.edit', compact('device', 'types'));
    }

    public function update(UpdateDeviceRequest $request, Device $device)
    {
        $data = $request->validated();
        unset($data['equipment_photo']);

        /*
        |--------------------------------------------------------------------------
        | Keep existing status if not submitted
        |--------------------------------------------------------------------------
        | This prevents accidentally changing issued/available status from forms
        | that do not include a status field.
        */
        if (!array_key_exists('status', $data)) {
            unset($data['status']);
        }

        $data['condition'] = $data['condition'] ?? $device->condition ?? 'serviceable';

        $data = $this->cleanDeviceDataByType($data);
        $oldPhotoPath = $device->photo_path;

        if ($photoPath = $this->storeEquipmentPhoto($request)) {
            $data['photo_path'] = $photoPath;
        }

        if (blank($data['property_number'] ?? null) && filled($data['part_of_property_number'] ?? null)) {
            $data['property_number'] = $device->property_number ?: $this->generateLinkedPropertyNumber(
                (string) $data['part_of_property_number'],
                (int) ($data['device_type_id'] ?? $device->device_type_id)
            );
        }

        $before = [
            'property_number' => $device->property_number,
            'device_type' => optional($device->type)->name,
            'computer_name' => $device->computer_name ?: data_get($device->specs, 'computer_name'),
            'brand' => $device->brand,
            'model' => $device->model,
            'serial_number' => $device->serial_number,
            'mac_address' => $device->mac_address,

            'windows_version' => $device->os_version,
            'windows_license' => $device->os_license,
            'ms_office_version' => $device->ms_office_version,
            'ms_office_license' => $device->ms_office_license,

            'memory' => data_get($device->specs, 'memory'),
            'storage' => data_get($device->specs, 'storage'),
            'form_factor' => data_get($device->specs, 'form_factor'),

            'unit_price' => $device->unit_price,

            'condition' => $device->condition,
            'part_of_property_number' => $device->part_of_property_number,
            'status' => $device->status,
            'maintenance_remarks' => $device->maintenance_remarks,
            'photo' => $device->photo_path ? 'Uploaded' : null,
            'notes' => $device->notes,
        ];

        $device->update($data);
        if (($data['photo_path'] ?? null) && $oldPhotoPath && $oldPhotoPath !== $data['photo_path']) {
            $this->deleteEquipmentPhoto($oldPhotoPath);
        }
        $device->load('type');

        $summary = [
            'property_number' => $device->property_number,
            'device_type' => optional($device->type)->name,
            'brand' => $device->brand,
        ];

        if ($this->isComputerDevice(optional($device->type)->name)) {
            $summary['computer_name'] =
                $device->computer_name ?: data_get($device->specs, 'computer_name');
        }

        foreach ([
            'model' => $device->model,
            'serial_number' => $device->serial_number,
            'mac_address' => $device->mac_address,
            'windows_version' => $device->os_version,
            'windows_license' => $device->os_license,
            'ms_office_version' => $device->ms_office_version,
            'ms_office_license' => $device->ms_office_license,
            'memory' => data_get($device->specs, 'memory'),
            'storage' => data_get($device->specs, 'storage'),
            'form_factor' => data_get($device->specs, 'form_factor'),
            'unit_price' => $device->unit_price,
            'condition' => $device->condition,
            'part_of_property_number' => $device->part_of_property_number,
            'status' => $device->status,
            'maintenance_remarks' => $device->maintenance_remarks,
            'photo' => $device->photo_path ? 'Uploaded' : null,
            'notes' => $device->notes,
        ] as $key => $value) {

            if (filled($value)) {
                $summary[$key] = $value;
            }
        }

        ActivityLog::record(
            'updated',
            "Updated device \"{$device->property_number}\"",
            $device,
            ActivityLog::makePayload(
                $summary,
                ActivityLog::buildChanges(
                    $before,
                    [
                        'property_number' => $device->property_number,
                        'device_type' => optional($device->type)->name,
                        'computer_name' => $device->computer_name ?: data_get($device->specs, 'computer_name'),
                        'brand' => $device->brand,
                        'model' => $device->model,
                        'serial_number' => $device->serial_number,
                        'mac_address' => $device->mac_address,

                        'windows_version' => $device->os_version,
                        'windows_license' => $device->os_license,
                        'ms_office_version' => $device->ms_office_version,
                        'ms_office_license' => $device->ms_office_license,

                        'memory' => data_get($device->specs, 'memory'),
                        'storage' => data_get($device->specs, 'storage'),
                        'form_factor' => data_get($device->specs, 'form_factor'),

                        'unit_price' => $device->unit_price,

                        'condition' => $device->condition,
                        'status' => $device->status,
                        'maintenance_remarks' => $device->maintenance_remarks,
                        'photo' => $device->photo_path ? 'Uploaded' : null,
                        'notes' => $device->notes,
                    ]
                ) ?? []
            )
        );

        return redirect()
            ->route('admin.devices.index')
            ->with('success', 'Equipment updated.');
    }

    /**
     * Capture and save an equipment photo without requiring the full edit form.
     */
    public function updatePhoto(Request $request, Device $device)
    {
        $request->validate([
            'equipment_photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:10240'],
        ], [
            'equipment_photo.required' => 'Please take a photo before submitting.',
            'equipment_photo.mimes' => 'The equipment photo must be a JPG, PNG, WEBP, HEIC, or HEIF file.',
            'equipment_photo.max' => 'The equipment photo must not be larger than 10 MB.',
        ]);

        $oldPhotoPath = $device->photo_path;
        $photoPath = $this->storeEquipmentPhoto($request);

        if (! $photoPath) {
            return back()->withErrors(['equipment_photo' => 'The equipment photo could not be captured.']);
        }

        $device->update(['photo_path' => $photoPath]);
        $this->deleteEquipmentPhoto($oldPhotoPath);

        ActivityLog::record(
            'updated',
            "Updated photo for device \"{$device->property_number}\"",
            $device,
            ActivityLog::makePayload(
                ['property_number' => $device->property_number, 'photo' => 'Uploaded'],
                ['photo' => ['old' => $oldPhotoPath ? 'Uploaded' : null, 'new' => 'Uploaded']]
            )
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Equipment photo updated successfully.',
                'photo_url' => asset('storage/' . $photoPath),
            ]);
        }

        return back()->with('success', 'Equipment photo updated successfully.');
    }

    /**
     * Remove the equipment photo without changing the rest of the record.
     */
    public function destroyPhoto(Request $request, Device $device)
    {
        $oldPhotoPath = $device->photo_path;

        if ($oldPhotoPath) {
            $device->update(['photo_path' => null]);
            $this->deleteEquipmentPhoto($oldPhotoPath);

            ActivityLog::record(
                'updated',
                "Removed photo for device \"{$device->property_number}\"",
                $device,
                ActivityLog::makePayload(
                    ['property_number' => $device->property_number, 'photo' => null],
                    ['photo' => ['old' => 'Uploaded', 'new' => null]]
                )
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $oldPhotoPath
                    ? 'Equipment photo cleared successfully.'
                    : 'No equipment photo to clear.',
            ]);
        }

        return back()->with('success', $oldPhotoPath
            ? 'Equipment photo cleared successfully.'
            : 'No equipment photo to clear.');
    }

    public function destroy(Device $device)
    {
        $deviceType = DeviceType::where('id', $device->device_type_id)->value('name');
        $isComputer = $this->isComputerDevice($deviceType);
        $isDesktop = strtolower((string) $deviceType) === 'desktop';

        $summary = [
            'property_number' => $device->property_number,
            'device_type' => $deviceType,
            'brand' => $device->brand,
        ];

        if ($isComputer) {
            $summary['computer_name'] = $device->computer_name ?: data_get($device->specs, 'computer_name');
        }

        $summary += [
            'model' => $device->model,
            'serial_number' => $device->serial_number,
            'unit_price' => $device->unit_price,
            'condition' => $device->condition,
            'status' => $device->status,
            'maintenance_remarks' => $device->maintenance_remarks,
            'photo' => $device->photo_path ? 'Uploaded' : null,
            'notes' => $device->notes,
        ];

        // Deleted-device snapshot: show every applicable field regardless of
        // whether it has a value, so the log preserves the device's full
        // last-known state. date_acquired and last_maintenance_date are
        // intentionally left out of this snapshot entirely.
        if ($isComputer) {
            $summary += [
                'mac_address' => $device->mac_address,
                'windows_version' => $device->os_version,
                'windows_license' => $device->os_license,
                'ms_office_version' => $device->ms_office_version,
                'ms_office_license' => $device->ms_office_license,
                'memory' => data_get($device->specs, 'memory'),
                'storage' => data_get($device->specs, 'storage'),
            ];

            // Form factor only applies to desktops, never laptops.
            if ($isDesktop) {
                $summary['form_factor'] = data_get($device->specs, 'form_factor');
            }
        }

        ActivityLog::record(
            'deleted',
            "Deleted device \"{$summary['property_number']}\"",
            $device,
            ActivityLog::makePayload($summary)
        );

        DeviceAssignment::where('device_id', $device->id)->delete();
        DeviceMaintenanceRecord::where('device_id', $device->id)->delete();
        $this->deleteEquipmentPhoto($device->photo_path);
        $device->delete();
        return redirect()
            ->route('admin.devices.index')
            ->with('success', 'Equipment deleted.');
    }

    /**
     * Delete several equipment records in one confirmed operation.
     * Assignments and maintenance records are explicitly removed so the
     * equipment history is removed together with the selected equipment.
     */
    public function bulkDestroy(Request $request)
    {
        abort_unless(Auth::user()?->isAdmin() || Auth::user()?->isUnitHead(), 403);

        $selectAll = $request->boolean('select_all');

        if ($selectAll) {
            $data = $request->validate([
                'select_all' => ['required', 'boolean'],
                'filter_q' => ['nullable', 'string', 'max:255'],
                'filter_type' => ['nullable', 'integer', 'exists:device_types,id'],
                'filter_location' => ['nullable', 'integer', 'exists:locations,id'],
                'filter_office' => ['nullable', 'integer', 'exists:offices,id'],
                'filter_status' => ['nullable', 'in:available,issued,repair,retired'],
                'filter_condition' => ['nullable', 'in:serviceable,unserviceable,condemned'],
            ]);

            $devices = Device::with('type')
                ->filterInventory([
                    'q' => $data['filter_q'] ?? '',
                    'type_id' => $data['filter_type'] ?? null,
                    'location_id' => $data['filter_location'] ?? null,
                    'office_id' => $data['filter_office'] ?? null,
                    'status' => $data['filter_status'] ?? null,
                    'condition' => $data['filter_condition'] ?? null,
                ])
                ->get();
        } else {
            $data = $request->validate([
                'device_ids' => ['required', 'array', 'min:1'],
                'device_ids.*' => ['integer', 'distinct', 'exists:devices,id'],
            ]);

            $devices = Device::with('type')
                ->whereIn('id', $data['device_ids'])
                ->get();
        }

        if ($devices->isEmpty()) {
            return back()->withErrors(['device_ids' => 'No equipment was selected.']);
        }

        $items = $devices->map(function (Device $device) {
            return [
                'id' => $device->id,
                'property_number' => $device->property_number,
                'device_type' => $device->type?->name,
                'serial_number' => $device->serial_number,
                'status' => $device->status,
                'condition' => $device->condition,
            ];
        })->values()->all();

        DB::transaction(function () use ($devices, $items) {
            $ids = $devices->modelKeys();
            $photoPaths = $devices->pluck('photo_path')->filter()->values();

            // Remove all history rows before deleting the equipment rows.
            DeviceAssignment::whereIn('device_id', $ids)->delete();
            DeviceMaintenanceRecord::whereIn('device_id', $ids)->delete();
            ActivityLog::whereIn('subject_id', $ids)
                ->whereIn('subject_type', ['Device', 'Equipment'])
                ->delete();

            Device::whereIn('id', $ids)->delete();

            $photoPaths->each(fn ($path) => $this->deleteEquipmentPhoto($path));

            ActivityLog::record(
                'deleted',
                'Deleted ' . count($items) . ' equipment record(s) in bulk',
                null,
                [
                    'bulk' => true,
                    'record_type' => 'Equipment',
                    'items' => $items,
                ]
            );
        });

        return redirect()
            ->route('admin.devices.index')
            ->with('success', count($items) . ' equipment record(s) and their history were deleted.');
    }

    /**
     * Import complete equipment rows. The same row may contain all inventory
     * specifications plus an optional existing end user and issuance details.
     */
    public function import(Request $request)
    {
        abort_unless(Auth::user()?->isAdmin() || Auth::user()?->isUnitHead(), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $dryRun = $request->boolean('dry_run');

        try {
            $rows = Excel::toCollection(
                new DeviceInventoryImport,
                $request->file('file')
            )->first() ?? collect();
        } catch (\Throwable $exception) {
            report($exception);

            $result = $this->importFailureResult(0, 'The file could not be read.');
            $this->recordImportAudit($dryRun ? 'import_preview_failed' : 'import_failed', $request, $result, $dryRun);

            return back()->withErrors([
                'file' => 'The file could not be read. Please upload a valid CSV, XLSX, or XLS workbook.',
            ]);
        }

        if ($rows->isEmpty()) {
            $result = $this->importFailureResult(0, 'The file has no data rows.');
            $this->recordImportAudit($dryRun ? 'import_preview_failed' : 'import_failed', $request, $result, $dryRun);

            return back()->withErrors(['file' => 'The import file has no data rows.']);
        }

        if ($rows->count() > DeviceInventoryImport::MAX_ROWS) {
            $result = $this->importFailureResult($rows->count(), 'The file exceeds the row limit.');
            $this->recordImportAudit($dryRun ? 'import_preview_failed' : 'import_failed', $request, $result, $dryRun);

            return back()->withErrors([
                'file' => 'The import file exceeds the 5,000-row limit. Split it into smaller files and try again.',
            ]);
        }

        $this->resetImportLookupCache();
        DB::beginTransaction();

        try {
            $result = $this->processImportedRows($rows);

            if ($result['error_count'] > 0) {
                DB::rollBack();

                $this->recordImportAudit(
                    $dryRun ? 'import_preview_failed' : 'import_failed',
                    $request,
                    $result,
                    $dryRun
                );

                return back()
                    ->withErrors(['file' => 'Import was not applied. Review the row errors below.'])
                    ->with('import_preview', $this->importPreviewPayload($result, $dryRun, false));
            }

            if ($dryRun) {
                DB::rollBack();
                $this->recordImportAudit('import_previewed', $request, $result, true);

                return back()
                    ->with('import_preview', $this->importPreviewPayload($result, true, false));
            }

            DB::commit();
        } catch (\Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            report($exception);

            $result = $this->importFailureResult(
                $rows->count(),
                'The import could not be completed because of a server-side error.'
            );

            $this->recordImportAudit('import_failed', $request, $result, $dryRun);

            return back()
                ->withErrors(['file' => 'Import failed. Please verify the file and try again.'])
                ->with('import_preview', $this->importPreviewPayload($result, $dryRun, false));
        }

        $this->recordImportAudit('imported', $request, $result, false);

        $message = "{$result['created']} equipment added, {$result['updated']} equipment updated"
            . ($result['issued'] ? ", {$result['issued']} issuance record(s) created." : '.');

        return back()->with('success', $message);
    }

    private function processImportedRows($rows): array
    {
        $result = [
            'total_rows' => $rows->count(),
            'processed_rows' => 0,
            'created' => 0,
            'updated' => 0,
            'issued' => 0,
            'error_count' => 0,
            'errors' => [],
        ];
        $seenPropertyNumbers = [];

        foreach ($rows as $index => $rawRow) {
            $rawRow = $rawRow instanceof \Illuminate\Support\Collection
                ? $rawRow->toArray()
                : (array) $rawRow;

            $rowNumber = $index + 2; // account for the heading row

            if (count($rawRow) > self::IMPORT_MAX_COLUMNS) {
                $this->addImportRowError($result, $rowNumber, 'The row contains too many columns.');

                if ($result['error_count'] >= self::IMPORT_MAX_ERRORS) {
                    break;
                }

                continue;
            }

            $row = $this->normalizeImportRow($rawRow);

            if ($this->importRowIsEmpty($row)) {
                continue;
            }

            $result['processed_rows']++;

            try {
                $propertyNumber = strtolower(trim((string) $this->importValue($row, ['property_number'])));
                if ($propertyNumber !== '') {
                    if (array_key_exists($propertyNumber, $seenPropertyNumbers)) {
                        throw new \RuntimeException('property_number is duplicated in this import file.');
                    }

                    $seenPropertyNumbers[$propertyNumber] = $rowNumber;
                }

                [$wasCreated, $wasIssued] = $this->persistImportedInventoryRow($row);
                $wasCreated ? $result['created']++ : $result['updated']++;

                if ($wasIssued) {
                    $result['issued']++;
                }
            } catch (\Throwable $exception) {
                if (! $exception instanceof \RuntimeException) {
                    report($exception);
                }

                $this->addImportRowError(
                    $result,
                    $rowNumber,
                    $this->safeImportExceptionMessage($exception)
                );

                if ($result['error_count'] >= self::IMPORT_MAX_ERRORS) {
                    break;
                }
            }
        }

        return $result;
    }

    private function addImportRowError(array &$result, int $rowNumber, string $message): void
    {
        $result['error_count']++;

        if (count($result['errors']) < self::IMPORT_MAX_ERRORS) {
            $result['errors'][] = "Row {$rowNumber}: {$message}";
        }

        if ($result['error_count'] === self::IMPORT_MAX_ERRORS) {
            $result['errors'][] = 'Additional row errors were omitted. Fix the listed errors and preview the file again.';
        }
    }

    private function safeImportExceptionMessage(\Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        if ($exception instanceof \RuntimeException
            && $message !== ''
            && ! preg_match('/SQLSTATE|database|table|column|constraint|file_get_contents|storage_path|vendor[\\\/]|app[\\\/]|undefined|call to |[A-Z]:\\\\/i', $message)) {
            $cleanMessage = preg_replace('/\s+/', ' ', $message) ?: $message;

            return Str::limit($cleanMessage, 240, '...');
        }

        return 'Unable to process this row. Check the required fields and values.';
    }

    private function importFailureResult(int $totalRows, string $message): array
    {
        return [
            'total_rows' => $totalRows,
            'processed_rows' => 0,
            'created' => 0,
            'updated' => 0,
            'issued' => 0,
            'error_count' => 1,
            'errors' => [$message],
        ];
    }

    private function importPreviewPayload(array $result, bool $dryRun, bool $applied): array
    {
        return [
            'mode' => $dryRun ? 'preview' : 'import',
            'applied' => $applied,
            'total_rows' => $result['total_rows'],
            'processed_rows' => $result['processed_rows'],
            'created' => $result['created'],
            'updated' => $result['updated'],
            'issued' => $result['issued'],
            'error_count' => $result['error_count'],
            'errors' => $result['errors'],
        ];
    }

    private function recordImportAudit(string $action, Request $request, array $result, bool $dryRun): void
    {
        try {
            ActivityLog::record(
                $action,
                $dryRun ? 'Previewed equipment inventory import' : 'Imported equipment inventory',
                null,
                ActivityLog::makePayload([
                    'mode' => $dryRun ? 'preview' : 'commit',
                    'file_type' => strtolower((string) $request->file('file')?->getClientOriginalExtension()),
                    'file_size_kb' => round(((int) $request->file('file')?->getSize()) / 1024, 1),
                    'rows' => $result['total_rows'],
                    'processed_rows' => $result['processed_rows'],
                    'created' => $result['created'],
                    'updated' => $result['updated'],
                    'issuance_records' => $result['issued'],
                    'errors' => $result['error_count'],
                ])
            );
        } catch (\Throwable $exception) {
            // An audit failure must not turn a completed import into an error.
            report($exception);
        }
    }

    private function resetImportLookupCache(): void
    {
        $this->importLookupCache = [
            'types' => [],
            'staff' => [],
            'locations' => [],
            'offices' => [],
        ];
    }

    public function importTemplate()
    {
        return Excel::download(
            new EquipmentImportTemplateExport,
            'equipment-import-template.xls',
            \Maatwebsite\Excel\Excel::XLS,
            ['Content-Type' => 'application/vnd.ms-excel']
        );
    }

    private function persistImportedInventoryRow(array $row): array
    {
        $this->validateImportedRow($row);

        $propertyNumber = $this->importValue($row, ['property_number', 'property_no', 'asset_number', 'asset_no']);
        $propertyNumber = blank($propertyNumber) ? null : trim((string) $propertyNumber);
        $partOfPropertyNumber = $this->importValue($row, ['part_of_property_number', 'parent_property_number', 'parent_property_no']);
        $partOfPropertyNumber = blank($partOfPropertyNumber) ? null : trim((string) $partOfPropertyNumber);

        $typeName = $this->importValue($row, ['equipment_type', 'device_type', 'type']);
        if (blank($typeName)) {
            throw new \RuntimeException('equipment_type is required.');
        }

        $typeName = trim((string) $typeName);
        $typeKey = strtolower($typeName);

        if (array_key_exists($typeKey, $this->importLookupCache['types'])) {
            $type = $this->importLookupCache['types'][$typeKey];
        } else {
            $type = DeviceType::query()
                ->whereRaw('LOWER(name) = ?', [$typeKey])
                ->first();

            if (! $type) {
                $allowedTypeNames = [
                    'Desktop', 'Laptop', 'Printer', 'Monitor', 'UPS', 'AVR', 'Scanner', 'Other',
                ];
                $canonicalTypeName = collect($allowedTypeNames)
                    ->first(fn (string $allowedType) => strtolower($allowedType) === $typeKey);

                if (! $canonicalTypeName) {
                    throw new \RuntimeException('Equipment type is not supported. Use one of the types in the import template.');
                }

                $type = DeviceType::firstOrCreate(
                    ['name' => $canonicalTypeName],
                    ['slug' => strtolower(str_replace(' ', '-', $canonicalTypeName))]
                );
            }

            $this->importLookupCache['types'][$typeKey] = $type;
        }

        if (blank($propertyNumber) && filled($partOfPropertyNumber)) {
            $propertyNumber = $this->generateLinkedPropertyNumber($partOfPropertyNumber, (int) $type->id);
        }

        if (blank($propertyNumber)) {
            throw new \RuntimeException('property_number or part_of_property_number is required.');
        }

        $device = Device::firstOrNew(['property_number' => $propertyNumber]);
        $wasCreated = !$device->exists;
        $device->device_type_id = $type->id;

        if (filled($partOfPropertyNumber)) {
            if (strcasecmp($partOfPropertyNumber, $propertyNumber) === 0) {
                throw new \RuntimeException('part_of_property_number cannot be the same as property_number.');
            }

            if (! Device::where('property_number', $partOfPropertyNumber)
                ->whereNull('part_of_property_number')
                ->exists()) {
                throw new \RuntimeException('part_of_property_number must match an existing equipment property number.');
            }

            $device->part_of_property_number = $partOfPropertyNumber;
        }

        foreach ([
            'serial_number', 'computer_name', 'brand', 'model', 'mac_address',
            'os_version', 'os_license', 'ms_office_version', 'ms_office_license',
            'maintenance_remarks', 'notes',
        ] as $field) {
            // Empty cells are intentionally ignored for existing records so a
            // partial import cannot erase specifications. Use the edit form to
            // explicitly clear a value.
            if (array_key_exists($field, $row) && filled($row[$field])) {
                $device->{$field} = $row[$field];
            }
        }

        if (array_key_exists('unit_price', $row) && filled($row['unit_price'])) {
            $unitPrice = str_replace(',', '', (string) $row['unit_price']);
            if (!is_numeric($unitPrice) || (float) $unitPrice < 0) {
                throw new \RuntimeException('unit_price must be a non-negative number.');
            }
            $device->unit_price = $unitPrice;
        }

        if (array_key_exists('date_acquired', $row) && filled($row['date_acquired'])) {
            $device->date_acquired = $this->importDate($row['date_acquired'], 'date_acquired');
        }

        if (array_key_exists('last_maintenance_date', $row) && filled($row['last_maintenance_date'])) {
            $device->last_maintenance_date = $this->importDate($row['last_maintenance_date'], 'last_maintenance_date');
        }

        if (array_key_exists('condition', $row) && filled($row['condition'])) {
            $condition = strtolower(trim((string) $row['condition']));
            if (!in_array($condition, ['serviceable', 'unserviceable', 'condemned'], true)) {
                throw new \RuntimeException('condition must be serviceable, unserviceable, or condemned.');
            }
            $device->condition = $condition;
        } elseif ($wasCreated) {
            $device->condition = 'serviceable';
        }

        $importedStatus = null;
        $statusValue = $this->importValue($row, ['status', 'availability']);
        if (filled($statusValue)) {
            $status = strtolower(trim((string) $statusValue));
            if (!in_array($status, ['available', 'repair', 'retired', 'issued'], true)) {
                throw new \RuntimeException('status must be available, issued, repair, or retired.');
            }
            if ($status === 'issued' && !$this->importStaffDetailsPresent($row) && !$this->importLocationDetailsPresent($row)) {
                throw new \RuntimeException('An issued status requires an end user or location in the same row.');
            }
            $importedStatus = $status;
            $device->status = $status === 'issued' ? 'available' : $status;

            if ($status !== 'issued'
                && ($this->importStaffDetailsPresent($row) || $this->importLocationDetailsPresent($row))) {
                throw new \RuntimeException('Staff, office, or location assignment details require status to be issued.');
            }

            if ($status !== 'issued' && $device->currentAssignment()->exists()) {
                throw new \RuntimeException('The imported status conflicts with an active assignment. Use issued or close the existing assignment first.');
            }
        } elseif ($wasCreated) {
            $device->status = 'available';
        }

        // A location/office-only row is an intentional shared assignment even
        // when the status column was left blank. Treat the assignment details
        // as issued so the imported relationship is not silently discarded.
        if ($importedStatus === null
            && $this->importLocationDetailsPresent($row)
            && ! $this->importStaffDetailsPresent($row)) {
            $importedStatus = 'issued';
        }

        $specs = is_array($device->specs) ? $device->specs : [];
        foreach (['memory', 'storage', 'form_factor'] as $specField) {
            if (array_key_exists($specField, $row) && filled($row[$specField])) {
                $specs[$specField] = $row[$specField];
            }
        }
        $device->specs = $specs ?: null;
        $device->save();

        $wasIssued = false;
        if ($this->importStaffDetailsPresent($row)) {
            $wasIssued = $this->issueImportedDevice($device->fresh(), $row);
        } elseif ($importedStatus === 'issued' && $this->importLocationDetailsPresent($row)) {
            $wasIssued = $this->assignImportedDeviceToLocation($device->fresh(), $row);
        } elseif ($device->currentAssignment()->exists() && $device->status !== 'issued') {
            $device->update(['status' => 'issued']);
        }

        return [$wasCreated, $wasIssued];
    }

    private function issueImportedDevice(Device $device, array $row): bool
    {
        $staff = $this->resolveImportedStaff($row);
        $currentAssignment = $device->currentAssignment()->with(['staff.office.location', 'office.location', 'location'])->first();

        $assignmentOffice = $staff->office;
        $assignmentLocation = $this->importLocationDetailsPresent($row)
            ? $this->resolveImportedLocation($row)
            : $assignmentOffice?->location;

        if (! $assignmentOffice) {
            throw new \RuntimeException('The selected staff member is not linked to an office.');
        }

        if ($this->importValue($row, ['office', 'office_name']) !== null) {
            $importedOffice = $this->resolveImportedOffice($row, $assignmentLocation);
            if ((int) $importedOffice->id !== (int) $assignmentOffice->id) {
                throw new \RuntimeException('The supplied office does not match the selected staff member.');
            }
        }

        if ($assignmentLocation && $assignmentOffice->location_id
            && (int) $assignmentLocation->id !== (int) $assignmentOffice->location_id) {
            throw new \RuntimeException('The supplied location does not match the selected staff member\'s office.');
        }

        if ($currentAssignment) {
            if ((int) $currentAssignment->staff_id === (int) $staff->id) {
                $updates = [
                    'office_id' => $assignmentOffice->id,
                    'location_id' => $assignmentLocation?->id,
                ];

                if (filled($this->importValue($row, ['issued_at', 'issue_date']))) {
                    $updates['issued_at'] = $this->importDate(
                        $this->importValue($row, ['issued_at', 'issue_date']),
                        'issued_at'
                    );
                }

                $remarks = $this->importValue($row, ['issuance_remarks', 'remarks']);
                if (filled($remarks)) {
                    $updates['remarks'] = $remarks;
                }

                $currentAssignment->update($updates);
                $device->update(['status' => 'issued']);

                return false;
            }

            $currentAssignment->update([
                'returned_at' => now(),
                'remarks' => trim(($currentAssignment->remarks ? $currentAssignment->remarks . ' ' : '') . 'Reissued through equipment import on ' . now()->format('M d, Y h:i A') . '.'),
            ]);
        }

        $issuedAt = filled($this->importValue($row, ['issued_at', 'issue_date']))
            ? $this->importDate($this->importValue($row, ['issued_at', 'issue_date']), 'issued_at')
            : now();

        DeviceAssignment::create([
            'device_id' => $device->id,
            'staff_id' => $staff->id,
            'office_id' => $assignmentOffice->id,
            'location_id' => $assignmentLocation?->id,
            'issued_by' => Auth::id(),
            'issued_at' => $issuedAt,
            'remarks' => $this->importValue($row, ['issuance_remarks', 'remarks']),
        ]);

        $device->update(['status' => 'issued']);

        return true;
    }

    private function assignImportedDeviceToLocation(Device $device, array $row): bool
    {
        $location = $this->resolveImportedLocation($row);
        $office = $this->resolveImportedOffice($row, $location);
        $currentAssignment = $device->currentAssignment()->with(['staff.office.location', 'office.location', 'location'])->first();

        if ($currentAssignment) {
            if (!$currentAssignment->staff_id
                && (int) $currentAssignment->location_id === (int) $location->id
                && (int) $currentAssignment->office_id === (int) ($office?->id ?? 0)) {
                $device->update(['status' => 'issued']);

                return false;
            }

            $currentAssignment->update([
                'returned_at' => now(),
                'remarks' => trim(($currentAssignment->remarks ? $currentAssignment->remarks . ' ' : '') . 'Relocated through equipment import on ' . now()->format('M d, Y h:i A') . '.'),
            ]);
        }

        $issuedAt = filled($this->importValue($row, ['issued_at', 'issue_date']))
            ? $this->importDate($this->importValue($row, ['issued_at', 'issue_date']), 'issued_at')
            : now();

        DeviceAssignment::create([
            'device_id' => $device->id,
            'staff_id' => null,
            'office_id' => $office?->id,
            'location_id' => $location->id,
            'issued_by' => Auth::id(),
            'issued_at' => $issuedAt,
            'remarks' => $this->importValue($row, ['issuance_remarks', 'remarks'])
                ?: 'Imported as location assignment.',
        ]);

        $device->update(['status' => 'issued']);

        return true;
    }

    private function resolveImportedStaff(array $row): Staff
    {
        $email = strtolower(trim((string) $this->importValue($row, [
            'staff_email', 'end_user_email', 'issued_to_email', 'assigned_to_email', 'email', 'user_email',
        ])));
        $staffName = trim((string) $this->importValue($row, ['staff_name', 'issued_to', 'end_user', 'user_name']));
        $firstName = trim((string) $this->importValue($row, ['first_name', 'staff_first_name']));
        $lastName = trim((string) $this->importValue($row, ['last_name', 'staff_last_name']));

        if (blank($firstName) && blank($lastName) && filled($staffName)) {
            if (str_contains($staffName, ',')) {
                [$lastName, $firstName] = array_map('trim', explode(',', $staffName, 2));
            } else {
                $parts = preg_split('/\s+/', $staffName);
                $firstName = array_shift($parts) ?: '';
                $lastName = implode(' ', $parts);
            }
        }

        if (blank($email) && blank($firstName) && blank($lastName)) {
            throw new \RuntimeException('Provide staff_email or staff_name/first_name and last_name.');
        }

        $officeName = strtolower(trim((string) $this->importValue($row, ['office', 'office_name'])));
        $locationName = strtolower(trim((string) $this->importValue($row, ['location_code', 'location', 'location_name'])));
        $cacheKey = implode('|', [$email, strtolower($firstName), strtolower($lastName), $officeName, $locationName]);

        if (array_key_exists($cacheKey, $this->importLookupCache['staff'])) {
            return $this->importLookupCache['staff'][$cacheKey];
        }

        if (filled($email)) {
            $candidates = Staff::query()
                ->with('office.location')
                ->where('is_active', true)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->limit(26)
                ->get();

            if ($candidates->isEmpty()) {
                throw new \RuntimeException('No active staff record found for the supplied email.');
            }

            if ($candidates->count() !== 1) {
                throw new \RuntimeException('Multiple active staff records match the supplied email.');
            }

            $scoped = $candidates->filter(function (Staff $staff) use ($officeName, $locationName) {
                $officeMatches = blank($officeName) || $this->importTextMatches($staff->office?->name, $officeName);
                $locationMatches = blank($locationName)
                    || $this->importTextMatches($staff->office?->location?->code, $locationName)
                    || $this->importTextMatches($staff->office?->location?->name, $locationName);

                return $officeMatches && $locationMatches;
            })->values();

            if ($scoped->isEmpty() && (filled($officeName) || filled($locationName))) {
                throw new \RuntimeException('The supplied email does not match the provided office or location.');
            }

            return $this->importLookupCache['staff'][$cacheKey] = $scoped->first();
        }

        if (blank($firstName) || blank($lastName)) {
            throw new \RuntimeException('Provide both first_name and last_name, or use staff_email.');
        }

        $firstNameLower = strtolower($firstName);
        $lastNameLower = strtolower($lastName);

        $candidates = Staff::query()
            ->with('office.location')
            ->where('is_active', true)
            ->where(function ($staff) use ($firstNameLower, $lastNameLower) {
                $staff->where(function ($exact) use ($firstNameLower, $lastNameLower) {
                    $exact->whereRaw('LOWER(first_name) = ?', [$firstNameLower])
                        ->whereRaw('LOWER(last_name) = ?', [$lastNameLower]);
                })->orWhere(function ($reversed) use ($firstNameLower, $lastNameLower) {
                    $reversed->whereRaw('LOWER(first_name) = ?', [$lastNameLower])
                        ->whereRaw('LOWER(last_name) = ?', [$firstNameLower]);
                });
            })
            ->limit(26)
            ->get();

        if ($candidates->isEmpty()) {
            throw new \RuntimeException('No active staff record found for the supplied name.');
        }

        $scoped = $candidates->filter(function (Staff $staff) use ($officeName, $locationName) {
            $officeMatches = blank($officeName) || $this->importTextMatches($staff->office?->name, $officeName);
            $locationMatches = blank($locationName)
                || $this->importTextMatches($staff->office?->location?->code, $locationName)
                || $this->importTextMatches($staff->office?->location?->name, $locationName);

            return $officeMatches && $locationMatches;
        });

        if ($scoped->count() === 1) {
            return $this->importLookupCache['staff'][$cacheKey] = $scoped->first();
        }

        if ($scoped->count() > 1) {
            throw new \RuntimeException('Multiple active staff records match the supplied identity. Add a unique staff_email.');
        }

        if (filled($officeName) || filled($locationName)) {
            throw new \RuntimeException('The supplied name does not match the provided office or location.');
        }

        throw new \RuntimeException('Multiple active staff records match the supplied name. Add a unique staff_email.');
    }

    private function resolveImportedLocation(array $row): Location
    {
        $locationName = trim((string) $this->importValue($row, ['location_code', 'location', 'location_name']));
        $cacheKey = strtolower($locationName . '|' . trim((string) $this->importValue($row, ['office', 'office_name'])));

        if (array_key_exists($cacheKey, $this->importLookupCache['locations'])) {
            return $this->importLookupCache['locations'][$cacheKey];
        }

        $location = null;

        if (filled($locationName)) {
            $matches = Location::query()
                ->whereRaw('LOWER(code) = ?', [strtolower($locationName)])
                ->orWhereRaw('LOWER(name) = ?', [strtolower($locationName)])
                ->get()
                ->unique('id')
                ->values();

            if ($matches->count() === 1) {
                $location = $matches->first();
            }

            if ($matches->count() > 1) {
                throw new \RuntimeException('Multiple locations match the supplied location code or name.');
            }

            if (! $location) {
                $matches = Location::query()
                    ->get()
                    ->filter(fn (Location $location) => $this->importTextMatches($location->code, $locationName)
                        || $this->importTextMatches($location->name, $locationName))
                    ->values();

                if ($matches->count() === 1) {
                    $location = $matches->first();
                }

                if ($matches->count() > 1) {
                    throw new \RuntimeException('Multiple locations match the supplied location value.');
                }
            }
        }

        $office = $this->resolveImportedOffice($row, $location);
        if ($office?->location) {
            if ($location && (int) $office->location_id !== (int) $location->id) {
                throw new \RuntimeException('The supplied office does not belong to the supplied location.');
            }

            $location ??= $office->location;
        }

        if (! $location) {
            throw new \RuntimeException('No matching location or office found for the supplied location details.');
        }

        return $this->importLookupCache['locations'][$cacheKey] = $location;
    }

    private function resolveImportedOffice(array $row, ?Location $location = null): ?Office
    {
        $officeName = trim((string) $this->importValue($row, ['office', 'office_name']));
        if ($officeName === '') {
            return null;
        }

        $cacheKey = strtolower($officeName . '|' . ($location?->id ?? ''));
        if (array_key_exists($cacheKey, $this->importLookupCache['offices'])) {
            return $this->importLookupCache['offices'][$cacheKey];
        }

        $query = Office::query()
            ->with('location')
            ->when($location, fn ($officeQuery) => $officeQuery->where('location_id', $location->id));

        $offices = (clone $query)
            ->whereRaw('LOWER(name) = ?', [strtolower($officeName)])
            ->get();

        if ($offices->isEmpty()) {
            $offices = $query->get()
                ->filter(fn (Office $office) => $this->importTextMatches($office->name, $officeName))
                ->values();
        }

        if ($offices->count() > 1) {
            throw new \RuntimeException('Multiple offices match the supplied office value. Add a more specific location.');
        }

        if ($offices->isEmpty()) {
            throw new \RuntimeException($location
                ? 'No office in the supplied location matches the imported office value.'
                : 'No matching office found for the supplied office value.');
        }

        return $this->importLookupCache['offices'][$cacheKey] = $offices->first();
    }

    private function validateImportedRow(array $row): void
    {
        $unknownColumns = array_values(array_diff(array_keys($row), $this->importAllowedColumns()));
        if ($unknownColumns) {
            throw new \RuntimeException('Unsupported column(s): ' . implode(', ', array_slice($unknownColumns, 0, 5)) . '.');
        }

        $propertyNumber = trim((string) $this->importValue($row, ['property_number']));
        $partOfPropertyNumber = trim((string) $this->importValue($row, ['part_of_property_number']));
        if ($propertyNumber === '' && $partOfPropertyNumber === '') {
            throw new \RuntimeException('property_number or part_of_property_number is required.');
        }

        $equipmentType = trim((string) $this->importValue($row, ['equipment_type']));
        if ($equipmentType === '') {
            throw new \RuntimeException('equipment_type is required.');
        }

        if (strlen($equipmentType) > 100) {
            throw new \RuntimeException('equipment_type must not exceed 100 characters.');
        }

        if ($partOfPropertyNumber !== ''
            && !in_array(strtolower($equipmentType), ['printer', 'monitor', 'avr', 'ups', 'scanner', 'other'], true)) {
            throw new \RuntimeException('part_of_property_number is only supported for Printer, Monitor, AVR, UPS, Scanner, or Other equipment.');
        }

        if ($propertyNumber !== ''
            && (strlen($propertyNumber) > 50 || ! preg_match(StoreDeviceRequest::PROPERTY_NUMBER_REGEX, $propertyNumber))) {
            throw new \RuntimeException('property_number may only contain letters, numbers, hyphens, and slashes, with a maximum of 50 characters.');
        }

        if ($partOfPropertyNumber !== ''
            && (strlen($partOfPropertyNumber) > 50
                || ! preg_match(StoreDeviceRequest::PROPERTY_NUMBER_REGEX, $partOfPropertyNumber))) {
            throw new \RuntimeException('part_of_property_number may only contain letters, numbers, hyphens, and slashes, with a maximum of 50 characters.');
        }

        if (filled($row['serial_number'] ?? null)
            && (strlen((string) $row['serial_number']) > 100
                || ! preg_match(StoreDeviceRequest::SERIAL_NUMBER_REGEX, (string) $row['serial_number']))) {
            throw new \RuntimeException('serial_number may only contain letters, numbers, and hyphens, with a maximum of 100 characters.');
        }

        if (filled($row['mac_address'] ?? null)
            && ! preg_match(StoreDeviceRequest::MAC_ADDRESS_REGEX, (string) $row['mac_address'])) {
            throw new \RuntimeException('mac_address must use the format 00:1A:2B:3C:4D:5E.');
        }

        foreach ([
            'computer_name' => 100,
            'brand' => 100,
            'model' => 100,
            'os_version' => 100,
            'os_license' => 100,
            'ms_office_version' => 100,
            'ms_office_license' => 100,
            'memory' => 255,
            'storage' => 255,
            'form_factor' => 255,
            'maintenance_remarks' => 1000,
            'notes' => 2000,
            'part_of_property_number' => 50,
            'staff_email' => 255,
            'staff_name' => 255,
            'first_name' => 100,
            'last_name' => 100,
            'office' => 255,
            'location' => 255,
            'location_code' => 100,
            'issuance_remarks' => 1000,
        ] as $field => $maxLength) {
            if (filled($row[$field] ?? null) && strlen((string) $row[$field]) > $maxLength) {
                throw new \RuntimeException("{$field} must not exceed {$maxLength} characters.");
            }
        }

        if (filled($row['staff_email'] ?? null)
            && ! filter_var((string) $row['staff_email'], FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('staff_email must be a valid email address.');
        }

        if (filled($row['unit_price'] ?? null)) {
            $unitPrice = str_replace(',', '', (string) $row['unit_price']);
            if (! is_numeric($unitPrice) || (float) $unitPrice < 0 || (float) $unitPrice > 9999999999.99) {
                throw new \RuntimeException('unit_price must be between 0 and 9,999,999,999.99.');
            }
        }

        foreach (['date_acquired', 'last_maintenance_date', 'issued_at'] as $dateField) {
            if (filled($row[$dateField] ?? null)) {
                $date = Carbon::parse($this->importDate($row[$dateField], $dateField));
                if ($date->isFuture()) {
                    throw new \RuntimeException("{$dateField} cannot be in the future.");
                }
            }
        }

        foreach ([
            'os_version' => ['Windows 7', 'Windows 8', 'Windows 10', 'Windows 11'],
            'os_license' => ['Cracked', 'OEM Licensed'],
            'ms_office_version' => ['Office 2007', 'Office 2010', 'Office 2013', 'Office 2016', 'Office 2019', 'Office 2021', 'Microsoft 365'],
            'ms_office_license' => ['Cracked', 'OEM Licensed'],
        ] as $field => $allowed) {
            if (filled($row[$field] ?? null) && ! in_array((string) $row[$field], $allowed, true)) {
                throw new \RuntimeException("{$field} contains an unsupported value.");
            }
        }
    }

    private function importAllowedColumns(): array
    {
        return [
            'property_number', 'equipment_type', 'serial_number', 'brand', 'model',
            'computer_name', 'mac_address', 'unit_price', 'date_acquired', 'condition',
            'part_of_property_number',
            'status', 'os_version', 'os_license', 'ms_office_version', 'ms_office_license',
            'memory', 'storage', 'form_factor', 'last_maintenance_date', 'maintenance_remarks',
            'notes', 'staff_email', 'staff_name', 'first_name', 'last_name', 'office',
            'location', 'location_code', 'issued_at', 'issuance_remarks',
        ];
    }

    private function normalizeImportRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[Str::snake(trim((string) $key))] = is_string($value) ? trim($value) : $value;
        }

        $aliases = [
            'property_no' => 'property_number', 'asset_number' => 'property_number', 'asset_no' => 'property_number',
            'parent_property_number' => 'part_of_property_number', 'parent_property_no' => 'part_of_property_number',
            'part_of_property_no' => 'part_of_property_number', 'part_of' => 'part_of_property_number',
            'equipment' => 'equipment_type', 'device' => 'equipment_type', 'device_type' => 'equipment_type',
            'type' => 'equipment_type', 'type_name' => 'equipment_type',
            'serial_no' => 'serial_number', 'office_name' => 'office', 'location_name' => 'location',
            'availability' => 'status',
            'user_email' => 'staff_email', 'end_user_email' => 'staff_email', 'issued_to_email' => 'staff_email',
            'issued_user_email' => 'staff_email',
            'assigned_to_email' => 'staff_email', 'email' => 'staff_email',
            'user_name' => 'staff_name', 'staff' => 'staff_name', 'assigned_to' => 'staff_name',
            'end_user' => 'staff_name', 'issued_user' => 'staff_name', 'issued_user_name' => 'staff_name',
            'staff_first_name' => 'first_name', 'staff_last_name' => 'last_name',
            'issued_to' => 'staff_name', 'issue_date' => 'issued_at', 'issue_remarks' => 'issuance_remarks',
            'remarks' => 'issuance_remarks',
        ];
        foreach ($aliases as $from => $to) {
            if (array_key_exists($from, $normalized)) {
                if (! array_key_exists($to, $normalized)) {
                    $normalized[$to] = $normalized[$from];
                }

                unset($normalized[$from]);
            }
        }

        return $normalized;
    }

    private function importValue(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && filled($row[$key])) {
                return $row[$key];
            }
        }

        return null;
    }

    private function importRowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (filled($value)) {
                return false;
            }
        }

        return true;
    }

    private function importStaffDetailsPresent(array $row): bool
    {
        return filled($this->importValue($row, [
            'staff_email', 'end_user_email', 'issued_to_email', 'assigned_to_email', 'email', 'user_email',
            'staff_name', 'issued_to', 'end_user', 'user_name', 'assigned_to',
            'first_name', 'last_name', 'staff_first_name', 'staff_last_name',
        ]));
    }

    private function importLocationDetailsPresent(array $row): bool
    {
        return filled($this->importValue($row, ['location_code', 'location', 'location_name', 'office', 'office_name']));
    }

    private function importTextMatches(?string $candidate, ?string $needle): bool
    {
        if (blank($candidate) || blank($needle)) {
            return false;
        }

        $candidateNormalized = $this->normalizeImportSearchText($candidate);
        $needleNormalized = $this->normalizeImportSearchText($needle);

        if ($candidateNormalized === '' || $needleNormalized === '') {
            return false;
        }

        if ($candidateNormalized === $needleNormalized) {
            return true;
        }

        if (str_contains($candidateNormalized, $needleNormalized) || str_contains($needleNormalized, $candidateNormalized)) {
            return true;
        }

        return $this->importAcronym($candidate) === $needleNormalized;
    }

    private function normalizeImportSearchText(?string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/i', ' ', strtolower((string) $value))));
    }

    private function importAcronym(?string $value): string
    {
        return collect(preg_split('/[^a-z0-9]+/i', strtolower((string) $value), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $word) => $word[0] ?? '')
            ->join('');
    }

    private function importDate(mixed $value, string $field): string
    {
        try {
            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value)->toDateString();
            }

            // PhpSpreadsheet may expose an Excel serial date for cells that
            // are not formatted as dates.
            if (is_numeric($value) && (float) $value > 20000) {
                return Carbon::create(1899, 12, 30)
                    ->addDays((int) $value)
                    ->toDateString();
            }

            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            throw new \RuntimeException("{$field} must be a valid date.");
        }
    }

    /**
     * Generate a unique internal record number when a linked peripheral does
     * not have its own property number. The export uses the parent number for
     * grouping, while the database still keeps every equipment row distinct.
     */
    private function generateLinkedPropertyNumber(string $parentPropertyNumber, ?int $deviceTypeId = null): string
    {
        $base = preg_replace('/[^A-Za-z0-9]+/', '-', strtoupper(trim($parentPropertyNumber))) ?: 'PARENT';
        $base = trim(substr($base, 0, 28), '-');
        $typeSegment = $deviceTypeId ? 'T' . $deviceTypeId . '-' : '';

        do {
            $candidate = $base . '-LINK-' . $typeSegment . strtoupper(Str::random(8));
        } while (Device::where('property_number', $candidate)->exists());

        return $candidate;
    }

    /**
     * Quick update endpoint used by popup edit on "Issued Devices" page.
     */
    public function quickUpdate(Request $request, Device $device)
    {
        $data = $request->validate([
            'device_type_id' => ['nullable', 'exists:device_types,id'],

            'property_number' => [
                'nullable',
                'string',
                'max:50',
                'regex:' . StoreDeviceRequest::PROPERTY_NUMBER_REGEX,
                'required_without:part_of_property_number',
                'unique:devices,property_number,' . $device->id,
            ],

            'part_of_property_number' => [
                'nullable',
                'string',
                'max:50',
                'regex:' . StoreDeviceRequest::PROPERTY_NUMBER_REGEX,
                'required_without:property_number',
                Rule::exists('devices', 'property_number')->where(function ($query) use ($device) {
                    $query
                        ->where('id', '!=', $device->id)
                        ->whereNull('part_of_property_number');
                }),
            ],

            'serial_number' => ['nullable', 'string', 'max:100', 'regex:' . StoreDeviceRequest::SERIAL_NUMBER_REGEX],

            'brand' => ['nullable', 'string', 'max:100', 'regex:' . StoreDeviceRequest::BRAND_MODEL_REGEX],
            'model' => ['nullable', 'string', 'max:100', 'regex:' . StoreDeviceRequest::BRAND_MODEL_REGEX],
            'mac_address' => ['nullable', 'string', 'regex:' . StoreDeviceRequest::MAC_ADDRESS_REGEX],
            'computer_name' => ['nullable', 'string', 'max:100'],

            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'date_acquired' => ['nullable', 'date', 'before_or_equal:today'],

            'condition' => ['nullable', 'in:serviceable,unserviceable,condemned'],
            'status' => ['nullable', 'in:available,issued,repair,retired'],

            'last_maintenance_date' => ['nullable', 'date', 'before_or_equal:today'],
            'maintenance_remarks' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'specs' => ['nullable', 'array'],
            'specs.os' => ['nullable', 'string', 'max:100'],
            'specs.memory' => ['nullable', 'string', 'max:50'],
            'specs.storage' => ['nullable', 'string', 'max:50'],
            'specs.form_factor' => ['nullable', 'string', 'max:50'],

            'os_version' => ['nullable', 'string', 'in:Windows 7,Windows 8,Windows 10,Windows 11'],
            'os_license' => ['nullable', 'string', 'in:Cracked,OEM Licensed'],
            'ms_office_version' => ['nullable', 'string', 'in:Office 2007,Office 2010,Office 2013,Office 2016,Office 2019,Office 2021,Microsoft 365'],
            'ms_office_license' => ['nullable', 'string', 'in:Cracked,OEM Licensed'],
        ], [
            'property_number.regex' => 'Property number may only contain letters, numbers, hyphens, and slashes.',
            'property_number.required_without' => 'Enter a property number, or select a parent property number for this linked equipment.',
            'part_of_property_number.exists' => 'The selected parent property number does not exist.',
            'part_of_property_number.required_without' => 'Select a parent property number when the equipment property number is blank.',
            'serial_number.regex' => 'Serial number may only contain letters, numbers, and hyphens.',
            'brand.regex' => 'Brand may only contain letters and numbers.',
            'model.regex' => 'Model may only contain letters and numbers.',
            'mac_address.regex' => 'Please enter a valid MAC address, e.g. 00:1A:2B:3C:4D:5E.',
            'date_acquired.before_or_equal' => 'Date acquired cannot be in the future.',
            'last_maintenance_date.before_or_equal' => 'Last maintenance date cannot be in the future.',
            'os_version.in' => 'Invalid OS version selected.',
            'os_license.in' => 'OS license must be either Cracked or OEM Licensed.',
            'ms_office_version.in' => 'Invalid MS Office version selected.',
            'ms_office_license.in' => 'MS Office license must be either Cracked or OEM Licensed.',
        ]);

        /*
        |--------------------------------------------------------------------------
        | If device_type_id is not submitted, use the current device type.
        |--------------------------------------------------------------------------
        */
        $data['device_type_id'] = $data['device_type_id'] ?? $device->device_type_id;
        $data['condition'] = $data['condition'] ?? $device->condition ?? 'serviceable';

        if (blank($data['property_number'] ?? null) && filled($data['part_of_property_number'] ?? null)) {
            $data['property_number'] = $device->property_number ?: $this->generateLinkedPropertyNumber(
                (string) $data['part_of_property_number'],
                (int) $data['device_type_id']
            );
        }

        if (!array_key_exists('status', $data)) {
            unset($data['status']);
        }

        $data = $this->cleanDeviceDataByType($data);

        $before = [
            'property_number' => $device->property_number,
            'device_type' => optional($device->type)->name,
            'computer_name' => $device->computer_name ?: data_get($device->specs, 'computer_name'),
            'brand' => $device->brand,
            'model' => $device->model,
            'serial_number' => $device->serial_number,
            'mac_address' => $device->mac_address,

            'windows_version' => $device->os_version,
            'windows_license' => $device->os_license,
            'ms_office_version' => $device->ms_office_version,
            'ms_office_license' => $device->ms_office_license,

            'memory' => data_get($device->specs, 'memory'),
            'storage' => data_get($device->specs, 'storage'),
            'form_factor' => data_get($device->specs, 'form_factor'),

            'unit_price' => $device->unit_price,

            'condition' => $device->condition,
            'status' => $device->status,
            'maintenance_remarks' => $device->maintenance_remarks,
            'notes' => $device->notes,
        ];

        $device->update($data);
        $device->load('type');

        $summary = [
            'property_number' => $device->property_number,
            'device_type' => optional($device->type)->name,
            'brand' => $device->brand,
        ];

        if ($this->isComputerDevice(optional($device->type)->name)) {
            $summary['computer_name'] =
                $device->computer_name ?: data_get($device->specs, 'computer_name');
        }

        foreach ([
            'model' => $device->model,
            'serial_number' => $device->serial_number,
            'mac_address' => $device->mac_address,
            'windows_version' => $device->os_version,
            'windows_license' => $device->os_license,
            'ms_office_version' => $device->ms_office_version,
            'ms_office_license' => $device->ms_office_license,
            'memory' => data_get($device->specs, 'memory'),
            'storage' => data_get($device->specs, 'storage'),
            'form_factor' => data_get($device->specs, 'form_factor'),
            'unit_price' => $device->unit_price,
            'condition' => $device->condition,
            'part_of_property_number' => $device->part_of_property_number,
            'status' => $device->status,
            'maintenance_remarks' => $device->maintenance_remarks,
            'notes' => $device->notes,
        ] as $key => $value) {

            if (filled($value)) {
                $summary[$key] = $value;
            }
        }

        ActivityLog::record(
            'updated',
            "Updated device \"{$device->property_number}\"",
            $device,
            ActivityLog::makePayload(
                $summary,
                ActivityLog::buildChanges(
                    $before,
                    [
                        'property_number' => $device->property_number,
                        'device_type' => optional($device->type)->name,
                        'computer_name' => $device->computer_name ?: data_get($device->specs, 'computer_name'),
                        'brand' => $device->brand,
                        'model' => $device->model,
                        'serial_number' => $device->serial_number,
                        'mac_address' => $device->mac_address,

                        'windows_version' => $device->os_version,
                        'windows_license' => $device->os_license,
                        'ms_office_version' => $device->ms_office_version,
                        'ms_office_license' => $device->ms_office_license,

                        'memory' => data_get($device->specs, 'memory'),
                        'storage' => data_get($device->specs, 'storage'),
                        'form_factor' => data_get($device->specs, 'form_factor'),

                        'unit_price' => $device->unit_price,

                        'condition' => $device->condition,
                        'status' => $device->status,
                        'maintenance_remarks' => $device->maintenance_remarks,
                        'notes' => $device->notes,
                    ]
                ) ?? []
            )
        );

        return back()->with('success', 'Equipment updated.');
    }

    /**
     * Mark the device as checked/maintained today.
     * This also creates a maintenance history record.
     */
    public function markChecked(Request $request, Device $device)
    {
        $data = $request->validate([
            'maintenance_date' => ['nullable', 'date', 'before_or_equal:today'],
            'maintenance_type' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'corrective_action' => ['nullable', 'string', 'max:1000'],
        ], [
            'maintenance_date.before_or_equal' => 'Maintenance date cannot be in the future.',
        ]);

        $maintenanceDate = $data['maintenance_date'] ?? now()->toDateString();
        $maintenanceType = $data['maintenance_type'] ?? 'Checked';
        $remarks = $data['remarks'] ?? 'Checked/Maintained today';

        $device->loadMissing(['type', 'currentAssignment.staff.office.location', 'currentAssignment.office.location', 'currentAssignment.location']);
        $assignment = $device->currentAssignment;
        $staff = $assignment?->staff;
        $office = $assignment?->office ?: $staff?->office;
        $location = $assignment?->location ?: $office?->location;

        DeviceMaintenanceRecord::create([
            'device_id' => $device->id,
            'staff_id' => $staff?->id,
            'office_id' => $office?->id,
            'location_id' => $location?->id,
            'maintenance_date' => $maintenanceDate,
            'maintenance_type' => $maintenanceType,
            'condition' => $device->condition ?? 'serviceable',
            'remarks' => $remarks,
            'corrective_action' => $data['corrective_action'] ?? null,
            'checklist_data' => [
                'snapshot' => [
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
            "Updated maintenance for device \"{$device->property_number}\"",
            $device,
            ActivityLog::makePayload([
                'property_number' => $device->property_number,
                'device_type' => optional($device->type)->name,
                'maintenance_date' => $maintenanceDate,
                'maintenance_type' => $maintenanceType,
                'maintenance_remarks' => $remarks,
            ])
        );

        return redirect()
            ->route('admin.devices.show', $device->id)
            ->with('success', 'Equipment has been marked as checked.');
    }

    public function maintenanceHistory(Device $device)
    {
        $device->load([
            'type',
            'maintenanceRecords.checkedBy',
        ]);

        $records = $device->maintenanceRecords()
            ->with('checkedBy')
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->get();

        $assignments = $device->assignments()
            ->with(['staff.office.location', 'office.location', 'location', 'issuer'])
            ->orderByDesc('issued_at')
            ->get();

        $activityLogs = ActivityLog::query()
            ->where('subject_type', 'Device')
            ->where('subject_id', $device->id)
            ->whereIn('action', ['relocated', 'reissued', 'updated'])
            ->latest()
            ->get();

        return view('admin.devices.maintenance-history', compact('device', 'records', 'assignments', 'activityLogs'));
    }

    public function generateQr()
    {
        $devices = Device::orderBy('property_number')->get();

        $qrCodes = $devices->mapWithKeys(function ($device) {
            $qrPayload = route('admin.devices.show', $device) . '?property_number=' . urlencode($device->property_number);

            return [
                $device->id => QrCode::size(180)->generate($qrPayload),
            ];
        });

        return view('admin.devices.generate-qr', compact('devices', 'qrCodes'));
    }

    public function exportPreventiveMaintenanceReport(Request $request)
    {
        $status = $request->query('status');
        $condition = $request->query('condition');

        if (!in_array($status, ['available', 'issued', 'repair', 'retired'], true)) {
            $status = null;
        }

        if (!in_array($condition, ['serviceable', 'unserviceable', 'condemned'], true)) {
            $condition = null;
        }

        $filters = [
            'q' => $request->string('q')->toString(),
            'type_id' => $request->integer('type'),
            'location_id' => $request->integer('location') ?: $request->integer('college'),
            'office_id' => $request->integer('office_id'),
            'status' => $status,
            'condition' => $condition,
        ];

        $filename = 'equipment-inventory-filtered-' . now()->format('Y-m-d-His') . '.xlsx';

        return Excel::download(new EquipmentInventoryExport($filters), $filename);
    }

    public function exportOfficePreventiveMaintenanceReport(Office $office)
    {
        $safeOfficeName = str($office->name)
            ->lower()
            ->replace(' ', '-')
            ->replace('/', '-');

        $filename = 'preventive-maintenance-report-' . $safeOfficeName . '-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new PreventiveMaintenanceReportExport($office), $filename);
    }

    /**
     * Remove computer-only fields when the device is not Desktop or Laptop.
     */
    private function storeEquipmentPhoto(Request $request): ?string
    {
        if (! $request->hasFile('equipment_photo')) {
            return null;
        }

        $file = $request->file('equipment_photo');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $filename = Str::uuid() . '.' . $extension;

        return $file->storeAs('equipment-photos', $filename, 'public');
    }

    private function deleteEquipmentPhoto(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function cleanDeviceDataByType(array $data): array
    {
        $type = DeviceType::find($data['device_type_id'] ?? null);
        $typeName = strtolower($type?->name ?? '');

        $isComputerType = in_array($typeName, ['desktop', 'laptop']);
        $isPartPropertyType = in_array($typeName, ['printer', 'monitor', 'avr', 'ups', 'scanner', 'other'], true);

        if (!$isPartPropertyType) {
            $data['part_of_property_number'] = null;
        }

        if (!$isComputerType) {
            // Computer names are meaningful only for Desktop/Laptop records.
            // Clear the value on create/update/import so changing an item type
            // cannot leave stale computer metadata behind.
            $data['computer_name'] = null;
            $data['mac_address'] = null;
            $data['os_version'] = null;
            $data['os_license'] = null;
            $data['ms_office_version'] = null;
            $data['ms_office_license'] = null;

            $data['specs'] = collect($data['specs'] ?? [])
                ->except([
                    'os',
                    'memory',
                    'storage',
                    'form_factor',
                    'computer_name',
                ])
                ->toArray();

            if (empty($data['specs'])) {
                $data['specs'] = null;
            }
        }

        if ($isComputerType) {

    /*
    |--------------------------------------------------------------------------
    | Laptops do not use Form Factor.
    |--------------------------------------------------------------------------
    */
    if ($typeName === 'laptop') {
        unset($data['specs']['form_factor']);
    }

    $data['specs'] = collect($data['specs'] ?? [])
        ->filter(fn($value) => filled($value))
        ->toArray();

    if (empty($data['specs'])) {
        $data['specs'] = null;
    }
}

        return $data;
    }

    /**
     * Only show these device types in the Add/Edit dropdown.
     * This does not delete old device types from the database.
     */

    private function isComputerDevice(?string $deviceType): bool
    {
        return in_array(
            strtolower((string) $deviceType),
            ['desktop', 'laptop'],
            true
        );
    }

    private function staffLookupResult(Staff $staff): array
    {
        $name = trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? ''));
        $office = $staff->office?->name;
        $location = $staff->office?->location?->code ?: $staff->office?->location?->name;

        return [
            'id' => $staff->id,
            'name' => $name,
            'position' => $staff->position,
            'email' => $staff->email,
            'office' => $office,
            'location' => $location,
            'label' => collect([$name, $staff->position, $office, $location])
                ->filter()
                ->join(' - '),
        ];
    }

    private function assignmentContext(?DeviceAssignment $assignment): array
    {
        $staff = $assignment?->staff;
        $office = $assignment?->office ?: $staff?->office;
        $location = $assignment?->location ?: $office?->location;

        return [
            'staff_name' => $staff ? trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? '')) : null,
            'office_name' => $office?->name,
            'location_name' => $location
                ? (($location->code ? $location->code . ' - ' : '') . $location->name)
                : null,
        ];
    }

    private function searchTokens(string $value): array
    {
        return collect(preg_split('/\s+/', strtolower(trim($value)), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $token) => trim($token))
            ->filter(fn (string $token) => $token !== '')
            ->take(5)
            ->values()
            ->all();
    }

    private function allowedDeviceTypes()
    {
        $allowedTypes = [
            'Desktop',
            'Laptop',
            'Printer',
            'Monitor',
            'UPS',
            'AVR',
            'Scanner',
            'Other',
        ];

        foreach ($allowedTypes as $typeName) {
            DeviceType::firstOrCreate(
                ['name' => $typeName],
                ['slug' => strtolower(str_replace(' ', '-', $typeName))]
            );
        }

        return DeviceType::whereIn('name', $allowedTypes)
            ->get()
            ->sortBy(function ($type) use ($allowedTypes) {
                return array_search($type->name, $allowedTypes);
            })
            ->values();
    }
}
