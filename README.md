# PMAMS

PMAMS (Preventive Maintenance and Asset Monitoring System) is a Laravel-based ICT equipment management system. It helps an organization register equipment, track conditions and locations, issue assets to staff, maintain equipment history, and generate operational reports.

## Screenshots

The screenshots below are stored in the repository so they render correctly on GitHub. They cover the dashboard/workflow overview, inventory filters, preventive-maintenance plans, reports, and mobile navigation.

### Dashboard and workflow overview

![PMAMS dashboard and workflow overview](manual_assets/contact-sheet.png)

### Equipment inventory and filters

![PMAMS equipment inventory filters](manual_assets/equipment-filters.png)

### Preventive maintenance plan

![PMAMS preventive maintenance plan](manual_assets/pm-plan-card.png)

### Maintenance attention report

![PMAMS maintenance attention report](manual_assets/maintenance-attention-report.png)

### Mobile navigation

![PMAMS mobile navigation](manual_assets/mobile-menu.png)

> To replace a screenshot, keep the new image in `manual_assets/` and update the relative Markdown link above. Do not use local `C:\...` paths; GitHub cannot render them.

## Features

- Equipment inventory with property numbers, serial numbers, specifications, condition, status, and maintenance remarks
- Staff, office, and location directory
- Equipment issuance, return, relocation, and issuance history
- Dashboard cards and charts for availability, condition, type, and office summaries
- QR code generation and browser-based QR scanning
- Bulk equipment deletion with related issuance and maintenance history cleanup
- CSV/XLS/XLSX inventory and issuance import with office/location-aware staff matching
- Excel and PDF reporting
- Activity logging for equipment and organization changes
- Responsive mobile sidebar and SPA-style page navigation powered by Livewire
- Dark mode and synchronized support contacts on the login and authenticated support pages
- Local maintenance-attention recommendations that work offline and explain why equipment is prioritized, available from the dashboard summary and paginated sidebar page

## Technology stack

- PHP 8.2+
- Laravel 12
- Livewire 4
- MySQL (SQLite can be used for automated tests)
- Tailwind CSS 4 and Vite
- Chart.js
- Maatwebsite Excel
- Simple Software QR Code
- Dompdf

## Requirements

Install the following before setting up the project:

- PHP 8.2 or newer with required Laravel extensions
- Composer
- Node.js and npm
- MySQL 8+ or MariaDB
- A web server such as Laravel's built-in server, Laragon, Apache, or Nginx

## Installation

Clone the repository and enter the project directory:

```bash
git clone <repository-url> pms_systemv2
cd pms_systemv2
```

Install PHP and frontend dependencies:

```bash
composer install
npm install
```

Create the environment file and application key:

```bash
cp .env.example .env
php artisan key:generate
```

Configure the database values in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pms_system
DB_USERNAME=root
DB_PASSWORD=
```

Create the database, run migrations, seed the equipment types, and build frontend assets:

```bash
php artisan migrate --seed
npm run build
```

For local development, start Laravel and Vite in separate terminals:

```bash
php artisan serve
npm run dev
```

The application is then available at `http://127.0.0.1:8000`.

### Laragon

Place the project in `C:\laragon\www\pms_systemv2`, start Apache and MySQL, and open:

```text
http://localhost/pms_systemv2/public
```

Alternatively, use `php artisan serve` from the project directory.

### Offline XAMPP deployment

The dashboard maintenance-attention recommendations are calculated locally from the PMAMS database; they do not require an internet connection, an API key, or a separate AI service. Copy the project to `C:\xampp\htdocs\pms_systemv2`, start Apache and MySQL, configure `.env`, and open:

```text
http://localhost/pms_systemv2/public
```

Install the PHP dependencies before serving the application and build the production assets once:

```bat
cd C:\xampp\htdocs\pms_systemv2
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm install
npm run build
```

The **Maintenance attention** dashboard summary links to a dedicated paginated page. The page is lazy-loaded: choose a filter (the dropdowns submit automatically) or press **Reset** before recommendations are queried. The recommendations are advisory: they score existing equipment using condition/status, recent checklist issues, equipment age, maintenance recency, repeated maintenance, transfers, RAM/OS compatibility, and license status. The page can be filtered by location, RAM-upgrade attention, cracked OS/MS Office licensing, or equipment at least five years old. Windows 10/11 systems with 8 GB or less are advised to upgrade to at least 16 GB; Windows 7/8 systems with 4 GB or less are advised to upgrade to at least 8 GB. Cracked OS or Microsoft Office entries recommend procuring genuine software. Condemned equipment is excluded. Review the reasons and use the existing equipment/checklist actions for the final decision; those actions continue to be recorded by the normal activity-history workflow.

