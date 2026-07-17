<?php

namespace App\Http\Controllers\Admin;

use App\Exports\EquipmentInventoryExport;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->string('q')->toString();
        $typeId = $request->integer('type');
        $locationId = $request->integer('location') ?: $request->integer('college');
        $collegeId = $locationId; // backward-compatible variable for existing views
        $status = $request->query('status');
        $condition = $request->query('condition');

        if (!in_array($status, ['available', 'issued', 'repair', 'retired'], true)) {
            $status = null;
        }

        if (!in_array($condition, ['serviceable', 'unserviceable', 'condemned'], true)) {
            $condition = null;
        }

        $devices = Device::query()
            ->with([
                'type',
                'currentAssignment.staff.office.location',
                'currentAssignment.location',
                'latestMaintenanceRecord',
            ])
            ->filterInventory([
                'q' => $q,
                'type_id' => $typeId,
                'location_id' => $locationId,
                'status' => $status,
                'condition' => $condition,
            ])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $types = $this->allowedDeviceTypes();

        // Only locations with a code are usable in this filter dropdown —
        // a location with no code would render as a blank, unselectable-
        // looking option, so it's excluded here rather than in the view.
        $locations = Location::whereNotNull('code')
            ->where('code', '!=', '')
            ->orderBy('name')
            ->get();

        $colleges = $locations; // backward-compatible variable for existing device views

        return view('admin.devices.index', compact(
            'devices',
            'q',
            'typeId',
            'locationId',
            'collegeId',
            'status',
            'condition',
            'types',
            'locations',
            'colleges'
        ));
    }

    public function staffLookup(Request $request)
    {
        $tokens = $this->searchTokens($request->string('q')->toString());

        $staff = Staff::query()
            ->select(['id', 'office_id', 'first_name', 'last_name', 'position', 'email', 'phone', 'is_active'])
            ->with([
                'office:id,location_id,name',
                'office.location:id,name,code',
            ])
            ->where('is_active', true)
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
            'currentAssignment.location',
            'latestMaintenanceRecord',
        ]);

        $types = $this->allowedDeviceTypes();
        $locations = Location::orderBy('name')->get();

        return view('admin.devices.show', compact('device', 'types', 'locations'));
    }

    public function relocate(Request $request, Device $device)
    {
        $data = $request->validate([
            'location_id' => ['required', 'exists:locations,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $location = Location::findOrFail($data['location_id']);
        $locationLabel = $location->code
            ? "{$location->code} - {$location->name}"
            : $location->name;

        $assignment = $device->currentAssignment()
            ->with(['staff.office.location', 'location'])
            ->first();
        $from = $this->assignmentContext($assignment);

        if ($assignment) {
            $assignment->update([
                'returned_at' => now(),
                'remarks' => trim(($assignment->remarks ? $assignment->remarks . ' ' : '') . 'Relocated to ' . $locationLabel . ' on ' . now()->format('M d, Y h:i A') . '.'),
            ]);
        }

        $relocationRemarks = trim($data['remarks'] ?? '') ?: "Equipment relocated to {$locationLabel}.";

        DeviceAssignment::create([
            'device_id' => $device->id,
            'staff_id' => null,
            'location_id' => $location->id,
            'issued_by' => Auth::id(),
            'issued_at' => now(),
            'remarks' => $relocationRemarks,
        ]);

        $device->update(['status' => 'issued']);

        ActivityLog::record(
            'relocated',
            "Relocated equipment \"{$device->property_number}\"",
            $device,
            ActivityLog::makePayload([
                'property_number' => $device->property_number,
                'from_end_user' => $from['staff_name'],
                'from_office' => $from['office_name'],
                'from_location' => $from['location_name'],
                'to_end_user' => null,
                'to_office' => null,
                'to_location' => $locationLabel,
                'remarks' => $relocationRemarks,
                'relocated_by' => Auth::user()?->name,
                'relocated_at' => now()->format('M d, Y h:i A'),
            ])
        );

        return back()->with('success', 'Equipment relocated successfully.');
    }

    public function reissue(Request $request, Device $device)
    {
        $data = $request->validate([
            'staff_id' => ['required', 'exists:staff,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], [
            'staff_id.required' => 'Please select a registered end user.',
            'staff_id.exists' => 'The selected end user could not be found.',
        ]);

        $staff = Staff::query()
            ->with('office.location')
            ->findOrFail($data['staff_id']);

        $assignment = $device->currentAssignment()
            ->with(['staff.office.location', 'location'])
            ->first();
        $from = $this->assignmentContext($assignment);

        if ($assignment && (int) $assignment->staff_id === (int) $staff->id) {
            return back()->withErrors(['staff_id' => 'This equipment is already assigned to the selected end user.'])->withInput();
        }

        $reissueRemarks = trim($data['remarks'] ?? '') ?: 'Equipment reissued to registered end user.';

        if ($assignment) {
            $assignment->update([
                'returned_at' => now(),
                'remarks' => trim(($assignment->remarks ? $assignment->remarks . ' ' : '') . 'Reissued on ' . now()->format('M d, Y h:i A') . '.'),
            ]);
        }

        DeviceAssignment::create([
            'device_id' => $device->id,
            'staff_id' => $staff->id,
            'location_id' => null,
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
                'to_location' => $staff->office?->location?->name,
                'remarks' => $reissueRemarks,
                'reissued_by' => Auth::user()?->name,
                'reissued_at' => now()->format('M d, Y h:i A'),
            ])
        );

        return back()->with('success', 'Equipment reissued successfully.');
    }

    public function issue(Request $request, Device $device)
    {
        $data = $request->validate([
            'staff_id' => ['required', 'exists:staff,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], [
            'staff_id.required' => 'Please select a staff member to issue this equipment to.',
            'staff_id.exists' => 'The selected staff member could not be found.',
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

        $data = $request->validate([
            'device_ids' => ['required', 'array', 'min:1'],
            'device_ids.*' => ['integer', 'distinct', 'exists:devices,id'],
        ]);

        $devices = Device::with('type')
            ->whereIn('id', $data['device_ids'])
            ->get();

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

        DB::transaction(function () use ($devices, $data, $items) {
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
        ]);

        try {
            $rows = Excel::toCollection(
                new DeviceInventoryImport,
                $request->file('file')
            )->first() ?? collect();
        } catch (\Throwable $exception) {
            return back()->withErrors([
                'file' => 'The file could not be read. Please upload a valid CSV or Excel workbook.',
            ]);
        }

        if ($rows->isEmpty()) {
            return back()->withErrors(['file' => 'The import file has no data rows.']);
        }

        $created = 0;
        $updated = 0;
        $issued = 0;
        $rowErrors = [];

        DB::beginTransaction();

        try {
            foreach ($rows as $index => $rawRow) {
                $row = $this->normalizeImportRow($rawRow instanceof \Illuminate\Support\Collection
                    ? $rawRow->toArray()
                    : (array) $rawRow);

                if ($this->importRowIsEmpty($row)) {
                    continue;
                }

                $rowNumber = $index + 2; // account for the heading row

                try {
                    [$wasCreated, $wasIssued] = $this->persistImportedInventoryRow($row);
                    $wasCreated ? $created++ : $updated++;
                    if ($wasIssued) {
                        $issued++;
                    }
                } catch (\Throwable $exception) {
                    $rowErrors[] = "Row {$rowNumber}: {$exception->getMessage()}";
                }
            }

            if ($rowErrors) {
                DB::rollBack();
                throw ValidationException::withMessages(['file' => $rowErrors]);
            }

            DB::commit();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw ValidationException::withMessages([
                'file' => 'Import failed: ' . $exception->getMessage(),
            ]);
        }

        $message = "{$created} equipment added, {$updated} equipment updated"
            . ($issued ? ", {$issued} issuance record(s) created." : '.');

        return back()->with('success', $message);
    }

    public function importTemplate()
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'property_number', 'equipment_type', 'serial_number', 'brand', 'model',
                'computer_name', 'mac_address', 'unit_price', 'date_acquired', 'condition',
                'status', 'os_version', 'os_license', 'ms_office_version', 'ms_office_license',
                'memory', 'storage', 'form_factor', 'last_maintenance_date', 'maintenance_remarks', 'notes',
                'staff_email', 'staff_name', 'first_name', 'last_name', 'office',
                'location_code', 'issued_at', 'issuance_remarks',
            ]);
            fputcsv($handle, [
                'PN-2026-0001', 'Laptop', 'SN-0001', 'Example', 'Model', 'PC-001', '',
                '25000', '2026-01-15', 'serviceable', 'issued', 'Windows 11',
                'OEM Licensed', 'Microsoft 365', 'OEM Licensed', '16 GB', '512 GB SSD',
                '', '', '', '', 'juan@example.edu', 'Juan Dela Cruz', '', '', 'ICTU',
                'MAIN', '2026-01-15', 'Initial issuance',
            ]);
            fclose($handle);
        }, 'equipment-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function persistImportedInventoryRow(array $row): array
    {
        $propertyNumber = $this->importValue($row, ['property_number', 'property_no', 'asset_number', 'asset_no']);
        if (blank($propertyNumber)) {
            throw new \RuntimeException('property_number is required.');
        }

        $typeName = $this->importValue($row, ['equipment_type', 'device_type', 'type']);
        if (blank($typeName)) {
            throw new \RuntimeException('equipment_type is required.');
        }

        $typeName = trim((string) $typeName);
        $type = DeviceType::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($typeName)])
            ->first();
        $type ??= DeviceType::create([
            'name' => $typeName,
            'slug' => Str::slug($typeName),
        ]);

        $device = Device::firstOrNew(['property_number' => trim((string) $propertyNumber)]);
        $wasCreated = !$device->exists;
        $device->device_type_id = $type->id;

        foreach ([
            'serial_number', 'computer_name', 'brand', 'model', 'mac_address',
            'os_version', 'os_license', 'ms_office_version', 'ms_office_license',
            'maintenance_remarks', 'notes',
        ] as $field) {
            if (array_key_exists($field, $row)) {
                $device->{$field} = blank($row[$field]) ? null : $row[$field];
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
        } elseif ($wasCreated) {
            $device->status = 'available';
        }

        $specs = is_array($device->specs) ? $device->specs : [];
        foreach (['memory', 'storage', 'form_factor'] as $specField) {
            if (array_key_exists($specField, $row)) {
                if (blank($row[$specField])) {
                    unset($specs[$specField]);
                } else {
                    $specs[$specField] = $row[$specField];
                }
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
        $currentAssignment = $device->currentAssignment()->with(['staff.office.location', 'location'])->first();

        if ($currentAssignment) {
            if ((int) $currentAssignment->staff_id === (int) $staff->id) {
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
            'location_id' => null,
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
        $currentAssignment = $device->currentAssignment()->with(['staff.office.location', 'location'])->first();

        if ($currentAssignment) {
            if (!$currentAssignment->staff_id && (int) $currentAssignment->location_id === (int) $location->id) {
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

        if (filled($email)) {
            $staff = Staff::query()
                ->with('office.location')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();

            if ($staff) {
                return $staff;
            }

            if (blank($firstName) && blank($lastName)) {
                throw new \RuntimeException('No matching staff found for the supplied email.');
            }
        }

        $query = Staff::query()->with('office.location');

        if (filled($firstName) && filled($lastName)) {
            $firstNameLower = strtolower($firstName);
            $lastNameLower = strtolower($lastName);

            $query->where(function ($staff) use ($firstNameLower, $lastNameLower) {
                $staff->where(function ($exact) use ($firstNameLower, $lastNameLower) {
                    $exact->whereRaw('LOWER(first_name) = ?', [$firstNameLower])
                        ->whereRaw('LOWER(last_name) = ?', [$lastNameLower]);
                })->orWhere(function ($reversed) use ($firstNameLower, $lastNameLower) {
                    $reversed->whereRaw('LOWER(first_name) = ?', [$lastNameLower])
                        ->whereRaw('LOWER(last_name) = ?', [$firstNameLower]);
                });
            });
        } else {
            if (filled($firstName)) {
                $query->whereRaw('LOWER(first_name) = ?', [strtolower($firstName)]);
            }
            if (filled($lastName)) {
                $query->whereRaw('LOWER(last_name) = ?', [strtolower($lastName)]);
            }
        }

        $candidates = $query->limit(25)->get();

        if ($candidates->isEmpty()) {
            throw new \RuntimeException('No matching staff found for the supplied name/email.');
        }

        $scoped = $candidates->filter(function (Staff $staff) use ($officeName, $locationName) {
            $officeMatches = blank($officeName) || $this->importTextMatches($staff->office?->name, $officeName);
            $locationMatches = blank($locationName)
                || $this->importTextMatches($staff->office?->location?->code, $locationName)
                || $this->importTextMatches($staff->office?->location?->name, $locationName);

            return $officeMatches && $locationMatches;
        });

        if ($scoped->isNotEmpty()) {
            return $scoped->first();
        }

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        throw new \RuntimeException('Multiple matching staff records were found. Add staff_email, office, or location_code.');
    }

    private function resolveImportedLocation(array $row): Location
    {
        $locationName = trim((string) $this->importValue($row, ['location_code', 'location', 'location_name']));
        $officeName = trim((string) $this->importValue($row, ['office', 'office_name']));

        if (filled($locationName)) {
            $location = Location::query()
                ->whereRaw('LOWER(code) = ?', [strtolower($locationName)])
                ->orWhereRaw('LOWER(name) = ?', [strtolower($locationName)])
                ->first();

            if ($location) {
                return $location;
            }

            $location = Location::query()
                ->get()
                ->first(fn (Location $location) => $this->importTextMatches($location->code, $locationName)
                    || $this->importTextMatches($location->name, $locationName));

            if ($location) {
                return $location;
            }
        }

        if (filled($officeName)) {
            $office = Office::query()
                ->with('location')
                ->get()
                ->first(fn (Office $office) => $this->importTextMatches($office->name, $officeName));

            if ($office?->location) {
                return $office->location;
            }
        }

        throw new \RuntimeException('No matching location found for the supplied location_code/location.');
    }

    private function normalizeImportRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[Str::snake(trim((string) $key))] = is_string($value) ? trim($value) : $value;
        }

        $aliases = [
            'property_no' => 'property_number', 'asset_number' => 'property_number', 'asset_no' => 'property_number',
            'equipment' => 'equipment_type', 'device' => 'equipment_type', 'type_name' => 'equipment_type',
            'serial_no' => 'serial_number', 'office_name' => 'office', 'location_name' => 'location',
            'availability' => 'status',
            'user_email' => 'staff_email', 'end_user_email' => 'staff_email', 'issued_to_email' => 'staff_email',
            'assigned_to_email' => 'staff_email',
            'user_name' => 'staff_name', 'staff' => 'staff_name', 'assigned_to' => 'staff_name',
            'issued_to' => 'staff_name', 'issue_date' => 'issued_at', 'issue_remarks' => 'issuance_remarks',
        ];
        foreach ($aliases as $from => $to) {
            if (array_key_exists($from, $normalized) && !array_key_exists($to, $normalized)) {
                $normalized[$to] = $normalized[$from];
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
     * Quick update endpoint used by popup edit on "Issued Devices" page.
     */
    public function quickUpdate(Request $request, Device $device)
    {
        $data = $request->validate([
            'device_type_id' => ['nullable', 'exists:device_types,id'],

            'property_number' => [
                'required',
                'string',
                'max:50',
                'regex:' . StoreDeviceRequest::PROPERTY_NUMBER_REGEX,
                'unique:devices,property_number,' . $device->id,
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

        $device->loadMissing(['type', 'currentAssignment.staff.office.location', 'currentAssignment.location']);
        $staff = $device->currentAssignment?->staff;
        $office = $staff?->office;
        $location = $device->currentAssignment?->location ?? $office?->location;

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
            ->with(['staff.office.location', 'location', 'issuer'])
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

        if (!$isComputerType) {
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
        $office = $staff?->office;
        $location = $assignment?->location ?? $office?->location;

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
