<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Office;
use App\Models\SystemSetting;
use App\Services\MaintenanceAttentionService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class MaintenanceAttentionController extends Controller
{
    public function index(Request $request, MaintenanceAttentionService $maintenanceAttentionService)
    {
        $perPage = min(max((int) $request->integer('per_page', 10), 5), 50);
        $location = trim((string) $request->query('location', ''));
        $office = trim((string) $request->query('office', ''));
        $attention = trim((string) $request->query('attention', ''));
        $loaded = $request->hasAny(['location', 'office', 'attention', 'reset']);
        $mode = MaintenanceAttentionService::normalizeMode(
            (string) SystemSetting::getValue(MaintenanceAttentionService::MODE_SETTING_KEY, 'hybrid')
        );
        $locations = Location::query()
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $officeRecords = Office::query()
            ->with('location:id,name')
            ->orderBy('name')
            ->get(['id', 'location_id', 'name']);
        $offices = $officeRecords
            ->when($location !== '', fn ($items) => $items->filter(
                fn ($availableOffice) => $availableOffice->location?->name === $location
            ))
            ->pluck('name')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $allRecommendations = $loaded
            ? $maintenanceAttentionService->recommendations($mode)
            : collect();

        if ($loaded) {
            $locations = $locations
                ->merge($allRecommendations->pluck('location_name')->filter())
                ->unique()
                ->sort()
                ->values();
            $recommendationOffices = $allRecommendations
                ->when($location !== '', fn ($items) => $items->where('location_name', $location))
                ->pluck('office_name')
                ->filter();
            $offices = $offices
                ->merge($recommendationOffices)
                ->unique()
                ->sort()
                ->values();
        }

        // Prevent a stale office query value from filtering against a
        // different location after the parent location changes.
        if ($office !== '' && ! $offices->contains($office)) {
            $office = '';
        }

        $filteredRecommendations = $allRecommendations
            ->when($location !== '', fn ($items) => $items->where('location_name', $location))
            ->when($office !== '', fn ($items) => $items->where('office_name', $office))
            ->when($attention !== '', fn ($items) => $items->filter(
                fn (array $item) => in_array($attention, $item['attention_flags'] ?? [], true)
            ))
            ->values();
        $reviewCount = $filteredRecommendations->where('score', '>=', 25)->count();
        $page = LengthAwarePaginator::resolveCurrentPage('page');

        $recommendations = new LengthAwarePaginator(
            $filteredRecommendations->forPage($page, $perPage)->values(),
            $filteredRecommendations->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => $request->query(),
            ]
        );

        return view('admin.maintenance-attention.index', compact(
            'recommendations',
            'reviewCount',
            'perPage',
            'locations',
            'offices',
            'location',
            'office',
            'attention',
            'loaded',
            'mode'
        ));
    }

    /**
     * Change the recommendation engine globally. Only Super Admin may choose
     * the source because it affects what every reviewer sees.
     */
    public function updateMode(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'mode' => ['required', 'in:rules,ai,hybrid'],
        ]);

        SystemSetting::putValue(
            MaintenanceAttentionService::MODE_SETTING_KEY,
            MaintenanceAttentionService::normalizeMode($data['mode'])
        );

        return redirect()
            ->route('admin.maintenance-attention.index', ['reset' => 1])
            ->with('status', 'Maintenance attention recommendation mode updated.');
    }
}
