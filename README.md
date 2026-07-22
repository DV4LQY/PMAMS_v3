# PMAMS 3.0

PMAMS (Preventive Maintenance and Asset Monitoring System) is a Laravel-based ICT equipment management system. It helps an organization register equipment, track conditions and locations, issue assets to staff, maintain equipment history, and generate operational reports.

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

## License

The application declares the MIT license in `composer.json`. Add a `LICENSE` file before publishing if your GitHub repository requires an explicit license document.
