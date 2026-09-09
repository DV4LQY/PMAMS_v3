<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Optional bridge to the offline Python/ONNX maintenance model.
 *
 * Every failure is fail-closed: callers receive an empty prediction list and
 * the deterministic Laravel maintenance rules continue to work normally.
 */
class LocalMaintenanceModelService
{
    /**
     * @param array<int, array<string, int|float>> $rows
     * @return array<int, float>
     */
    public function predict(array $rows): array
    {
        // A model trained before a rule/threshold change must not silently
        // produce recommendations from stale labels. Laravel rules remain a
        // safe fallback until the model is retrained with the current policy.
        if (! $this->isEnabled() || $rows === [] || ! $this->modelIsCurrent()) {
            return [];
        }

        $cacheKey = 'maintenance_attention_ai:' . md5(json_encode([
            'model' => @filemtime($this->modelPath()),
            'rows' => $rows,
        ], JSON_THROW_ON_ERROR));

        try {
            return Cache::remember(
                $cacheKey,
                now()->addMinutes(max(1, (int) config('maintenance.attention_ai.cache_minutes', 10))),
                fn (): array => $this->run('predict', ['rows' => $rows])
            );
        } catch (Throwable $exception) {
            // Avoid spawning a failing Python process on every page request
            // when a deployment has no runtime/dependencies yet.
            try {
                Cache::put(
                    $cacheKey,
                    [],
                    now()->addMinutes(max(1, (int) config('maintenance.attention_ai.cache_minutes', 10)))
                );
            } catch (Throwable) {
                // Cache failures must never affect the maintenance page.
            }
            Log::warning('Local maintenance model prediction was skipped.', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param array<int, array<string, int|float>> $rows
     * @return array<string, mixed>
     */
    public function train(array $rows): array
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('MAINTENANCE_AI_ENABLED is disabled. Enable it before training.');
        }

        return $this->run('train', ['rows' => $rows]);
    }

    public function isEnabled(): bool
    {
        return filter_var(config('maintenance.attention_ai.enabled', false), FILTER_VALIDATE_BOOL);
    }

    public function modelPath(): string
    {
        return (string) config('maintenance.attention_ai.model');
    }

    private function modelIsCurrent(): bool
    {
        $modelPath = $this->modelPath();
        $metadataPath = (string) config('maintenance.attention_ai.metadata');

        if (! is_file($modelPath) || ! is_file($metadataPath)) {
            return false;
        }

        try {
            $metadata = json_decode((string) file_get_contents($metadataPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return false;
        }

        return is_array($metadata)
            && ($metadata['rules_version'] ?? null) === MaintenanceAttentionService::AI_RULES_VERSION
            && (int) ($metadata['old_equipment_threshold_years'] ?? 0)
                === MaintenanceAttentionService::OLD_EQUIPMENT_AGE_YEARS;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function run(string $mode, array $payload): array
    {
        $script = (string) config('maintenance.attention_ai.script');
        $python = $this->resolvePython((string) config('maintenance.attention_ai.python', 'python'));
        $model = $this->modelPath();
        $metadata = (string) config('maintenance.attention_ai.metadata');

        if (! is_file($script)) {
            throw new \RuntimeException('The local maintenance model script is missing.');
        }

        $process = new Process([$python, $script, $mode, '--model', $model, '--metadata', $metadata]);
        $process->setInput(json_encode($payload, JSON_THROW_ON_ERROR));
        $timeoutKey = $mode === 'train' ? 'training_timeout' : 'timeout';
        $process->setTimeout(max(1, (int) config('maintenance.attention_ai.' . $timeoutKey, 10)));
        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: trim($process->getOutput());
            throw new \RuntimeException($error ?: 'The local maintenance model process failed.');
        }

        $decoded = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
            throw new \RuntimeException((string) ($decoded['error'] ?? 'The local model returned an invalid response.'));
        }

        return $mode === 'predict'
            ? array_map('floatval', (array) ($decoded['predictions'] ?? []))
            : $decoded;
    }

    private function resolvePython(string $configured): string
    {
        if ($configured !== 'python' || PHP_OS_FAMILY !== 'Windows') {
            return $configured;
        }

        // Apache/PHP often does not inherit the interactive user's Python
        // PATH. Prefer a per-user installation when one is discoverable.
        $localAppData = (string) getenv('LOCALAPPDATA');
        $candidates = $localAppData !== ''
            ? (glob($localAppData . '\\Programs\\Python\\Python*\\python.exe') ?: [])
            : [];
        $candidates = array_merge(
            $candidates,
            glob('C:\\Python*\\python.exe') ?: [],
            glob('C:\\Program Files\\Python*\\python.exe') ?: []
        );

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return 'py';
    }
}
