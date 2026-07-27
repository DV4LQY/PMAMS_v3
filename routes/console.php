<?php

use Illuminate\Foundation\Inspiring;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Evaluate the saved day/time every minute so a schedule change takes effect
// without requiring a scheduler-worker restart. Run `php artisan schedule:work`
// (or schedule:run from Windows Task Scheduler) for Laravel to execute it.
Schedule::command('database:backup-monthly')
    ->everyMinute()
    ->withoutOverlapping(30)
    ->when(function (): bool {
        try {
            $day = (int) SystemSetting::getValue('database_backup_day', 1);
            $time = (string) SystemSetting::getValue('database_backup_time', '02:00');
        } catch (\Throwable) {
            $day = 1;
            $time = '02:00';
        }

        return now()->day === $day && now()->format('H:i') === $time;
    });
