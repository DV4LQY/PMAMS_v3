<?php

namespace App\Console\Commands;

use App\Services\LocalMaintenanceModelService;
use App\Services\MaintenanceAttentionService;
use Illuminate\Console\Command;
use Throwable;

class TrainMaintenanceAttentionModel extends Command
{
    protected $signature = 'maintenance:train-model
                            {--min-samples=20 : Minimum number of inventory examples required}
                            {--trigger=scheduled : Training trigger saved in model metadata}';

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
            $this->annotateMetadata((string) $this->option('trigger'));
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
            $this->error($message);

            if (str_contains(strtolower($message), 'access is denied')) {
                $this->line('Windows blocked the configured Python runtime. Set MAINTENANCE_AI_PYTHON to a runnable Python installation that the web server can access.');
            } else {
                $this->line('Install the offline dependencies with: python -m pip install -r ai/requirements.txt');
            }

            return self::FAILURE;
        }

        $this->info('Offline maintenance-attention model trained successfully.');
        $this->line('Examples: ' . ($result['samples'] ?? count($rows)));
        $this->line('Old-equipment label threshold: ' . MaintenanceAttentionService::OLD_EQUIPMENT_AGE_YEARS . ' years.');
        $this->line('Model: ' . $modelService->modelPath());
        $this->line('Trigger: ' . $this->trainingTrigger());

        return self::SUCCESS;
    }

    /**
     * Store how the model was trained alongside the Python-generated
     * timestamp. This lets the report distinguish a manual refresh from the
     * scheduled job without changing the model format or prediction logic.
     */
    private function annotateMetadata(string $trigger): void
    {
        $path = (string) config('maintenance.attention_ai.metadata');
        if ($path === '' || ! is_file($path)) {
            return;
        }

        try {
            $metadata = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($metadata)) {
                return;
            }

            $metadata['trained_trigger'] = $this->trainingTrigger($trigger);
            file_put_contents(
                $path,
                json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
                LOCK_EX
            );
        } catch (Throwable $exception) {
            // Training succeeded even if the optional provenance annotation
            // cannot be written. The model timestamp remains available.
            $this->warn('Model trained, but its trigger metadata could not be saved: ' . $exception->getMessage());
        }
    }

    private function trainingTrigger(?string $trigger = null): string
    {
        return in_array(strtolower(trim($trigger ?? (string) $this->option('trigger'))), ['manual', 'scheduled'], true)
            ? strtolower(trim($trigger ?? (string) $this->option('trigger')))
            : 'scheduled';
    }
}
