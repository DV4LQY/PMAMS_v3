<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\MaintenancePlanSchedule;
use App\Models\Location;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DatabaseBackupController extends Controller
{
    private const SUPPORTED_DRIVERS = ['mysql', 'mariadb'];
    public const BACKUP_DAY_KEY = 'database_backup_day';
    public const BACKUP_TIME_KEY = 'database_backup_time';
    public const BACKUP_FREQUENCY_KEY = 'database_backup_frequency';
    public const BACKUP_WEEKDAY_KEY = 'database_backup_weekday';
    // Keep each INSERT comfortably below XAMPP's default max_allowed_packet.
    private const MAX_INSERT_BYTES = 262144;

    public function index(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin() || $request->user()?->canMenu('database'), 403);
        $this->ensureSupportedDriver();

        $backupFiles = collect(Storage::disk('local')->files('backups'))
            ->filter(fn (string $path): bool => str_ends_with(strtolower($path), '.sql'))
            ->map(function (string $path): array {
                return [
                    'path' => $path,
                    'name' => basename($path),
                    'size' => (int) Storage::disk('local')->size($path),
                    'modified_at' => Storage::disk('local')->lastModified($path),
                ];
            })
            ->sortByDesc('modified_at')
            ->take(10)
            ->values()
            ->all();

        return view('admin.database.index', [
            'driver' => DB::connection()->getDriverName(),
            'database' => DB::getDatabaseName(),
            'backupTables' => $this->backupTableNames(),
            'backupFiles' => $backupFiles,
            'backupFrequency' => (string) SystemSetting::getValue(self::BACKUP_FREQUENCY_KEY, 'monthly'),
            'backupDay' => (int) SystemSetting::getValue(self::BACKUP_DAY_KEY, 1),
            'backupWeekday' => (int) SystemSetting::getValue(self::BACKUP_WEEKDAY_KEY, 1),
            'backupTime' => (string) SystemSetting::getValue(self::BACKUP_TIME_KEY, '02:00'),
            'deletedUsersCount' => User::onlyTrashed()->count(),
            'deletedDevicesCount' => Device::onlyTrashed()->count(),
            'deletedMaintenancePlansCount' => Schema::hasColumn('maintenance_plan_schedules', 'deleted_at')
                ? MaintenancePlanSchedule::onlyTrashed()->count()
                : 0,
            'deletedLocationsCount' => Schema::hasColumn('locations', 'deleted_at')
                ? Location::onlyTrashed()->count()
                : 0,
        ]);
    }

    public function updateSchedule(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin() || $request->user()?->canMenu('database'), 403);

        $data = $request->validate([
            // Days 1–28 exist in every month, so the backup is never skipped.
            'backup_frequency' => ['required', 'in:monthly,weekly'],
            'backup_day' => ['nullable', 'integer', 'min:1', 'max:28', 'required_if:backup_frequency,monthly'],
            'backup_weekday' => ['nullable', 'integer', 'min:0', 'max:6', 'required_if:backup_frequency,weekly'],
            'backup_time' => ['required', 'date_format:H:i'],
        ]);

        $backupDay = (int) ($data['backup_day'] ?? SystemSetting::getValue(self::BACKUP_DAY_KEY, 1));
        $backupWeekday = (int) ($data['backup_weekday'] ?? SystemSetting::getValue(self::BACKUP_WEEKDAY_KEY, 1));
        SystemSetting::putValue(self::BACKUP_FREQUENCY_KEY, $data['backup_frequency']);
        SystemSetting::putValue(self::BACKUP_DAY_KEY, $backupDay);
        SystemSetting::putValue(self::BACKUP_WEEKDAY_KEY, $backupWeekday);
        SystemSetting::putValue(self::BACKUP_TIME_KEY, $data['backup_time']);
        ActivityLog::record('updated', 'Updated the automatic database backup schedule.', null, [
            'frequency' => $data['backup_frequency'],
            'day' => $backupDay,
            'weekday' => $backupWeekday,
            'time' => $data['backup_time'],
        ]);

        return back()->with('success', 'Automatic backup schedule updated. Keep the Laravel scheduler running for the new setting to take effect.');
    }

    public function download(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin() || $request->user()?->canMenu('database'), 403);
        $this->ensureSupportedDriver();

        $filename = 'pmams-backup-' . now()->format('Ymd-His') . '.sql';
        ActivityLog::record('exported', 'Exported a database backup SQL file.');

        return response()->streamDownload(function () {
            echo $this->buildDump();
        }, $filename, [
            'Content-Type' => 'application/sql; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Generate a portable dump for the scheduled local backup command.
     * This intentionally has no authentication check because the command is
     * only registered with the server-side scheduler.
     */
    public function generateDumpForScheduler(): string
    {
        $this->ensureSupportedDriver();

        return $this->buildDump();
    }

    public function restore(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin() || $request->user()?->canMenu('database'), 403);
        $this->ensureSupportedDriver();

        $request->validate([
            'backup' => ['required', 'file', 'max:102400'],
        ]);

        $file = $request->file('backup');
        $extension = strtolower((string) $file?->getClientOriginalExtension());
        if (! in_array($extension, ['sql', 'txt'], true)) {
            return back()->withErrors(['backup' => 'Only .sql or .txt SQL backup files are allowed.']);
        }

        $uploadPath = $file->getRealPath();
        if (! is_string($uploadPath) || ! is_readable($uploadPath) || (int) @filesize($uploadPath) === 0) {
            return back()->withErrors(['backup' => 'The selected SQL backup is empty or could not be read.']);
        }

        $maintenanceStarted = false;
        $safetyBackup = null;
        $executed = 0;

        try {
            // Prevent users from writing while tables are being replaced.
            // The current request continues, then maintenance mode is removed
            // in finally even when the restore fails.
            if (! app()->isDownForMaintenance()) {
                Artisan::call('down');
                $maintenanceStarted = true;
            }

            // Always keep a rollback point before replacing the live database.
            $safetyBackup = 'backups/pre-restore-' . now()->format('Ymd-His-u') . '.sql';
            Storage::disk('local')->makeDirectory('backups');
            Storage::disk('local')->put($safetyBackup, $this->buildDump());

            // Process the upload as a stream so a large SQL file is not copied
            // into a second in-memory string or statement array.
            $executed = $this->executeRestoreFile($uploadPath);

            // A backup may come from an older deployment. Run migrations after
            // import so required columns (for example deleted_at) are restored.
            if (Artisan::call('migrate', ['--force' => true]) !== 0) {
                throw new \RuntimeException('Post-restore migrations did not complete successfully.');
            }

            try {
                ActivityLog::record('restored', 'Restored a database backup SQL file.', null, [
                    'statements' => $executed,
                    'filename' => $file->getClientOriginalName(),
                    'safety_backup' => $safetyBackup,
                ]);
            } catch (Throwable $loggingException) {
                // A restored legacy schema must not be rolled back merely
                // because the audit entry could not be written.
                report($loggingException);
            }

            return back()->with('success', "Database restore completed ({$executed} SQL statements processed). A pre-restore safety backup was saved to {$safetyBackup}.");
        } catch (Throwable $exception) {
            report($exception);

            $recovered = false;
            if ($safetyBackup !== null && Storage::disk('local')->exists($safetyBackup)) {
                try {
                    $this->executeRestoreFile(Storage::disk('local')->path($safetyBackup));
                    Artisan::call('migrate', ['--force' => true]);
                    $recovered = true;
                } catch (Throwable $recoveryException) {
                    report($recoveryException);
                }
            }

            $message = $recovered
                ? 'The SQL restore failed. The database was automatically restored from the pre-restore safety backup; verify the application before continuing.'
                : 'The SQL restore failed and automatic recovery could not be confirmed. Keep the pre-restore safety backup and have the database administrator verify or restore it before continuing.';

            return back()->withErrors(['backup' => $message]);
        } finally {
            if ($maintenanceStarted) {
                try {
                    Artisan::call('up');
                } catch (Throwable $maintenanceException) {
                    report($maintenanceException);
                }
            }
        }
    }

    /**
     * Execute a SQL file incrementally. DDL is not transactional, so this is
     * paired with the pre-restore safety backup and recovery path above.
     */
    private function executeRestoreFile(string $path): int
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('The SQL backup could not be opened.');
        }

        $executed = 0;

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($this->streamStatements($handle) as $statement) {
                $normalized = trim($statement);
                if ($normalized === '' || preg_match('/^(DELIMITER|LOCK TABLES|UNLOCK TABLES)\b/i', $normalized)) {
                    continue;
                }

                $normalized = $this->makePortableStatement($normalized);
                if (trim($normalized) === '') {
                    continue;
                }

                foreach ($this->splitLargeInsert($normalized) as $insertStatement) {
                    DB::unprepared($insertStatement);
                    $executed++;
                }
            }
        } finally {
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } finally {
                fclose($handle);
            }
        }

        if ($executed === 0) {
            throw new \RuntimeException('No executable SQL statements were found in the backup.');
        }

        return $executed;
    }

    /**
     * Yield SQL statements without loading the complete upload into memory.
     * This supports ordinary phpMyAdmin/MySQL dumps and preserves semicolons
     * inside quoted values.
     */
    private function streamStatements($handle): \Generator
    {
        $buffer = '';
        $single = false;
        $double = false;
        $backtick = false;
        $lineComment = false;
        $blockComment = false;

        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^\s*DELIMITER\s+/i', $line)) {
                continue;
            }

            $length = strlen($line);
            for ($i = 0; $i < $length; $i++) {
                $char = $line[$i];
                $next = $i + 1 < $length ? $line[$i + 1] : '';

                if ($lineComment) {
                    if ($char === "\n") {
                        $lineComment = false;
                    }
                    continue;
                }

                if ($blockComment) {
                    if ($char === '*' && $next === '/') {
                        $blockComment = false;
                        $i++;
                    }
                    continue;
                }

                if (! $single && ! $double && ! $backtick) {
                    if ($char === '#' || ($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($line[$i + 2])))) {
                        $lineComment = true;
                        if ($char === '-') {
                            $i++;
                        }
                        continue;
                    }

                    if ($char === '/' && $next === '*') {
                        $blockComment = true;
                        $i++;
                        continue;
                    }
                }

                if ($char === "'" && ! $double && ! $backtick) {
                    if ($single && $next === "'") {
                        $buffer .= $char . $next;
                        $i++;
                        continue;
                    }
                    $single = ! $single;
                } elseif ($char === '"' && ! $single && ! $backtick) {
                    if ($double && $next === '"') {
                        $buffer .= $char . $next;
                        $i++;
                        continue;
                    }
                    $double = ! $double;
                } elseif ($char === '`' && ! $single && ! $double) {
                    $backtick = ! $backtick;
                } elseif ($char === '\\' && ($single || $double) && $next !== '') {
                    $buffer .= $char . $next;
                    $i++;
                    continue;
                }

                if ($char === ';' && ! $single && ! $double && ! $backtick) {
                    if (trim($buffer) !== '') {
                        yield trim($buffer);
                    }
                    $buffer = '';
                    continue;
                }

                $buffer .= $char;
            }
        }

        if (trim($buffer) !== '') {
            yield trim($buffer);
        }
    }

    private function ensureSupportedDriver(): void
    {
        abort_unless(in_array(DB::connection()->getDriverName(), self::SUPPORTED_DRIVERS, true), 422, 'Database backup and restore require a MySQL or MariaDB connection.');
    }

    private function backupTableNames(): array
    {
        $tables = [];

        foreach (DB::select('SHOW FULL TABLES') as $row) {
            $values = array_values((array) $row);
            $name = (string) ($values[0] ?? '');
            $type = strtoupper((string) ($values[1] ?? 'BASE TABLE'));
            // A database backup must be complete. Include every base table,
            // including sessions; restoring a backup may invalidate old sessions
            // but silently omitting authentication state is worse.
            if ($name !== '' && $type === 'BASE TABLE') {
                $tables[] = $name;
            }
        }

        return $this->orderTablesByDependencies(array_values(array_unique($tables)));
    }

    private function buildDump(): string
    {
        $pdo = DB::connection()->getPdo();
        $tables = $this->backupTableNames();

        $sql = "-- PMAMS MySQL/MariaDB database backup\n";
        $sql .= '-- Generated: ' . now()->toDateTimeString() . "\n";
        $sql .= "-- Import this file into the target database using phpMyAdmin or the MySQL client.\n\n";
        $sql .= "SET NAMES utf8mb4;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\nSET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $identifier = $this->quoteIdentifier($table);
            $createRow = DB::selectOne('SHOW CREATE TABLE ' . $identifier);
            $createValues = array_values((array) $createRow);
            $createStatement = (string) end($createValues);

            if ($createStatement === '') {
                continue;
            }

            // MariaDB versions bundled with older XAMPP releases can reject
            // Laravel's automatically generated JSON_VALID check constraint.
            // JSON data remains intact; the application validates it when it
            // writes the value, so the optional server-side check is safe to
            // omit from a portable dump.
            $createStatement = $this->makePortableCreateStatement($createStatement);

            $sql .= 'DROP TABLE IF EXISTS ' . $identifier . ";\n";
            $sql .= $createStatement . ";\n\n";

            $columns = collect(DB::select('SHOW COLUMNS FROM ' . $identifier))
                ->map(fn ($column) => (string) data_get((array) $column, 'Field'))
                ->filter()
                ->values()
                ->all();

            if ($columns === []) {
                continue;
            }

            $columnSql = implode(', ', array_map(fn ($column) => $this->quoteIdentifier($column), $columns));
            $insertPrefix = 'INSERT INTO ' . $identifier . ' (' . $columnSql . ") VALUES\n";
            // Query the table directly rather than through an Eloquent model, so
            // SoftDeletes rows (including deleted Locations and PM Plans) are
            // preserved. A cursor avoids loading a large table into memory and
            // visits every row while each INSERT remains packet-safe.
            $rows = [];
            $statementBytes = strlen($insertPrefix);
            foreach (DB::table($table)->select($columns)->cursor() as $record) {
                $values = [];
                foreach ($columns as $column) {
                    $values[] = $this->quoteValue($pdo, data_get((array) $record, $column));
                }
                $row = '(' . implode(', ', $values) . ')';
                $rowBytes = strlen($row) + 2;

                if ($rows !== [] && ($statementBytes + $rowBytes) > self::MAX_INSERT_BYTES) {
                    $sql .= $insertPrefix . implode(",\n", $rows) . ";\n\n";
                    $rows = [];
                    $statementBytes = strlen($insertPrefix);
                }

                $rows[] = $row;
                $statementBytes += $rowBytes;
            }

            if ($rows !== []) {
                $sql .= $insertPrefix . implode(",\n", $rows) . ";\n\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        return $sql;
    }

    /**
     * Ensure referenced tables are created before tables containing foreign
     * keys. InnoDB still requires the parent table to exist even when
     * FOREIGN_KEY_CHECKS is disabled.
     */
    private function orderTablesByDependencies(array $tables): array
    {
        $lookup = [];
        foreach ($tables as $table) {
            $lookup[strtolower($table)] = $table;
        }

        $dependencies = array_fill_keys($tables, []);
        $rows = DB::select(
            'SELECT TABLE_NAME, REFERENCED_TABLE_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND REFERENCED_TABLE_NAME IS NOT NULL'
        );

        foreach ($rows as $row) {
            $child = $lookup[strtolower((string) ($row->TABLE_NAME ?? ''))] ?? null;
            $parent = $lookup[strtolower((string) ($row->REFERENCED_TABLE_NAME ?? ''))] ?? null;

            if ($child !== null && $parent !== null && $child !== $parent) {
                $dependencies[$child][] = $parent;
            }
        }

        foreach ($dependencies as $table => $parents) {
            $dependencies[$table] = array_values(array_unique($parents));
        }

        $ordered = [];
        $remaining = $tables;
        while ($remaining !== []) {
            $ready = array_values(array_filter(
                $remaining,
                fn (string $table): bool => array_intersect($dependencies[$table] ?? [], $remaining) === []
            ));

            // Cyclic foreign keys cannot be topologically sorted. Keeping the
            // remaining order still allows MySQL to process them with checks
            // disabled after all non-cyclic parents have been created.
            if ($ready === []) {
                return array_merge($ordered, $remaining);
            }

            $ordered = array_merge($ordered, $ready);
            $remaining = array_values(array_diff($remaining, $ready));
        }

        return $ordered;
    }

    private function makePortableCreateStatement(string $createStatement): string
    {
        $open = strpos($createStatement, '(');
        if ($open === false) {
            return $createStatement;
        }

        $close = $this->matchingParenthesis($createStatement, $open);
        if ($close === null) {
            return $createStatement;
        }

        $body = substr($createStatement, $open + 1, $close - $open - 1);
        $portableClauses = [];
        foreach ($this->splitTopLevelList($body) as $clause) {
            if (preg_match('/^\s*(?:CONSTRAINT\s+[^\s]+\s+)?CHECK\b/i', $clause)) {
                continue;
            }
            $portableClauses[] = $this->removeInlineCheck($clause);
        }

        return substr($createStatement, 0, $open + 1)
            . implode(",\n", $portableClauses)
            . substr($createStatement, $close);
    }

    private function makePortableStatement(string $statement): string
    {
        if (preg_match('/^\s*CREATE\s+TABLE\b/i', $statement)) {
            return $this->makePortableCreateStatement($statement);
        }

        return preg_match('/\bCHECK\s*\(/i', $statement)
            ? $this->removeInlineCheck($statement)
            : $statement;
    }

    private function splitLargeInsert(string $statement): array
    {
        if (! preg_match('/^(INSERT(?:\s+IGNORE)?\s+INTO\s+.+?\s+VALUES\s+)(.+)$/is', $statement, $matches)) {
            return [$statement];
        }

        $prefix = $matches[1];
        $rows = $this->splitTopLevelList(trim($matches[2]));
        if (count($rows) < 2) {
            return [$statement];
        }

        $statements = [];
        $chunk = [];
        $bytes = strlen($prefix);

        foreach ($rows as $row) {
            $rowBytes = strlen($row) + 2;
            if ($chunk !== [] && ($bytes + $rowBytes) > self::MAX_INSERT_BYTES) {
                $statements[] = $prefix . implode(",\n", $chunk);
                $chunk = [];
                $bytes = strlen($prefix);
            }

            $chunk[] = $row;
            $bytes += $rowBytes;
        }

        if ($chunk !== []) {
            $statements[] = $prefix . implode(",\n", $chunk);
        }

        return $statements ?: [$statement];
    }

    private function removeInlineCheck(string $statement): string
    {
        $checkPosition = $this->findTokenOutsideQuotes($statement, 'CHECK');
        if ($checkPosition === null) {
            return $statement;
        }

        $open = strpos($statement, '(', $checkPosition);
        if ($open === false) {
            return $statement;
        }

        $close = $this->matchingParenthesis($statement, $open);
        if ($close === null) {
            return $statement;
        }

        $prefix = rtrim(substr($statement, 0, $checkPosition));
        $suffix = ltrim(substr($statement, $close + 1));
        $suffix = preg_replace('/^(?:NOT\s+ENFORCED|ENFORCED)\b\s*/i', '', $suffix) ?? $suffix;

        if (preg_match('/\b(?:ADD\s+)?CONSTRAINT\b/i', $prefix)) {
            return '';
        }

        return $prefix . ($suffix !== '' ? ' ' . $suffix : '');
    }

    private function findTokenOutsideQuotes(string $sql, string $token): ?int
    {
        $length = strlen($sql);
        $tokenLength = strlen($token);
        $single = false;
        $double = false;
        $backtick = false;

        for ($i = 0; $i <= $length - $tokenLength; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($char === '\\' && ($single || $double)) {
                $i++;
                continue;
            }
            if ($char === "'" && ! $double && ! $backtick) {
                $single = ! $single;
                continue;
            }
            if ($char === '"' && ! $single && ! $backtick) {
                $double = ! $double;
                continue;
            }
            if (ord($char) === 96 && ! $single && ! $double) {
                $backtick = ! $backtick;
                continue;
            }
            if ($single || $double || $backtick) {
                continue;
            }

            if (strtoupper(substr($sql, $i, $tokenLength)) === $token
                && ($i === 0 || ! preg_match('/[A-Za-z0-9_]/', $sql[$i - 1]))
                && ($i + $tokenLength >= $length || ! preg_match('/[A-Za-z0-9_]/', $sql[$i + $tokenLength]))) {
                return $i;
            }
        }

        return null;
    }

    private function matchingParenthesis(string $sql, int $open): ?int
    {
        $depth = 0;
        $length = strlen($sql);
        $single = false;
        $double = false;
        $backtick = false;

        for ($i = $open; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($char === '\\' && ($single || $double)) {
                $i++;
                continue;
            }
            if ($char === "'" && ! $double && ! $backtick) {
                $single = ! $single;
                continue;
            }
            if ($char === '"' && ! $single && ! $backtick) {
                $double = ! $double;
                continue;
            }
            if (ord($char) === 96 && ! $single && ! $double) {
                $backtick = ! $backtick;
                continue;
            }
            if ($single || $double || $backtick) {
                continue;
            }

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private function splitTopLevelList(string $sql): array
    {
        $items = [];
        $buffer = '';
        $depth = 0;
        $length = strlen($sql);
        $single = false;
        $double = false;
        $backtick = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($char === '\\' && ($single || $double)) {
                $buffer .= $char . $next;
                $i++;
                continue;
            }
            if ($char === "'" && ! $double && ! $backtick) {
                $single = ! $single;
            } elseif ($char === '"' && ! $single && ! $backtick) {
                $double = ! $double;
            } elseif (ord($char) === 96 && ! $single && ! $double) {
                $backtick = ! $backtick;
            } elseif (! $single && ! $double && ! $backtick && $char === '(') {
                $depth++;
            } elseif (! $single && ! $double && ! $backtick && $char === ')') {
                $depth--;
            }

            if ($char === ',' && ! $single && ! $double && ! $backtick && $depth === 0) {
                if (trim($buffer) !== '') {
                    $items[] = trim($buffer);
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $items[] = trim($buffer);
        }

        return $items;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function quoteValue($pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        $quoted = $pdo->quote((string) $value);

        return $quoted === false ? "''" : $quoted;
    }

    /**
     * Split SQL on semicolons while preserving semicolons inside quoted values.
     * This accepts ordinary phpMyAdmin/MySQL dumps without requiring a shell
     * mysqldump binary, which keeps it usable on XAMPP installations.
     */
    private function splitStatements(string $sql): array
    {
        $sql = preg_replace('/^\s*DELIMITER\s+.+$/mi', '', $sql) ?? $sql;
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $single = false;
        $double = false;
        $backtick = false;
        $lineComment = false;
        $blockComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($lineComment) {
                if ($char === "\n") {
                    $lineComment = false;
                    $buffer .= $char;
                }
                continue;
            }

            if ($blockComment) {
                if ($char === '*' && $next === '/') {
                    $blockComment = false;
                    $i++;
                }
                continue;
            }

            if (! $single && ! $double && ! $backtick) {
                if ($char === '#' || ($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2])))) {
                    $lineComment = true;
                    if ($char === '-') {
                        $i++;
                    }
                    continue;
                }

                if ($char === '/' && $next === '*') {
                    $blockComment = true;
                    $i++;
                    continue;
                }
            }

            if ($char === "'" && ! $double && ! $backtick) {
                if ($single && $next === "'") {
                    $buffer .= $char . $next;
                    $i++;
                    continue;
                }
                $single = ! $single;
            } elseif ($char === '"' && ! $single && ! $backtick) {
                if ($double && $next === '"') {
                    $buffer .= $char . $next;
                    $i++;
                    continue;
                }
                $double = ! $double;
            } elseif ($char === '`' && ! $single && ! $double) {
                $backtick = ! $backtick;
            } elseif ($char === '\\' && ($single || $double) && $next !== '') {
                $buffer .= $char . $next;
                $i++;
                continue;
            }

            if ($char === ';' && ! $single && ! $double && ! $backtick) {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return $statements;
    }
}
