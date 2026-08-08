#requires -Version 5.1
#requires -RunAsAdministrator

[CmdletBinding()]
param(
    [string]$ProjectRoot = '',
    [string]$PhpPath = '',
    [string]$TaskName = 'PMAMS Laravel Scheduler',
    [int]$RetryCount = 4,
    [int]$RetryDelaySeconds = 2
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($ProjectRoot)) {
    $ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
} else {
    $ProjectRoot = (Resolve-Path $ProjectRoot).Path
}

if ([string]::IsNullOrWhiteSpace($PhpPath)) {
    $PhpPath = $env:PHP_BIN
}
if ([string]::IsNullOrWhiteSpace($PhpPath) -and (Test-Path 'C:\xampp\php\php.exe')) {
    $PhpPath = 'C:\xampp\php\php.exe'
}
if ([string]::IsNullOrWhiteSpace($PhpPath) -and (Test-Path 'C:\laragon\bin\php')) {
    $PhpPath = Get-ChildItem 'C:\laragon\bin\php' -Filter 'php.exe' -Recurse -File |
        Sort-Object FullName -Descending |
        Select-Object -First 1 -ExpandProperty FullName
}
if ([string]::IsNullOrWhiteSpace($PhpPath)) {
    $phpCommand = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($phpCommand) { $PhpPath = $phpCommand.Source }
}

if ([string]::IsNullOrWhiteSpace($PhpPath) -or -not (Test-Path $PhpPath)) {
    throw 'PHP was not found. Re-run with -PhpPath C:\xampp\php\php.exe or set PHP_BIN.'
}

$artisan = Join-Path $ProjectRoot 'artisan'
if (-not (Test-Path $artisan)) {
    throw "Laravel artisan was not found at $artisan."
}

# A running scheduler can hold a bootstrap cache file open on Windows. Stop
# only the PMAMS task (if it exists), clear caches, then start it again.
$task = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
$taskWasRunning = $false
if ($task) {
    $taskWasRunning = $task.State -eq 'Running'
    if ($taskWasRunning) {
        Write-Host "Stopping '$TaskName' while clearing caches..."
        Stop-ScheduledTask -TaskName $TaskName
        Start-Sleep -Seconds 2
    }
}

try {
    $commands = @('config:clear', 'cache:clear', 'route:clear', 'view:clear', 'event:clear')
    foreach ($command in $commands) {
        $succeeded = $false
        for ($attempt = 1; $attempt -le [Math]::Max(1, $RetryCount); $attempt++) {
            Write-Host "Running php artisan $command (attempt $attempt)..."
            & $PhpPath $artisan $command '--no-ansi' '--no-interaction'
            if ($LASTEXITCODE -eq 0) {
                $succeeded = $true
                break
            }
            if ($attempt -lt $RetryCount) {
                Start-Sleep -Seconds ([Math]::Max(1, $RetryDelaySeconds))
            }
        }
        if (-not $succeeded) {
            throw "Failed to clear the $command cache. Stop the web server/PHP worker that owns bootstrap/cache and run this script again."
        }
    }

    # Rebuild only after all stale caches are cleared. If Windows still has a
    # file lock, report the exact recovery action instead of leaving a partial
    # optimize command failure unexplained.
    foreach ($command in @('config:cache', 'route:cache', 'event:cache', 'view:cache')) {
        & $PhpPath $artisan $command '--no-ansi' '--no-interaction'
        if ($LASTEXITCODE -ne 0) {
            throw "Failed to rebuild the $command cache. Stop the web server/PHP worker and run this script again."
        }
    }

    Write-Host 'PMAMS Laravel caches cleared and rebuilt successfully.' -ForegroundColor Green
} finally {
    if ($taskWasRunning) {
        Start-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
        Write-Host "Restarted '$TaskName'."
    }
}
