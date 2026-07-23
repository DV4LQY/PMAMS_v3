<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseBackupController extends Controller
{
    private const SUPPORTED_DRIVERS = ['mysql', 'mariadb'];
    // Keep each INSERT comfortably below XAMPP's default max_allowed_packet.
    private const MAX_INSERT_BYTES = 262144;

    public function index(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        return view('admin.database.index', [
            'driver' => DB::connection()->getDriverName(),
            'database' => DB::getDatabaseName(),
        ]);
    }

    public function download(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
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

    public function restore(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $this->ensureSupportedDriver();

        $request->validate([
            'backup' => ['required', 'file', 'max:102400'],
        ]);

        $file = $request->file('backup');
        $extension = strtolower((string) $file?->getClientOriginalExtension());
        if (! in_array($extension, ['sql', 'txt'], true)) {
            return back()->withErrors(['backup' => 'Only .sql or .txt SQL backup files are allowed.']);
        }

        $sql = @file_get_contents($file->getRealPath());
        if (! is_string($sql) || trim($sql) === '') {
            return back()->withErrors(['backup' => 'The selected SQL backup is empty or could not be read.']);
        }

        $statements = $this->splitStatements($sql);
        if ($statements === []) {
            return back()->withErrors(['backup' => 'No executable SQL statements were found in the backup.']);
        }

        $executed = 0;
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($statements as $statement) {
                $normalized = trim($statement);
                if ($normalized === '' || preg_match('/^(DELIMITER|LOCK TABLES|UNLOCK TABLES)\b/i', $normalized)) {
                    continue;
                }

                DB::unprepared($normalized);
                $executed++;
            }
        } catch (Throwable $exception) {
            report($exception);

            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } catch (Throwable) {
                // Preserve the sanitized restore error if cleanup also fails.
            }

            return back()->withErrors([
                'backup' => 'The SQL restore failed. The database may be partially restored; verify it before continuing.',
            ]);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        ActivityLog::record('restored', 'Restored a database backup SQL file.', null, [
            'statements' => $executed,
            'filename' => $file->getClientOriginalName(),
        ]);

        return back()->with('success', "Database restore completed ({$executed} SQL statements processed). Review the application before allowing normal users back in.");
    }

    private function ensureSupportedDriver(): void
    {
        abort_unless(in_array(DB::connection()->getDriverName(), self::SUPPORTED_DRIVERS, true), 422, 'Database backup and restore require a MySQL or MariaDB connection.');
    }

    private function buildDump(): string
    {
        $pdo = DB::connection()->getPdo();
        $tables = [];

        foreach (DB::select('SHOW FULL TABLES') as $row) {
            $values = array_values((array) $row);
            $name = (string) ($values[0] ?? '');
            $type = strtoupper((string) ($values[1] ?? 'BASE TABLE'));
            // Sessions are transient and are deliberately excluded so importing
            // a PMAMS backup does not log out the Super Admin who started it.
            if ($name !== '' && strtolower($name) !== 'sessions' && $type === 'BASE TABLE') {
                $tables[] = $name;
            }
        }

        $tables = $this->orderTablesByDependencies(array_values(array_unique($tables)));

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
            foreach (DB::table($table)->select($columns)->get()->chunk(500) as $chunk) {
                $rows = [];
                $statementBytes = strlen($insertPrefix);
                foreach ($chunk as $record) {
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
        $portable = preg_replace(
            '/,\s*(?:CONSTRAINT\s+`?[^`\s]+`?\s+)?CHECK\s*\(\s*JSON_VALID\s*\([^)]*\)\s*\)/is',
            '',
            $createStatement
        );

        return is_string($portable) ? $portable : $createStatement;
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
