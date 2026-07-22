<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\DeviceMaintenancePhoto;
use App\Models\DeviceMaintenanceRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MaintenancePhotoController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $search = trim($request->string('q')->toString());

        $photos = DeviceMaintenancePhoto::query()
            ->with(['device.type', 'maintenanceRecord', 'uploadedBy'])
            ->when($dateFrom, fn ($query) => $query->whereDate('captured_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('captured_at', '<=', $dateTo))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . $search . '%';
                $query->where(function ($photoQuery) use ($like) {
                    $photoQuery->where('caption', 'like', $like)
                        ->orWhereHas('device', function ($deviceQuery) use ($like) {
                            $deviceQuery->where('property_number', 'like', $like)
                                ->orWhere('part_of_property_number', 'like', $like)
                                ->orWhere('serial_number', 'like', $like)
                                ->orWhereHas('type', fn ($typeQuery) => $typeQuery->where('name', 'like', $like));
                        });
                });
            })
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->paginate(24)
            ->withQueryString();

        $devices = Device::query()
            ->with('type:id,name')
            ->orderBy('property_number')
            ->get(['id', 'device_type_id', 'property_number', 'serial_number']);

        $maintenanceRecords = DeviceMaintenanceRecord::query()
            ->with('device:id,property_number')
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->limit(500)
            ->get(['id', 'device_id', 'maintenance_date', 'maintenance_type']);

        $slides = $photos->getCollection()->map(fn (DeviceMaintenancePhoto $photo) => [
            'url' => Storage::disk('public')->url($photo->photo_path),
            'caption' => $photo->caption ?: 'Preventive maintenance photo',
            'property_number' => $photo->device?->property_number,
            'captured_at' => $photo->captured_at?->format('M j, Y g:i A'),
        ])->values();

        return view('admin.maintenance-gallery.index', compact(
            'photos',
            'devices',
            'maintenanceRecords',
            'dateFrom',
            'dateTo',
            'search',
            'slides',
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'integer', 'exists:devices,id'],
            'maintenance_record_id' => ['nullable', 'integer', 'exists:device_maintenance_records,id'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'captured_at' => ['nullable', 'date'],
            'caption' => ['nullable', 'string', 'max:255'],
        ], [
            'photo.max' => 'Photo must not be larger than 10 MB.',
            'photo.mimes' => 'Upload a JPG, PNG, or WebP image.',
        ]);

        $record = null;
        if (! empty($data['maintenance_record_id'])) {
            $record = DeviceMaintenanceRecord::query()->findOrFail($data['maintenance_record_id']);
            abort_unless((int) $record->device_id === (int) $data['device_id'], 422, 'The selected maintenance record does not belong to this equipment.');
        }

        $path = $request->file('photo')->store('maintenance-photos', 'public');
        $capturedAt = ! empty($data['captured_at'])
            ? Carbon::parse($data['captured_at'])
            : now();

        $photo = DeviceMaintenancePhoto::create([
            'device_id' => $data['device_id'],
            'maintenance_record_id' => $record?->id,
            'uploaded_by' => auth()->id(),
            'photo_path' => $path,
            'captured_at' => $capturedAt,
            'caption' => filled($data['caption'] ?? null) ? trim($data['caption']) : null,
        ]);

        $device = Device::find($photo->device_id);
        ActivityLog::record('created', 'Added preventive maintenance photo', $device, ActivityLog::makePayload([
            'photo_id' => $photo->id,
            'maintenance_record_id' => $photo->maintenance_record_id,
            'captured_at' => $photo->captured_at?->toDateTimeString(),
            'caption' => $photo->caption,
        ]));

        return back()->with('success', 'Preventive maintenance photo added to the gallery.');
    }

    public function destroy(DeviceMaintenancePhoto $photo)
    {
        $photo->load('device');
        Storage::disk('public')->delete($photo->photo_path);

        ActivityLog::record('deleted', 'Deleted preventive maintenance photo', $photo->device, ActivityLog::makePayload([
            'photo_id' => $photo->id,
            'captured_at' => $photo->captured_at?->toDateTimeString(),
            'caption' => $photo->caption,
        ]));

        $photo->delete();

        return back()->with('success', 'Preventive maintenance photo deleted.');
    }
}
