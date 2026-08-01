<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
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
        // Read and normalize the query-string filters once.  In particular,
        // do not use Request::merge() and then read with query(): merge()
        // updates the input bag, while query() reads only the original URL
        // query bag.  That made the special Issued/Returned filter appear to
        // select Equipment in the UI without actually applying that filter.
        $action = trim((string) $request->query('action', ''));
        $subjectType = trim((string) $request->query('subject_type', ''));
        $userFilter = trim((string) $request->query('user_id', ''));

        // Date inputs are normalized before being used in whereDate clauses so
        // malformed bookmarked URLs cannot produce misleading results.
        $normalizeDate = static function ($value): string {
            $value = trim((string) $value);

            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return '';
            }

            [$year, $month, $day] = array_map('intval', explode('-', $value));

            return checkdate($month, $day, $year) ? $value : '';
        };

        $dateFrom = $normalizeDate($request->query('date_from', ''));
        $dateTo = $normalizeDate($request->query('date_to', ''));

        // Do not load the complete audit table on the initial page load.
        // Activity records are fetched only after at least one filter is
        // selected, which keeps a large log table from becoming a bottleneck.
        $hasFilter = $action !== ''
            || $subjectType !== ''
            || $userFilter !== ''
            || $dateFrom !== ''
            || $dateTo !== '';

        $query = ActivityLog::query()->latest();

        if ($action !== '') {
            $query->where('action', $action);
        }

        if ($userFilter === 'system') {
            $query->whereNull('user_id');
        } elseif (ctype_digit($userFilter) && (int) $userFilter > 0) {
            $query->where('user_id', (int) $userFilter);
        }

        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Issued/Returned logs are always Equipment logs.
        if (in_array($action, ['issued', 'returned'])) {
            $subjectType = 'Equipment';
        }

        // Accept bookmarked/legacy links such as subject_type=Device while
        // keeping the current UI label (Equipment) canonical.
        $subjectType = ActivityLog::canonicalType($subjectType) ?? '';

        if ($subjectType !== '') {
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

        $logs = $hasFilter
            ? $query->paginate(25)->withQueryString()
            : ActivityLog::query()->whereRaw('1 = 0')->paginate(25)->withQueryString();

        // Only include actions that actually exist in the log — no blank option,
        // the "Clear filters" button handles resetting.
        $actions = ActivityLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        $users = User::withTrashed()
            ->whereIn('id', ActivityLog::query()
                ->select('user_id')
                ->whereNotNull('user_id')
                ->distinct())
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        // Same — only non-null subject types that exist.
        // Issued/Returned logs only apply to Equipment.
        if (in_array($action, ['issued', 'returned'])) {

            $subjectTypes = collect(['Equipment']);

        } else {

            // Same — only non-null subject types that exist. Legacy names
            // (e.g. "College") are canonicalized to their current name
            // (e.g. "Location") so they appear as a single option instead
            // of fragmenting into two.
            $subjectTypes = ActivityLog::query()
                ->select('subject_type')
                ->whereNotNull('subject_type')
                ->distinct()
                ->pluck('subject_type')
                ->merge(ActivityLog::query()
                    ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(`changes`, '$.record_type')) as record_type")
                    ->whereNotNull('changes')
                    ->whereRaw('JSON_VALID(`changes`)')
                    ->whereRaw("JSON_EXTRACT(`changes`, '$.record_type') IS NOT NULL")
                    ->distinct()
                    ->pluck('record_type'))
                ->map(fn ($type) => ActivityLog::canonicalType($type))
                ->filter()
                ->unique()
                ->sort()
                ->values();

        }

        return view('admin.logs.index', [
            'logs' => $logs,
            'actions' => $actions,
            'subjectTypes' => $subjectTypes,
            'filterAction' => $action,
            'filterSubjectType' => $subjectType,
            'users' => $users,
            'filterUser' => $userFilter,
            'filterDateFrom' => $dateFrom,
            'filterDateTo' => $dateTo,
            'hasLogFilter' => $hasFilter,
        ]);
    }
}
