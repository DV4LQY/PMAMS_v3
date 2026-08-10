<?php

use Illuminate\Foundation\Inspiring;
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
    ->withoutOverlapping(30)
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

        $dateMatches = $frequency === 'weekly'
            ? now()->dayOfWeek === $weekday
            : now()->day === $day;

        return $dateMatches && now()->format('H:i') === $time;
    });

// Retrain the optional local model once per month after the scheduled backup.
// The scheduler is evaluated daily so the day/time can be changed in .env
// without editing code or restarting the scheduler worker.
Schedule::command('maintenance:train-model', [
    '--min-samples' => max(2, (int) config('maintenance.attention_ai.min_samples', 20)),
])
    ->dailyAt((string) config('maintenance.attention_ai.train_time', '03:30'))
    ->withoutOverlapping(60)
    ->when(function (): bool {
        if (! filter_var(config('maintenance.attention_ai.auto_train', true), FILTER_VALIDATE_BOOL)
            || ! filter_var(config('maintenance.attention_ai.enabled', true), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        return now()->day === min(28, max(1, (int) config('maintenance.attention_ai.train_day', 1)));
    });
