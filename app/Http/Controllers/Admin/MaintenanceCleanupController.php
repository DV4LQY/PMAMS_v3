<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Device;
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
        abort_unless($request->user()?->isSuperAdmin() || $request->user()?->canMenu('maintenance_cleanup'), 403);

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $deletedRecords = DeviceMaintenanceRecord::onlyTrashed()
            ->with([
                'device' => fn ($query) => $query->withTrashed()->with('type'),
                'checkedBy' => fn ($query) => $query->withTrashed(),
            ])
            ->when($dateFrom, fn ($q) => $q->whereDate('maintenance_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('maintenance_date', '<=', $dateTo))
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->paginate(50, ['*'], 'deleted_page')
            ->withQueryString();

        return view('admin.maintenance-cleanup.index', [
            'deletedRecords' => $deletedRecords,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'windowMonths' => (int) SystemSetting::getValue(self::WINDOW_KEY, 3),
        ]);
    }

    public function updateWindow(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin() || $request->user()?->canMenu('maintenance_cleanup'), 403);

        $data = $request->validate([
            'window_months' => ['required', 'integer', 'min:1', 'max:36'],
        ]);

        SystemSetting::putValue(self::WINDOW_KEY, $data['window_months']);

        return back()->with('success', 'Checklist duplicate window updated to ' . $data['window_months'] . ' month(s).');
    }

    public function destroy(Request $request)
    {
        $canDeleteCheckedReport = $request->routeIs('admin.reports.checkedEquipment.delete')
            && $request->user()?->canAction('checked_equipment_report', 'delete');

        abort_unless(
            $request->user()?->isSuperAdmin()
                || $canDeleteCheckedReport
                || (! $request->routeIs('admin.reports.checkedEquipment.delete')
                    && $request->user()?->canAction('checklist', 'delete')),
            403
        );

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
            // The report UI collects deletion remarks in a confirmation
            // modal. Enforce the same requirement on the server so a crafted
            // request cannot bypass the audit trail. Checklist Cleanup keeps
            // its existing optional-remarks behaviour below.
            'remarks' => $request->routeIs('admin.reports.checkedEquipment.delete')
                ? ['required', 'string', 'max:1000']
                : ['nullable', 'string', 'max:1000'],
        ]);

        $ids = collect($data['record_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
        // Match Equipment bulk deletion: selecting all is explicit. A date or
        // another filter alone must never turn an empty selection into a bulk
        // delete request.
        $selectAll = (bool) ($data['select_all'] ?? false);
        if ($selectAll) {
            abort_unless($request->user()?->isSuperAdmin(), 403);
        }
        if (! $selectAll && $ids->isEmpty()) {
            return back()->withErrors(['record_ids' => 'Select checklist history rows or choose select all matching records.']);
        }

        $query = DeviceMaintenanceRecord::query()
            ->whereNotNull('checked_by')
            ->whereHas('device');
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
                            ->orWhere('maintenance_type', 'like', $like)
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

        $remarks = trim((string) ($data['remarks'] ?? ''));
        $deviceIds = $records->pluck('device_id')->filter()->unique()->values();
        DB::transaction(function () use ($records, $remarks, $deviceIds) {
            foreach ($records as $record) {
                ActivityLog::record(
                    'deleted',
                    'Deleted checklist history from Checklist Cleanup.',
                    $record,
                    ActivityLog::makePayload([
                        'maintenance_record_id' => $record->id,
                        'device_id' => $record->device_id,
                        'property_number' => $record->device?->property_number,
                        'maintenance_date' => $record->maintenance_date?->toDateString(),
                        'checked_by' => $record->checkedBy?->name,
                        'deletion_remarks' => $remarks,
                    ])
                );

                $record->delete();
            }

            $this->syncDeviceMaintenanceDates($deviceIds);
        });

        return back()->with('success', $records->count() . ' checklist history record(s) deleted.');
    }

    public function restore(int $record)
    {
        abort_unless(request()->user()?->isSuperAdmin() || request()->user()?->canAction('checklist', 'edit'), 403);

        $deletedRecord = DeviceMaintenanceRecord::onlyTrashed()
            ->with([
                'device' => fn ($query) => $query->withTrashed()->with('type'),
                'checkedBy' => fn ($query) => $query->withTrashed(),
            ])
            ->findOrFail($record);
        $deletedRecord->restore();
        $this->syncDeviceMaintenanceDates(collect([$deletedRecord->device_id]));

        ActivityLog::record(
            'restored',
            'Restored checklist history from Checklist Cleanup.',
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

    public function restoreBulk(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin() || $request->user()?->canAction('checklist', 'edit'), 403);

        $data = $request->validate([
            'record_ids' => ['nullable', 'array'],
            'record_ids.*' => ['integer', 'distinct'],
            'select_all' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $selectAll = (bool) ($data['select_all'] ?? false);
        $ids = collect($data['record_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
        if (! $selectAll && $ids->isEmpty()) {
            return back()->withErrors(['record_ids' => 'Select at least one deleted checklist history row or choose select all matching records.']);
        }

        $records = DeviceMaintenanceRecord::onlyTrashed()
            ->when(! $selectAll, fn ($query) => $query->whereIn('id', $ids))
            ->when($selectAll && ($data['date_from'] ?? null), fn ($query, $date) => $query->whereDate('maintenance_date', '>=', $date))
            ->when($selectAll && ($data['date_to'] ?? null), fn ($query, $date) => $query->whereDate('maintenance_date', '<=', $date))
            ->orderByDesc('deleted_at')
            ->get();

        if ($records->isEmpty()) {
            return back()->withErrors(['record_ids' => 'No deleted checklist history records match the selected criteria.']);
        }

        $deviceIds = $records->pluck('device_id')->filter()->unique()->values();
        DB::transaction(function () use ($records, $deviceIds) {
            foreach ($records as $record) {
                $record->restore();
            }

            $this->syncDeviceMaintenanceDates($deviceIds);

            ActivityLog::record(
                'restored',
                'Restored checklist history from Checklist Cleanup.',
                null,
                ActivityLog::makePayload(['checklists_restored' => $records->count()])
            );
        });

        return back()->with('success', "Restored {$records->count()} checklist history record(s).");
    }

    public function forceDestroy(int $record)
    {
        abort_unless(request()->user()?->isSuperAdmin() || request()->user()?->canAction('checklist', 'delete'), 403);

        $data = request()->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $deletedRecord = DeviceMaintenanceRecord::onlyTrashed()
            ->with([
                'device' => fn ($query) => $query->withTrashed()->with('type'),
                'photos',
            ])
            ->findOrFail($record);

        $remarks = trim((string) ($data['remarks'] ?? ''));

        $summary = DB::transaction(function () use ($deletedRecord, $remarks) {
            $summary = $this->permanentlyDeleteRecord($deletedRecord);
            $this->syncDeviceMaintenanceDates(collect([$deletedRecord->device_id]));
            ActivityLog::record(
                'force_deleted',
                'Permanently deleted checklist history from Checklist Cleanup.',
                null,
                ActivityLog::makePayload($summary, ['deletion_remarks' => $remarks])
            );

            return $summary;
        });

        return back()->with('success', 'Checklist history permanently deleted.');
    }

    public function forceDestroyBulk(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin() || $request->user()?->canAction('checklist', 'delete'), 403);

        $data = $request->validate([
            'record_ids' => ['nullable', 'array'],
            'record_ids.*' => ['integer', 'distinct'],
            'select_all' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $remarks = trim((string) ($data['remarks'] ?? ''));

        $selectAll = (bool) ($data['select_all'] ?? false);
        $ids = collect($data['record_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
        if (! $selectAll && $ids->isEmpty()) {
            return back()->withErrors(['record_ids' => 'Select at least one deleted checklist history row or choose select all matching records.']);
        }

        $records = DeviceMaintenanceRecord::onlyTrashed()
            ->with([
                'device' => fn ($query) => $query->withTrashed()->with('type'),
                'photos',
            ])
            ->when(! $selectAll, fn ($query) => $query->whereIn('id', $ids))
            ->when($selectAll && ($data['date_from'] ?? null), fn ($query, $date) => $query->whereDate('maintenance_date', '>=', $date))
            ->when($selectAll && ($data['date_to'] ?? null), fn ($query, $date) => $query->whereDate('maintenance_date', '<=', $date))
            ->orderByDesc('deleted_at')
            ->get();

        if ($records->isEmpty()) {
            return back()->withErrors(['record_ids' => 'No deleted checklist history records match the selected criteria.']);
        }

        $deviceIds = $records->pluck('device_id')->filter()->unique()->values();
        $count = DB::transaction(function () use ($records, $remarks, $deviceIds) {
            foreach ($records as $record) {
                $summary = $this->permanentlyDeleteRecord($record);
                ActivityLog::record(
                    'force_deleted',
                    'Permanently deleted checklist history from Checklist Cleanup.',
                    null,
                    ActivityLog::makePayload($summary, ['deletion_remarks' => $remarks])
                );
            }

            $this->syncDeviceMaintenanceDates($deviceIds);

            return $records->count();
        });

        return back()->with('success', "Permanently deleted {$count} checklist history record(s).");
    }

    private function permanentlyDeleteRecord(DeviceMaintenanceRecord $record): array
    {
        $summary = [
            'maintenance_record_id' => $record->id,
            'device_id' => $record->device_id,
            'property_number' => $record->device?->property_number,
            'maintenance_date' => $record->maintenance_date?->toDateString(),
            'photos_deleted' => $record->photos->count(),
        ];

        foreach ($record->photos as $photo) {
            if (filled($photo->photo_path)) {
                Storage::disk('public')->delete($photo->photo_path);
            }
            $photo->delete();
        }

        $record->forceDelete();

        return $summary;
    }

    /**
     * Keep the equipment summary in sync with the surviving (non-deleted)
     * checklist history after a cleanup or restore operation.
     */
    private function syncDeviceMaintenanceDates($deviceIds): void
    {
        $ids = collect($deviceIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $parentDevices = Device::withTrashed()->whereIn('id', $ids)->get();
        $linkedDevices = Device::withTrashed()
            ->whereIn('part_of_property_number', $parentDevices->pluck('property_number')->filter()->values())
            ->get();

        $latestByDevice = [];
        $parentDevices->each(function ($device) use (&$latestByDevice) {
            $latest = DeviceMaintenanceRecord::query()
                ->where('device_id', $device->id)
                ->orderByDesc('maintenance_date')
                ->orderByDesc('id')
                ->first();

            $latestByDevice[$device->id] = $latest;
            $device->forceFill([
                'last_maintenance_date' => $latest?->maintenance_date,
                'maintenance_remarks' => $latest?->remarks,
            ])->saveQuietly();
        });

        // Checklist saves also copy the parent date/remarks to linked
        // peripherals. Rebuild that copied summary when the parent history is
        // deleted or restored so peripherals do not retain stale dates.
        $parentLatestByProperty = $parentDevices->mapWithKeys(fn ($device) => [
            (string) $device->property_number => $latestByDevice[$device->id] ?? null,
        ]);
        $linkedDevices->each(function ($device) use ($parentLatestByProperty) {
            $ownLatest = DeviceMaintenanceRecord::query()
                ->where('device_id', $device->id)
                ->orderByDesc('maintenance_date')
                ->orderByDesc('id')
                ->first();
            $parentLatest = $parentLatestByProperty->get((string) $device->part_of_property_number);
            $latest = $ownLatest ?: $parentLatest;

            $device->forceFill([
                'last_maintenance_date' => $latest?->maintenance_date,
                'maintenance_remarks' => $latest?->remarks,
            ])->saveQuietly();
        });
    }
}
