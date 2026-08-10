<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\MaintenanceAttentionService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class MaintenanceAttentionController extends Controller
{
    public function index(Request $request, MaintenanceAttentionService $maintenanceAttentionService)
    {
        $perPage = min(max((int) $request->integer('per_page', 10), 5), 50);
        $location = trim((string) $request->query('location', ''));
        $attention = trim((string) $request->query('attention', ''));
        $loaded = $request->hasAny(['location', 'attention', 'reset']);
        $locations = Location::query()
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $allRecommendations = $loaded
            ? $maintenanceAttentionService->recommendations()
            : collect();

        if ($loaded) {
            $locations = $locations
                ->merge($allRecommendations->pluck('location_name')->filter())
                ->unique()
                ->sort()
                ->values();
        }

        $filteredRecommendations = $allRecommendations
            ->when($location !== '', fn ($items) => $items->where('location_name', $location))
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
            'location',
            'attention',
            'loaded'
        ));
    }
}
