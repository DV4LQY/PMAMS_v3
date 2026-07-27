<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DeviceMaintenancePhoto;
use App\Models\DeviceMaintenanceRecord;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MaintenanceCleanupController extends Controller
{
    private const WINDOW_KEY = 'maintenance_checklist_duplicate_window_months';

    public function index(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $records = DeviceMaintenanceRecord::query()
            ->whereHas('device')
            ->with(['device.type', 'checkedBy'])
            ->when($dateFrom, fn ($q) => $q->whereDate('maintenance_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('maintenance_date', '<=', $dateTo))
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.maintenance-cleanup.index', [
            'records' => $records,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'windowMonths' => (int) SystemSetting::getValue(self::WINDOW_KEY, 3),
        ]);
    }

    public function updateWindow(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'window_months' => ['required', 'integer', 'min:1', 'max:36'],
        ]);

        SystemSetting::putValue(self::WINDOW_KEY, $data['window_months']);

        return back()->with('success', 'Checklist duplicate window updated to ' . $data['window_months'] . ' month(s).');
    }

    public function destroy(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'record_ids' => ['nullable', 'array'],
            'record_ids.*' => ['integer', 'distinct', 'exists:device_maintenance_records,id'],
            'select_all' => ['nullable', 'boolean'],
            'filter_checker_id' => ['nullable', 'integer', 'exists:users,id'],
            'filter_type_id' => ['nullable', 'integer', 'exists:device_types,id'],
            'filter_location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'filter_q' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        if (trim($data['remarks']) === '') {
            return back()->withErrors(['remarks' => 'Remarks are required before deletion.']);
        }

        $ids = collect($data['record_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
        $selectAll = (bool) ($data['select_all'] ?? false)
            || ($ids->isEmpty() && (filled($data['date_from'] ?? null) || filled($data['date_to'] ?? null)));
        if (! $selectAll && $ids->isEmpty()) {
            return back()->withErrors(['record_ids' => 'Select checklist history rows or choose select all matching records.']);
        }

        $query = DeviceMaintenanceRecord::query()->whereNotNull('checked_by');
        if (! $selectAll) {
            $query->whereIn('id', $ids);
        } else {
            $query
                ->when($data['filter_checker_id'] ?? null, fn ($q, $value) => $q->where('checked_by', $value))
                ->when($data['filter_type_id'] ?? null, fn ($q, $value) => $q->whereHas('device', fn ($device) => $device->where('device_type_id', $value)))
                ->when($data['filter_location_id'] ?? null, function ($q, $value) {
                    $q->where(function ($locationQuery) use ($value) {
                        $locationQuery->where('location_id', $value)
                            ->orWhere(function ($legacyQuery) use ($value) {
                                $legacyQuery->whereNull('location_id')
                                    ->whereHas('device.currentAssignment', function ($assignment) use ($value) {
                                        $assignment->where('location_id', $value)
                                            ->orWhereHas('staff.office', fn ($office) => $office->where('location_id', $value));
                                    });
                            });
                    });
                })
                ->when($data['filter_q'] ?? null, function ($q, $value) {
                    $q->where(function ($searchQuery) use ($value) {
                        $like = '%' . $value . '%';
                        $searchQuery->where('remarks', 'like', $like)
                            ->orWhere('corrective_action', 'like', $like)
                            ->orWhereHas('device', function ($device) use ($like) {
                                $device->where('property_number', 'like', $like)
                                    ->orWhere('serial_number', 'like', $like)
                                    ->orWhere('brand', 'like', $like)
                                    ->orWhere('model', 'like', $like);
                            });
                    });
                })
                ->when($data['date_from'] ?? null, fn ($q, $date) => $q->whereDate('maintenance_date', '>=', $date))
                ->when($data['date_to'] ?? null, fn ($q, $date) => $q->whereDate('maintenance_date', '<=', $date));
        }

        $records = $query->with(['device.type', 'checkedBy'])->get();
        if ($records->isEmpty()) {
            return back()->withErrors(['record_ids' => 'No checklist history records match the selected deletion criteria.']);
        }

        $remarks = trim($data['remarks']);
        DB::transaction(function () use ($records, $remarks) {
            foreach ($records as $record) {
                ActivityLog::record(
                    'deleted',
                    'Moved checklist history to the recycle bin: ' . $remarks,
                    $record,
                    ActivityLog::makePayload([
                        'maintenance_record_id' => $record->id,
                        'device_id' => $record->device_id,
                        'property_number' => $record->device?->property_number,
                        'maintenance_date' => $record->maintenance_date?->toDateString(),
                        'checked_by' => $record->checkedBy?->name,
                        'photos_retained' => DeviceMaintenancePhoto::where('maintenance_record_id', $record->id)->count(),
                        'deletion_remarks' => $remarks,
                    ])
                );

                // Keep the checklist row and its photos recoverable. The
                // gallery hides photos belonging to a trashed checklist.
                $record->delete();
            }
        });

        return back()->with('success', $records->count() . ' checklist history record(s) moved to the recycle bin.');
    }

    public function restore(int $record)
    {
        abort_unless(request()->user()?->isSuperAdmin(), 403);

        $deletedRecord = DeviceMaintenanceRecord::onlyTrashed()
            ->with(['device.type', 'checkedBy'])
            ->findOrFail($record);
        $deletedRecord->restore();

        ActivityLog::record(
            'restored',
            'Restored checklist history from the recycle bin.',
            $deletedRecord,
            ActivityLog::makePayload([
                'maintenance_record_id' => $deletedRecord->id,
                'device_id' => $deletedRecord->device_id,
                'property_number' => $deletedRecord->device?->property_number,
                'maintenance_date' => $deletedRecord->maintenance_date?->toDateString(),
            ])
        );

        return back()->with('success', 'Checklist history restored.');
    }

    public function forceDestroy(int $record)
    {
        abort_unless(request()->user()?->isSuperAdmin(), 403);

        $data = request()->validate([
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        $deletedRecord = DeviceMaintenanceRecord::onlyTrashed()
            ->with(['device.type', 'photos'])
            ->findOrFail($record);

        $summary = [
            'maintenance_record_id' => $deletedRecord->id,
            'device_id' => $deletedRecord->device_id,
            'property_number' => $deletedRecord->device?->property_number,
            'maintenance_date' => $deletedRecord->maintenance_date?->toDateString(),
            'photos_deleted' => $deletedRecord->photos->count(),
        ];

        DB::transaction(function () use ($deletedRecord) {
            foreach ($deletedRecord->photos as $photo) {
                if (filled($photo->photo_path)) {
                    Storage::disk('public')->delete($photo->photo_path);
                }
                $photo->delete();
            }

            $deletedRecord->forceDelete();
        });

        ActivityLog::record(
            'force_deleted',
            'Permanently deleted checklist history from the recycle bin: ' . trim($data['remarks']),
            null,
            ActivityLog::makePayload($summary, [
                'deletion_remarks' => trim($data['remarks']),
            ])
        );

        return back()->with('success', 'Checklist history permanently deleted.');
    }
}
