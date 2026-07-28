#requires -RunAsAdministrator

[CmdletBinding()]
param([string]$TaskName = 'PMAMS Laravel Scheduler')

$ErrorActionPreference = 'Stop'

Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction SilentlyContinue
Write-Host "Removed scheduled task '$TaskName'." -ForegroundColor Yellow
