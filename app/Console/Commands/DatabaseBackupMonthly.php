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
            $relativePath = $frequency === 'weekly'
                ? 'backups/pmams-backup-weekly-' . now()->format('Y-m-d') . '.sql'
                : 'backups/pmams-backup-' . now()->format('Y-m') . '.sql';

            Storage::disk('local')->makeDirectory('backups');
            Storage::disk('local')->put($relativePath, $sql);

            $this->info(ucfirst($frequency) . ' backup saved to ' . Storage::disk('local')->path($relativePath));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('The scheduled backup could not be created. Check the application log for details.');

            return self::FAILURE;
        }
    }
}
