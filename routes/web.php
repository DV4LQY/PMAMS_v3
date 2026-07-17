<?php

use App\Http\Controllers\Admin\ChangePasswordController;
use App\Http\Controllers\Admin\DeviceChecklistController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\OfficeController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StaffDeviceController;
use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\IssuanceController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\ReportController;


/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Forgot Password Routes
|--------------------------------------------------------------------------
*/

Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])
    ->name('password.request');

Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])
    ->name('password.email');

/*
|--------------------------------------------------------------------------
| Reset Password Routes
|--------------------------------------------------------------------------
*/

Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])
    ->name('password.reset');

Route::post('/reset-password', [ResetPasswordController::class, 'reset'])
    ->name('password.update');

/*
|--------------------------------------------------------------------------
| Protected Routes — shared by admin, custodian, and unit_head
|--------------------------------------------------------------------------
| Devices, issuing/returning devices, reports, dashboard, scanner, and
| browsing the location/office/staff directory are everyday tasks for these
| roles. Org-structure changes (creating/editing/deleting locations,
| offices, staff) and user management are admin-only — see the nested
| 'role:admin' group further below.
*/

Route::middleware(['auth', 'role:admin,custodian'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Admin Pages
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::view('/org-browser', 'admin.org-browser')->name('admin.org-browser');
        Route::view('/scanner', 'admin.scanner')->name('admin.scanner');
        Route::view('/support', 'admin.support')->name('admin.support');
        Route::get('/issuance', fn (\Illuminate\Http\Request $request) => redirect()->route('admin.reports.issuance', $request->query()))
            ->name('admin.issuance.index');
        Route::get('/issuance/export', fn (\Illuminate\Http\Request $request) => redirect()->route('admin.reports.issuance.export', $request->query()))
            ->name('admin.issuance.export');
        Route::get('/change-password', [ChangePasswordController::class, 'edit'])
            ->name('admin.change-password');

        Route::put('/change-password', [ChangePasswordController::class, 'update'])
            ->name('admin.change-password.update');

        /*
        |--------------------------------------------------------------------------
        | Reports
        |--------------------------------------------------------------------------
        */
        Route::prefix('reports')->name('admin.reports.')->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('/assets', [ReportController::class, 'assets'])->name('assets');
            Route::get('/issuance', [IssuanceController::class, 'index'])->name('issuance');
            Route::get('/issuance/export', [IssuanceController::class, 'export'])->name('issuance.export');
            Route::get('/accounts', [ReportController::class, 'accounts'])
                ->middleware('role:admin')
                ->name('accounts');
            Route::get('/checked-equipment', [ReportController::class, 'checkedEquipment'])->name('checkedEquipment');
            Route::post('/checked-equipment/pdf-selected', [ReportController::class, 'checkedEquipmentSelectedPdf'])->name('checkedEquipment.pdfSelected');
            Route::get('/checked-equipment/pdf-filtered', [ReportController::class, 'checkedEquipmentFilteredPdf'])->name('checkedEquipment.pdfFiltered');
            Route::get('/checked-equipment/{record}/pdf', [ReportController::class, 'checkedEquipmentPdf'])->name('checkedEquipment.pdf');
            Route::get('/checklist', [ReportController::class, 'checklist'])->name('checklist');
        });

        /*
        |--------------------------------------------------------------------------
        | Devices
        |--------------------------------------------------------------------------
        */
        Route::put('/devices/{device}/quick', [DeviceController::class, 'quickUpdate'])
            ->name('admin.devices.quickUpdate');

        Route::patch('/devices/{device}/mark-checked', [DeviceController::class, 'markChecked'])
            ->name('admin.devices.markChecked');

        Route::get('/devices/{device}/maintenance-checklist', [DeviceChecklistController::class, 'create'])
            ->name('admin.devices.checklist.form');

        Route::post('/devices/{device}/maintenance-checklist', [DeviceChecklistController::class, 'store'])
            ->name('admin.devices.checklist.save');

        // Legacy alias: old forms that still post to /pdf will still save instead of downloading.
        Route::post('/devices/{device}/maintenance-checklist/pdf', [DeviceChecklistController::class, 'store'])
            ->name('admin.devices.checklist.pdf');

        Route::get('/devices/{device}/maintenance-history', [DeviceController::class, 'maintenanceHistory'])
            ->name('admin.devices.history');

        Route::get('/reports/preventive-maintenance/export', [DeviceController::class, 'exportPreventiveMaintenanceReport'])
            ->name('admin.reports.preventiveMaintenance.export');

        Route::get('/devices/generate-qr', [DeviceController::class, 'generateQr'])
            ->name('admin.devices.qr.index');

        Route::get('/devices/lookup/staff', [DeviceController::class, 'staffLookup'])
            ->name('admin.devices.lookup.staff');

        Route::get('/devices/lookup/available', [DeviceController::class, 'availableLookup'])
            ->name('admin.devices.lookup.available');

        Route::post('/devices/{device}/issue', [DeviceController::class, 'issue'])
            ->name('admin.devices.issue');

        Route::post('/devices/{device}/reissue', [DeviceController::class, 'reissue'])
            ->name('admin.devices.reissue');

        Route::patch('/devices/{device}/photo', [DeviceController::class, 'updatePhoto'])
            ->name('admin.devices.photo');

        Route::delete('/devices/{device}/photo', [DeviceController::class, 'destroyPhoto'])
            ->name('admin.devices.photo.destroy');

        Route::post('/devices/import', [DeviceController::class, 'import'])
            ->name('admin.devices.import');

        Route::get('/devices/import-template', [DeviceController::class, 'importTemplate'])
            ->name('admin.devices.importTemplate');

        Route::resource('/devices', DeviceController::class)
            ->names('admin.devices')
            ->except(['destroy']);
    });


    /*
    |--------------------------------------------------------------------------
    | Locations — browsing is open to both roles
    |--------------------------------------------------------------------------
    */
    Route::get('admin/locations', [LocationController::class, 'index'])->name('admin.locations.index');
    // Backward-compatible old URL/name.
    Route::get('admin/colleges', fn () => redirect()->route('admin.locations.index'))->name('admin.colleges.index');

    /*
    |--------------------------------------------------------------------------
    | Offices — browsing is open to both roles
    |--------------------------------------------------------------------------
    */
    Route::get('admin/locations/{location}/offices', [OfficeController::class, 'index'])
        ->name('admin.offices.index');
    // Backward-compatible old URL.
    Route::get('admin/colleges/{location}/offices', [OfficeController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | Staff — browsing is open to both roles
    |--------------------------------------------------------------------------
    */
    Route::get('admin/offices/{office}/staff', [StaffController::class, 'index'])
        ->name('admin.staff.index');

    /*
    |--------------------------------------------------------------------------
    | Office Reports — both roles may need to pull these for inventory work
    |--------------------------------------------------------------------------
    */
    Route::get('admin/offices/{office}/reports/preventive-maintenance/export', [DeviceController::class, 'exportOfficePreventiveMaintenanceReport'])
        ->name('admin.offices.preventiveMaintenance.export');

    /*
    |--------------------------------------------------------------------------
    | Staff Devices — issuing/returning equipment is core custodian work
    |--------------------------------------------------------------------------
    */
    Route::get('admin/staff/{staff}/devices', [StaffDeviceController::class, 'index'])
        ->name('admin.staff.devices.index');

    Route::post('admin/staff/{staff}/devices/issue', [StaffDeviceController::class, 'issue'])
        ->name('admin.staff.devices.issue');

    Route::post('admin/staff/{staff}/devices/{assignment}/return', [StaffDeviceController::class, 'return'])
        ->name('admin.staff.devices.return');

    /*
    |--------------------------------------------------------------------------
    | Legacy unprefixed URLs
    |--------------------------------------------------------------------------
    | Older deployments linked to /locations, /offices, and /staff directly.
    | Keep those GET URLs working, but send users to the admin-prefixed route
    | names used by the sidebar. This is more reliable under htdocs subfolders.
    */
    Route::get('locations', fn () => redirect()->route('admin.locations.index'));
    Route::get('colleges', fn () => redirect()->route('admin.locations.index'));
    Route::get('locations/{location}/offices', fn ($location) => redirect()->route('admin.offices.index', $location));
    Route::get('colleges/{location}/offices', fn ($location) => redirect()->route('admin.offices.index', $location));
    Route::get('offices/{office}/staff', fn ($office) => redirect()->route('admin.staff.index', $office));
    Route::get('offices/{office}/reports/preventive-maintenance/export', fn ($office) => redirect()->route('admin.offices.preventiveMaintenance.export', $office));
    Route::get('staff/{staff}/devices', fn ($staff) => redirect()->route('admin.staff.devices.index', $staff));

    /*
    |--------------------------------------------------------------------------
    | Admin-only — org structure changes & user management
    |--------------------------------------------------------------------------
    | Creating/editing/deleting locations, offices, and staff records changes
    | the university's organizational structure, which is an admin decision.
    | Custodians can browse this directory (routes above) but not modify it.
    */
    Route::middleware('role:admin')->group(function () {

        // Locations — write actions
        Route::post('admin/locations', [LocationController::class, 'store'])->name('admin.locations.store');
        Route::get('admin/locations/{location}/edit', [LocationController::class, 'edit'])->name('admin.locations.edit');
        Route::put('admin/locations/{location}', [LocationController::class, 'update'])->name('admin.locations.update');
        Route::delete('admin/locations/{location}', [LocationController::class, 'destroy'])->name('admin.locations.destroy');

        // Backward-compatible old route names/URLs.
        Route::post('admin/colleges', [LocationController::class, 'store'])->name('admin.colleges.store');
        Route::get('admin/colleges/{location}/edit', [LocationController::class, 'edit'])->name('admin.colleges.edit');
        Route::put('admin/colleges/{location}', [LocationController::class, 'update'])->name('admin.colleges.update');
        Route::delete('admin/colleges/{location}', [LocationController::class, 'destroy'])->name('admin.colleges.destroy');

        // Offices — write actions
        Route::post('admin/locations/{location}/offices', [OfficeController::class, 'store'])
            ->name('admin.offices.store');
        Route::get('admin/locations/{location}/offices/{office}/edit', [OfficeController::class, 'edit'])
            ->name('admin.offices.edit');
        Route::put('admin/locations/{location}/offices/{office}', [OfficeController::class, 'update'])
            ->name('admin.offices.update');
        Route::delete('admin/locations/{location}/offices/{office}', [OfficeController::class, 'destroy'])
            ->name('admin.offices.destroy');

        // Backward-compatible old office URLs.
        Route::post('admin/colleges/{location}/offices', [OfficeController::class, 'store']);
        Route::get('admin/colleges/{location}/offices/{office}/edit', [OfficeController::class, 'edit']);
        Route::put('admin/colleges/{location}/offices/{office}', [OfficeController::class, 'update']);
        Route::delete('admin/colleges/{location}/offices/{office}', [OfficeController::class, 'destroy']);

        // Staff — write actions
        Route::post('admin/offices/{office}/staff', [StaffController::class, 'store'])
            ->name('admin.staff.store');
        Route::get('admin/offices/{office}/staff/{staff}/edit', [StaffController::class, 'edit'])
            ->name('admin.staff.edit');
        Route::put('admin/offices/{office}/staff/{staff}', [StaffController::class, 'update'])
            ->name('admin.staff.update');
        Route::delete('admin/offices/{office}/staff/{staff}', [StaffController::class, 'destroy'])
            ->name('admin.staff.destroy');

        // Device deletion — deleting records is admin-only system-wide
        Route::delete('admin/devices/bulk-delete', [DeviceController::class, 'bulkDestroy'])
            ->name('admin.devices.bulkDestroy');

        Route::delete('admin/devices/{device}', [DeviceController::class, 'destroy'])
            ->name('admin.devices.destroy');

        // User accounts & role management
        Route::get('admin/users', [UserController::class, 'index'])->name('admin.users.index');
        Route::post('admin/users', [UserController::class, 'store'])->name('admin.users.store');
        Route::put('admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
        Route::delete('admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');

        // Activity logs — admin-only audit trail
        Route::get('admin/logs', [ActivityLogController::class, 'index'])->name('admin.logs.index');
    });
});
