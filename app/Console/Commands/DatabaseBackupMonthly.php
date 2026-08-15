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

    protected $description = 'Save a portable MySQL/MariaDB backup in local storage and the configured backup directory';

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

            if (! $disk->put($relativePath, $sql)) {
                throw new \RuntimeException('The local backup could not be written.');
            }

            $this->info(ucfirst($frequency) . ' backup saved to ' . $disk->path($relativePath));

            // Keep the local copy as the primary backup. When a separate
            // PMAMS_BACKUP_PATH is configured (for example Z:\\PMAMS_Backup),
            // write the same dump there as a second, non-overwriting copy.
            $localRoot = $this->normalisePath($disk->path('backups'));
            $secondaryPath = trim((string) config('filesystems.disks.pmams_backups.root', ''));
            $secondaryRoot = $this->normalisePath($secondaryPath);

            if ($secondaryPath === '') {
                $this->warn('The local backup was created, but PMAMS_BACKUP_PATH is not configured; no additional copy was attempted.');
            } elseif ($secondaryRoot !== $localRoot) {
                try {
                    // Use the native filesystem for mapped/UNC Windows paths.
                    // Flysystem can normalize a mapped drive as Z://..., which
                    // makes an otherwise available Z:\\ path look missing.
                    $directoryAvailable = false;
                    for ($attempt = 0; $attempt < 3; $attempt++) {
                        clearstatcache(true, $secondaryPath);
                        if (is_dir($secondaryPath)) {
                            $directoryAvailable = true;
                            break;
                        }

                        @mkdir($secondaryPath, 0775, true);
                        usleep(250000);
                    }

                    if (! $directoryAvailable && ! is_dir($secondaryPath)) {
                        throw new \RuntimeException('The configured secondary backup directory is not available.');
                    }

                    $secondaryFilename = $this->uniqueNativeFilename($secondaryPath, basename($relativePath));
                    $secondaryFilePath = rtrim($secondaryPath, '\\/') . DIRECTORY_SEPARATOR . $secondaryFilename;
                    $written = false;
                    for ($attempt = 0; $attempt < 3; $attempt++) {
                        if (@file_put_contents($secondaryFilePath, $sql, LOCK_EX) !== false) {
                            $written = true;
                            break;
                        }

                        usleep(250000);
                    }

                    if (! $written) {
                        throw new \RuntimeException('The configured secondary backup directory is not writable.');
                    }

                    $this->info('Additional backup saved to ' . $secondaryFilePath);
                } catch (Throwable $secondaryException) {
                    // Do not lose the primary local backup when a mapped or
                    // network drive is unavailable to Apache/Task Scheduler.
                    report($secondaryException);
                    $this->warn('The local backup was created, but the additional backup could not be saved: ' . $secondaryException->getMessage());
                }
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('The scheduled backup could not be created. Check the application log for details.');

            return self::FAILURE;
        }
    }

    private function uniqueNativeFilename(string $directory, string $filename): string
    {
        $candidate = $filename;
        $suffix = 1;
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);

        while (file_exists(rtrim($directory, '\\/') . DIRECTORY_SEPARATOR . $candidate)) {
            $candidate = $basename . '-' . $suffix . ($extension !== '' ? '.' . $extension : '');
            $suffix++;
        }

        return $candidate;
    }

    private function normalisePath(string $path): string
    {
        return strtolower(rtrim(str_replace('/', '\\', $path), '\\'));
    }
}
