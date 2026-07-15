# TODO

## Completed

* [x] Fix Colleges page bulk toggle / delete confirmation not working
  (caused by duplicate Alpine.js instances — removed manual Alpine import in `app.js`).
* [x] Re-test bulk add — confirmed working.
* [x] Prevent duplicate codes from crashing bulk add (in-batch check added).
* [x] Stop add/edit errors leaking between modals and records.
* [x] Enter key now confirms delete.
* [x] Bulk add error messages no longer show raw field index (e.g. `codes.0`).

## Recent Features

### User Management

* [x] Add User Management module.
* [x] Create UserController.
* [x] Add user administration views.
* [x] Update User model.
* [x] Implement role-based access control.

### Activity Logging

* [x] Create ActivityLog model.
* [x] Create activity_logs migration.
* [x] Create ActivityLogController.
* [x] Add activity log views.
* [x] Record administrative actions for auditing.

### Authorization

* [x] Replace AdminMiddleware with RoleMiddleware.
* [x] Update application bootstrap middleware registration.
* [x] Restrict admin features by role.

### Admin Module Improvements

* [x] Improve Colleges management.
* [x] Improve Offices management.
* [x] Improve Devices management.
* [x] Improve Staff management.
* [x] Improve Staff Device assignment workflow.
* [x] Update admin layout/navigation.
* [x] Update routes for new modules.

### Cleanup

* [x] Remove unused AdminMiddleware.
* [x] Remove deprecated admin browser components.

## Later

* [ ] Remove duplicate `add_condition_and_specs_to_devices_table` migration (harmless, just redundant).
* [ ] Add activity log filtering and search.
* [ ] Add role management UI.
* [ ] Review authorization coverage across all admin routes.
* [ ] Add automated tests for user roles and permissions.
