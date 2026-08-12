<?php

namespace App\Console\Commands;

use App\Services\LocalMaintenanceModelService;
use App\Services\MaintenanceAttentionService;
use Illuminate\Console\Command;
use Throwable;

class TrainMaintenanceAttentionModel extends Command
{
    protected $signature = 'maintenance:train-model {--min-samples=20 : Minimum number of inventory examples required}';

    protected $description = 'Train the optional offline maintenance-attention model from PMAMS history';

    public function handle(
        MaintenanceAttentionService $attentionService,
        LocalMaintenanceModelService $modelService,
    ): int {
        $rows = $attentionService->trainingRows();
        $minimum = max(2, (int) $this->option('min-samples'));

        if (count($rows) < $minimum) {
            $this->error("Training needs at least {$minimum} inventory examples; found " . count($rows) . '.');
            $this->line('Use --min-samples=2 only for a small test dataset.');

            return self::FAILURE;
        }

        $labels = array_count_values(array_map(fn (array $row): int => (int) $row['label'], $rows));
        if (count($labels) < 2) {
            $this->error('Training needs both attention and no-attention examples. Add checklist/history data first.');

            return self::FAILURE;
        }

        try {
            $result = $modelService->train($rows);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            $this->line('Install the offline dependencies with: python -m pip install -r ai/requirements.txt');

            return self::FAILURE;
        }

        $this->info('Offline maintenance-attention model trained successfully.');
        $this->line('Examples: ' . ($result['samples'] ?? count($rows)));
        $this->line('Old-equipment label threshold: ' . MaintenanceAttentionService::OLD_EQUIPMENT_AGE_YEARS . ' years.');
        $this->line('Model: ' . $modelService->modelPath());

        return self::SUCCESS;
    }
}
