<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\DatabaseBackupController;
use App\Models\SystemSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DatabaseBackupMonthly extends Command
{
    protected $signature = 'database:backup-monthly';

    protected $description = 'Save a portable MySQL/MariaDB backup in local storage';

    public function handle(DatabaseBackupController $backupController): int
    {
        try {
            $sql = $backupController->generateDumpForScheduler();
            $frequency = (string) SystemSetting::getValue(DatabaseBackupController::BACKUP_FREQUENCY_KEY, 'monthly');
            $disk = Storage::disk('local');
            $disk->makeDirectory('backups');

            // Always include the full timestamp in scheduled backup names. This
            // keeps every run as a separate historical file instead of replacing
            // an existing monthly/weekly backup in the directory.
            $prefix = $frequency === 'weekly' ? 'pmams-backup-weekly-' : 'pmams-backup-';
            $relativePath = 'backups/' . $prefix . now()->format('Y-m-d-His-u') . '.sql';
            $suffix = 1;
            while ($disk->exists($relativePath)) {
                $relativePath = 'backups/' . $prefix . now()->format('Y-m-d-His-u') . '-' . $suffix . '.sql';
                $suffix++;
            }

            $disk->put($relativePath, $sql);

            $this->info(ucfirst($frequency) . ' backup saved to ' . $disk->path($relativePath));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('The scheduled backup could not be created. Check the application log for details.');

            return self::FAILURE;
        }
    }
}
