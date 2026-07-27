<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\DatabaseBackupController;
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
            $relativePath = 'backups/pmams-backup-' . now()->format('Y-m') . '.sql';

            Storage::disk('local')->makeDirectory('backups');
            Storage::disk('local')->put($relativePath, $sql);

            $this->info('Monthly backup saved to ' . Storage::disk('local')->path($relativePath));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('The monthly backup could not be created. Check the application log for details.');

            return self::FAILURE;
        }
    }
}
