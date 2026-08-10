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
