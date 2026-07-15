<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Every legacy name that should be treated as the given canonical type
     * when filtering, so a renamed record type (e.g. College -> Location)
     * still matches its old log rows.
     */
    private function typesMatching(string $canonical): array
    {
        $legacy = array_keys(array_filter(
            ActivityLog::TYPE_ALIASES,
            fn ($current) => $current === $canonical
        ));

        return array_values(array_unique([$canonical, ...$legacy]));
    }

    public function index(Request $request)
    {
        $query = ActivityLog::query()->latest();

        $action = $request->query('action');

        if ($action) {
            $query->where('action', $action);
        }

        // Issued/Returned logs are always Equipment logs.
        if (in_array($action, ['issued', 'returned'])) {
            $request->merge([
                'subject_type' => 'Equipment',
            ]);
        }

        $subjectType = $request->query('subject_type');

        if ($subjectType) {
            $matchTypes = $this->typesMatching($subjectType);

            $query->where(function ($q) use ($matchTypes) {
                // Normal logs
                $q->whereIn('subject_type', $matchTypes);

                // Bulk logs
                foreach ($matchTypes as $type) {
                    $q->orWhere('changes->record_type', $type);
                }
            });
        }

        $logs = $query->paginate(25)->withQueryString();

        // Only include actions that actually exist in the log — no blank option,
        // the "Clear filters" button handles resetting.
        $actions = ActivityLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        // Same — only non-null subject types that exist.
        // Issued/Returned logs only apply to Equipment.
        if (in_array($action, ['issued', 'returned'])) {

            $subjectTypes = collect(['Equipment']);

        } else {

            // Same — only non-null subject types that exist. Legacy names
            // (e.g. "College") are canonicalized to their current name
            // (e.g. "Location") so they appear as a single option instead
            // of fragmenting into two.
            $subjectTypes = ActivityLog::all()
                ->flatMap(function ($log) {
                    return array_filter([
                        $log->subject_type,
                        data_get($log->changes, 'record_type'),
                    ]);
                })
                ->map(fn ($type) => ActivityLog::canonicalType($type))
                ->unique()
                ->sort()
                ->values();

        }

        return view('admin.logs.index', compact('logs', 'actions', 'subjectTypes'));
    }
}
