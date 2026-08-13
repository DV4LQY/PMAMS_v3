<?php

namespace App\Console\Commands;

use App\Models\MaintenanceAttentionSnapshot;
use App\Models\SystemSetting;
use App\Services\MaintenanceAttentionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use InvalidArgumentException;

class RecordMaintenanceAttentionSnapshot extends Command
{
    protected $signature = 'maintenance:record-attention-snapshot
                            {--month= : Calendar month to capture in YYYY-MM format (defaults to the current month)}';

    protected $description = 'Persist the monthly maintenance-attention trend snapshot for the dashboard';

    public function handle(MaintenanceAttentionService $attentionService): int
    {
        try {
            $month = $this->snapshotMonth();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        // The rule output is the auditable baseline. The configured engine is
        // captured separately so the chart can show how many current items
        // also receive a high-confidence local-model recommendation.
        $rules = $attentionService->recommendations('rules');
        $mode = MaintenanceAttentionService::normalizeMode(
            (string) SystemSetting::getValue(MaintenanceAttentionService::MODE_SETTING_KEY, 'hybrid')
        );
        $active = $mode === 'rules'
            ? $rules
            : $attentionService->recommendations($mode);

        $counts = $rules->countBy(fn (array $item): string => strtolower((string) ($item['priority'] ?? 'low')));
        $snapshot = MaintenanceAttentionSnapshot::updateOrCreate(
            ['snapshot_month' => $month->toDateString()],
            [
                'critical_count' => (int) $counts->get('critical', 0),
                'high_count' => (int) $counts->get('high', 0),
                'medium_count' => (int) $counts->get('medium', 0),
                'low_count' => (int) $counts->get('low', 0),
                'ai_recommended_count' => (int) $active->where('ai_recommended', true)->count(),
                'total_count' => $rules->count(),
                'engine_mode' => $mode,
                'captured_at' => now(),
            ]
        );

        $this->info('Maintenance-attention snapshot saved for ' . $snapshot->snapshot_month->format('F Y') . '.');
        $this->line('Rule recommendations: ' . $snapshot->total_count . '; AI recommendations: ' . $snapshot->ai_recommended_count . '.');

        return self::SUCCESS;
    }

    private function snapshotMonth(): Carbon
    {
        $value = trim((string) ($this->option('month') ?: now()->format('Y-m')));

        $month = Carbon::createFromFormat('!Y-m', $value);
        if (! $month || $month->format('Y-m') !== $value) {
            throw new InvalidArgumentException('The --month option must use YYYY-MM format, for example 2026-08.');
        }

        if (! $month->isSameMonth(now())) {
            throw new InvalidArgumentException('Snapshots can only be captured for the current month. Historical months are preserved from their original capture and cannot be reconstructed from today\'s inventory.');
        }

        return $month->startOfMonth();
    }
}
