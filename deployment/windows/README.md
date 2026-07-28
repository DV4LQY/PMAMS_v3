# Windows/XAMPP scheduler deployment

PMAMS uses Laravel's scheduler for automatic monthly database backups. The
recommended deployment is a Windows Scheduled Task that starts
`php artisan schedule:work` when Windows starts and restarts it if it exits.

## Install on an XAMPP server

Open **PowerShell as Administrator**, then run this from the PMAMS project
folder:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\deployment\windows\install-scheduler-task.ps1 -PhpPath C:\xampp\php\php.exe
Start-ScheduledTask -TaskName 'PMAMS Laravel Scheduler'
```

The installer resolves the project root automatically. If XAMPP is installed
elsewhere, pass its PHP path explicitly, for example:

```powershell
.\deployment\windows\install-scheduler-task.ps1 `
  -ProjectRoot C:\xampp\htdocs\pms_systemv2 `
  -PhpPath C:\xampp\php\php.exe
```

The task runs as the Windows `SYSTEM` account, starts at boot (with a short
startup delay so XAMPP/MySQL can initialize), and is set to restart after an
unexpected exit. Check **Task Scheduler → Task Scheduler
Library → PMAMS Laravel Scheduler** to confirm it is running.

## Manual foreground runner

For a logged-in development server, double-click
`start-laravel-scheduler.cmd`. It keeps `schedule:work` running and writes
restart output to `storage/logs/scheduler-startup.log`.

## Remove the task

Run PowerShell as Administrator:

```powershell
.\deployment\windows\uninstall-scheduler-task.ps1
```

Do not register both the startup runner and the Scheduled Task on the same
server; use one scheduler process to avoid duplicate backup attempts.
