<?php

use Illuminate\Foundation\Inspiring;
use App\Http\Controllers\Admin\DatabaseBackupController;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Evaluate the saved frequency/day/time every minute so a schedule change takes effect
// without requiring a scheduler-worker restart. Run `php artisan schedule:work`
// (or schedule:run from Windows Task Scheduler) for Laravel to execute it.
Schedule::command('database:backup-monthly')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->when(function (): bool {
        try {
            $frequency = (string) SystemSetting::getValue('database_backup_frequency', 'monthly');
            $day = (int) SystemSetting::getValue('database_backup_day', 1);
            $weekday = (int) SystemSetting::getValue('database_backup_weekday', 1);
            $time = (string) SystemSetting::getValue('database_backup_time', '02:00');
        } catch (\Throwable) {
            $frequency = 'monthly';
            $day = 1;
            $weekday = 1;
            $time = '02:00';
        }

        // Use Laravel's configured timezone explicitly. The PHP CLI timezone
        // can differ from the web server timezone on Windows/XAMPP.
        $current = now(config('app.timezone', 'UTC'));
        $time = trim($time);
        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            return false;
        }

        [$hour, $minute] = array_map('intval', explode(':', $time, 2));
        $currentMinutes = ((int) $current->format('H') * 60) + (int) $current->format('i');
        $scheduledMinutes = ($hour * 60) + $minute;
        $dateMatches = $frequency === 'weekly'
            ? (int) $current->dayOfWeek === $weekday
            : (int) $current->day === $day;

        if (! $dateMatches || $currentMinutes < $scheduledMinutes) {
            return false;
        }

        // Keep one idempotency marker per scheduled slot. This allows a
        // scheduler invocation that starts a few minutes late to catch up,
        // while preventing a backup on every minute after the target time.
        $slot = $frequency === 'weekly'
            ? 'weekly:' . $current->format('o-W') . ':' . $time
            : 'monthly:' . $current->format('Y-m-d') . ':' . $time;

        return (string) SystemSetting::getValue(DatabaseBackupController::BACKUP_LAST_SLOT_KEY, '') !== $slot;
    });

// Retrain the optional local model once each day after the configured time.
//
// `dailyAt()` only fires at one exact minute.  If `schedule:work` starts after
// that minute (for example after a PC restart), Laravel waits until tomorrow.
// Checking every minute lets the task catch up safely while the model metadata
// keeps it idempotent: a successful training run for today prevents any repeat.
Schedule::command('maintenance:train-model', [
    '--min-samples' => max(2, (int) config('maintenance.attention_ai.min_samples', 20)),
])
    ->everyMinute()
    ->withoutOverlapping(60)
    ->appendOutputTo(storage_path('logs/maintenance-model-schedule.log'))
    ->when(function (): bool {
        if (! filter_var(config('maintenance.attention_ai.auto_train', true), FILTER_VALIDATE_BOOL)
            || ! filter_var(config('maintenance.attention_ai.enabled', true), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        $time = trim((string) config('maintenance.attention_ai.train_time', '16:00'));
        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            return false;
        }

        $timezone = (string) config('app.timezone', 'UTC');
        $now = now($timezone);
        [$hour, $minute] = array_map('intval', explode(':', $time, 2));
        $currentMinutes = ((int) $now->format('H') * 60) + (int) $now->format('i');
        if ($currentMinutes < (($hour * 60) + $minute)) {
            return false;
        }

        $metadataPath = (string) config('maintenance.attention_ai.metadata');
        if (! is_file($metadataPath)) {
            return true;
        }

        try {
            $metadata = json_decode((string) file_get_contents($metadataPath), true, 512, JSON_THROW_ON_ERROR);
            $trainedAt = $metadata['trained_at'] ?? null;

            if (! is_string($trainedAt) || trim($trainedAt) === '') {
                return true;
            }

            return ! \Carbon\CarbonImmutable::parse($trainedAt)->setTimezone($timezone)->isSameDay($now);
        } catch (\Throwable) {
            // A missing or damaged metadata file should not permanently block
            // the next automatic training attempt.
            return true;
        }
    });

// Persist one month-to-date attention point daily. updateOrCreate keeps the
// current month accurate while preserving completed months for trend history.
Schedule::command('maintenance:record-attention-snapshot')
    ->dailyAt((string) config('maintenance.attention_ai.snapshot_time', '16:30'))
    ->withoutOverlapping(30);
