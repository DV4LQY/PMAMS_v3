@echo off
setlocal EnableExtensions

rem PMAMS Laravel scheduler runner for Windows/XAMPP.
rem The script keeps schedule:work alive and restarts it after an unexpected exit.

set "PROJECT_ROOT=%~dp0..\.."
for %%I in ("%PROJECT_ROOT%") do set "PROJECT_ROOT=%%~fI"

if not defined PHP_BIN set "PHP_BIN=%PHP_PATH%"
if not defined PHP_BIN if exist "C:\xampp\php\php.exe" set "PHP_BIN=C:\xampp\php\php.exe"
if not defined PHP_BIN for /f "delims=" %%P in ('dir /b /s "C:\laragon\bin\php\php.exe" 2^>nul') do if not defined PHP_BIN set "PHP_BIN=%%P"
if not defined PHP_BIN for /f "delims=" %%P in ('where php 2^>nul') do if not defined PHP_BIN set "PHP_BIN=%%P"

if not defined PHP_BIN (
    echo [PMAMS] PHP was not found. Set PHP_BIN or PHP_PATH to the full php.exe path.
    exit /b 1
)

if not exist "%PHP_BIN%" (
    echo [PMAMS] PHP executable was not found at "%PHP_BIN%".
    exit /b 1
)

if not exist "%PROJECT_ROOT%\artisan" (
    echo [PMAMS] Laravel artisan file was not found at "%PROJECT_ROOT%\artisan".
    exit /b 1
)

if not exist "%PROJECT_ROOT%\storage\logs" mkdir "%PROJECT_ROOT%\storage\logs" >nul 2>&1
cd /d "%PROJECT_ROOT%"

:run
echo [%date% %time%] Starting Laravel scheduler.>>"%PROJECT_ROOT%\storage\logs\scheduler-startup.log"
"%PHP_BIN%" artisan schedule:work --no-interaction >>"%PROJECT_ROOT%\storage\logs\scheduler-startup.log" 2>&1
set "EXIT_CODE=%ERRORLEVEL%"
echo [%date% %time%] Scheduler exited with code %EXIT_CODE%; restarting in 10 seconds.>>"%PROJECT_ROOT%\storage\logs\scheduler-startup.log"
timeout /t 10 /nobreak >nul
goto run