The score is intentionally explainable: unserviceable/repair signals carry the greatest weight, followed by overdue or missing maintenance dates, repeated recent issues, equipment age, and repeated transfers. Scores are shown as Low, Medium, High, or Critical with the contributing reasons, so a supervisor can verify the recommendation without trusting an opaque model.

#### Future local-model extension (ONNX)

The current scorer is the safe baseline until PMAMS has enough historical outcomes. Later, checklist history can be exported for a Python training job, labelled with the eventual outcome (for example, repair, upgrade, or no action), and exported to ONNX. The ONNX model can then be deployed under `storage/app/private/models/` and called by a local adapter while retaining `MaintenanceAttentionService` as the fallback. This keeps XAMPP deployments offline and preserves the current dashboard contract if the model is unavailable.

## User roles

The application supports three roles:

- **Admin** - full system administration, organization management, user management, reports, imports, and deletion
- **Unit Head** - administrative equipment and reporting access
- **Custodian** - equipment, issuance, directory browsing, and operational workflows without administrative deletion or user management

Create or manage accounts through the user-management screen or an application-specific seeder. Do not commit real credentials to the repository.

## Equipment import

The Equipment page provides an **Import Inventory** action with two modes:

1. **Inventory records** - add new equipment or update an existing record using `property_number`.
2. **Issuance records** - issue existing equipment to an existing staff member using `property_number` or `serial_number`.

The import dialog includes a downloadable CSV template. Staff can be matched by email or name, with optional `office` and `location_code` columns to disambiguate users in detailed locations. Inventory rows can also include staff and location fields to create an issuance during import.

## Useful commands

```bash
php artisan migrate
php artisan db:seed
php artisan route:list
php artisan view:cache
php artisan test
npm run build
```

Clear cached application state when changing configuration or routes:

```bash
php artisan optimize:clear
```

## Windows/XAMPP scheduler startup

Automatic monthly backups require Laravel's scheduler to remain running. On a
Windows/XAMPP server, register the deployable startup task from an elevated
PowerShell window:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\deployment\windows\install-scheduler-task.ps1 -PhpPath C:\xampp\php\php.exe
Start-ScheduledTask -TaskName 'PMAMS Laravel Scheduler'
```

The task starts `php artisan schedule:work` when Windows starts, restarts it if
it exits, and uses the project directory as its working directory. See
[`deployment/windows/README.md`](deployment/windows/README.md) for XAMPP paths,
manual startup, logs, and removal instructions.

## Project structure

```text
app/                 Controllers, models, imports, exports, and Livewire components
database/migrations/ Database schema
database/seeders/    Initial equipment type and application seeders
resources/js/        SPA navigation, sidebar state, scanner, and chart initialization
resources/css/       Tailwind application styles
resources/views/     Authentication, admin pages, reports, and shared components
routes/web.php       Public and authenticated web routes
public/              Public assets and compiled Vite output
android-app/         Android WebView client with ICTU branding
```

## Security notes

- Keep `.env` and production credentials out of version control.
- Use HTTPS in production.
- Change database and application credentials before deployment.
- Review role permissions before creating production users.
- Validate and review imported spreadsheets before applying them to live inventory.

## Android app

The `android-app` folder contains a PMAMS Android WebView client that uses the ICTU logo and supports the existing web workflows, camera input, QR scanning, and file downloads. See [`android-app/README.md`](android-app/README.md) for emulator, physical-device, and APK build instructions.

## GitHub publishing checklist

- Commit `README.md` together with the referenced files in `manual_assets/` so the screenshots render on GitHub.
- Never commit `.env`, production credentials, uploaded photos, generated backups, or private model files.
- Configure the production `.env` on the server, run `composer install --no-dev --optimize-autoloader`, build the Vite assets, and cache configuration/routes/views.
- Point the web server document root to the project’s `public/` directory and enable HTTPS before opening the application to users.

## License

The application declares the MIT license in `composer.json`. Add a `LICENSE` file before publishing if your GitHub repository requires an explicit license document.
