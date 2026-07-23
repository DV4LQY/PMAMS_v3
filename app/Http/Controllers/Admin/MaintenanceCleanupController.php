<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $ids = collect($data['record_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
        if ($ids->isEmpty() && empty($data['date_from']) && empty($data['date_to'])) {
            return back()->withErrors(['record_ids' => 'Select checklist history rows or provide a date range.']);
        }
        $query = DeviceMaintenanceRecord::query();
        if ($ids->isNotEmpty()) {
            $query->whereIn('id', $ids);
        } else {
            $query->when($data['date_from'] ?? null, fn ($q, $date) => $q->whereDate('maintenance_date', '>=', $date))
                ->when($data['date_to'] ?? null, fn ($q, $date) => $q->whereDate('maintenance_date', '<=', $date));
        }

        $records = $query->get(['id']);
        if ($records->isEmpty()) {
            return back()->withErrors(['record_ids' => 'Select checklist history rows or provide a date range.']);
        }

        DB::transaction(function () use ($records) {
            $photos = DeviceMaintenancePhoto::query()
                ->whereIn('maintenance_record_id', $records->modelKeys())
                ->get(['id', 'photo_path']);
            foreach ($photos as $photo) {
                Storage::disk('public')->delete($photo->photo_path);
            }
            DeviceMaintenancePhoto::query()->whereIn('id', $photos->modelKeys())->delete();
            DeviceMaintenanceRecord::query()->whereIn('id', $records->modelKeys())->delete();
        });

        return back()->with('success', $records->count() . ' checklist history record(s) deleted.');
    }
}
