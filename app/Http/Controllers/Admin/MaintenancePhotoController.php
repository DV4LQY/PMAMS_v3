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
use Illuminate\Support\Str;
use ZipArchive;

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
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
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
            if (empty($data['device_id'])) {
                $data['device_id'] = $record->device_id;
            } else {
                abort_unless((int) $record->device_id === (int) $data['device_id'], 422, 'The selected maintenance record does not belong to this equipment.');
            }
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

        $device = $photo->device_id ? Device::find($photo->device_id) : null;
        ActivityLog::record('created', 'Added preventive maintenance photo', $device, ActivityLog::makePayload([
            'photo_id' => $photo->id,
            'maintenance_record_id' => $photo->maintenance_record_id,
            'captured_at' => $photo->captured_at?->toDateTimeString(),
            'caption' => $photo->caption,
        ]));

        return back()->with('success', 'Preventive maintenance photo added to the gallery.');
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'photo_ids' => ['required', 'array', 'min:1'],
            'photo_ids.*' => ['integer', 'distinct', 'exists:device_maintenance_photos,id'],
        ]);

        $photos = DeviceMaintenancePhoto::query()
            ->whereIn('id', $data['photo_ids'])
            ->get(['id', 'device_id', 'uploaded_by', 'photo_path', 'captured_at', 'caption']);

        $ownedPhotos = $photos->filter(fn (DeviceMaintenancePhoto $photo) => (int) $photo->uploaded_by === (int) auth()->id());
        $skippedCount = $photos->count() - $ownedPhotos->count();

        foreach ($ownedPhotos as $photo) {
            Storage::disk('public')->delete($photo->photo_path);
            ActivityLog::record('deleted', 'Bulk deleted preventive maintenance photo', null, ActivityLog::makePayload([
                'photo_id' => $photo->id,
                'device_id' => $photo->device_id,
                'captured_at' => $photo->captured_at?->toDateTimeString(),
                'caption' => $photo->caption,
            ]));
        }

        DeviceMaintenancePhoto::query()->whereKey($ownedPhotos->modelKeys())->delete();

        $message = $ownedPhotos->count() . ' maintenance photo(s) deleted.';
        if ($skippedCount > 0) {
            $message .= ' ' . $skippedCount . ' photo(s) were not deleted because they were uploaded by another account.';
        }

        return back()->with('success', $message);
    }

    public function bulkDownload(Request $request)
    {
        $data = $request->validate([
            'photo_ids' => ['required', 'array', 'min:1'],
            'photo_ids.*' => ['integer', 'distinct', 'exists:device_maintenance_photos,id'],
        ]);

        $photos = DeviceMaintenancePhoto::query()
            ->with('device:id,property_number')
            ->whereIn('id', $data['photo_ids'])
            ->get();

        if ($photos->isEmpty()) {
            return back()->withErrors(['photo_ids' => 'Select at least one photo to download.']);
        }

        if (! class_exists(ZipArchive::class)) {
            return back()->withErrors(['photo_ids' => 'ZIP downloads are not available because the PHP ZIP extension is not enabled.']);
        }

        $directory = storage_path('app/temp');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = 'maintenance-gallery-' . now()->format('Ymd-His') . '.zip';
        $zipPath = $directory . DIRECTORY_SEPARATOR . $filename;
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->withErrors(['photo_ids' => 'The ZIP file could not be created.']);
        }

        $added = 0;
        foreach ($photos as $photo) {
            $absolutePath = Storage::disk('public')->path($photo->photo_path);
            if (! is_file($absolutePath)) {
                continue;
            }

            $property = $photo->device?->property_number ?: 'unlinked';
            $extension = pathinfo($absolutePath, PATHINFO_EXTENSION) ?: 'jpg';
            $entryName = sprintf(
                '%d-%s.%s',
                $photo->id,
                Str::slug($property) ?: 'maintenance-photo',
                strtolower($extension),
            );
            $zip->addFile($absolutePath, $entryName);
            $added++;
        }

        $zip->close();

        if ($added === 0) {
            @unlink($zipPath);
            return back()->withErrors(['photo_ids' => 'None of the selected photos are available on storage.']);
        }

        return response()->download($zipPath, $filename)->deleteFileAfterSend(true);
    }

    public function destroy(DeviceMaintenancePhoto $photo)
    {
        abort_unless((int) $photo->uploaded_by === (int) auth()->id(), 403, 'You can only delete photos uploaded by your account.');

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
