#requires -RunAsAdministrator

[CmdletBinding()]
param(
    [string]$ProjectRoot = '',
    [string]$PhpPath = '',
    [string]$TaskName = 'PMAMS Laravel Scheduler'
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
    if ($phpCommand) {
        $PhpPath = $phpCommand.Source
    }
}

if ([string]::IsNullOrWhiteSpace($PhpPath) -or -not (Test-Path $PhpPath)) {
    throw 'PHP was not found. Re-run with -PhpPath C:\xampp\php\php.exe or set PHP_BIN.'
}

$artisan = Join-Path $ProjectRoot 'artisan'
if (-not (Test-Path $artisan)) {
    throw "Laravel artisan was not found at $artisan."
}

$logDirectory = Join-Path $ProjectRoot 'storage\logs'
New-Item -ItemType Directory -Force -Path $logDirectory | Out-Null

$action = New-ScheduledTaskAction `
    -Execute $PhpPath `
    -Argument ('"{0}" schedule:work --no-interaction' -f $artisan) `
    -WorkingDirectory $ProjectRoot
$trigger = New-ScheduledTaskTrigger -AtStartup -RandomDelay (New-TimeSpan -Minutes 1)
$settings = New-ScheduledTaskSettingsSet `
    -StartWhenAvailable `
    -RestartCount 10 `
    -RestartInterval (New-TimeSpan -Minutes 1) `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries
$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest

Register-ScheduledTask `
    -TaskName $TaskName `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Principal $principal `
    -Description 'Keeps the PMAMS Laravel scheduler running for automatic monthly database backups.' `
    -Force | Out-Null

Write-Host "Registered '$TaskName' to start with Windows." -ForegroundColor Green
Write-Host "PHP: $PhpPath"
Write-Host "Project: $ProjectRoot"
Write-Host "Run now with: Start-ScheduledTask -TaskName '$TaskName'"
