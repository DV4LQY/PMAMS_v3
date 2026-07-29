<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the optional per-user menu/action permissions without changing the
 * existing role middleware. Super Admins are always unrestricted.
 */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        [$menu, $resource, $action] = $this->permissionForRoute((string) ($request->route()?->getName() ?? ''));

        if ($menu && ! $user->canMenu($menu)) {
            abort(403, 'You do not have access to this menu.');
        }

        if ($resource && $action && ! $user->canAction($resource, $action)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }

    private function permissionForRoute(string $route): array
    {
        if ($route === 'admin.dashboard') return ['dashboard', null, null];
        if ($route === 'admin.scanner') return ['scanner', null, null];
        if ($route === 'admin.support') return ['support', null, null];
        if (str_starts_with($route, 'admin.logs.')) return ['activity_logs', null, null];
        if (str_starts_with($route, 'admin.database.')) return ['database', null, null];
        if (str_starts_with($route, 'admin.maintenance-cleanup.')) return ['maintenance_cleanup', 'checklist', $this->action($route)];
        if (str_starts_with($route, 'admin.maintenance-plan.')) return ['maintenance_plan', 'maintenance_plan', $this->action($route)];
        if ($route === 'admin.maintenance-gallery.photo') return ['maintenance_gallery', null, null];
        if (str_starts_with($route, 'admin.maintenance-gallery.')) return ['maintenance_gallery', 'maintenance_gallery', $this->action($route)];
        if ($route === 'admin.reports.checkedEquipment.delete') return ['reports', 'checklist', 'delete'];
        if (str_starts_with($route, 'admin.reports.')) {
            $issuance = str_contains($route, '.issuance');
            return [$issuance ? 'issuance' : 'reports', $issuance ? 'issuance' : null, null];
        }
        if (str_starts_with($route, 'admin.issuance.')) return ['issuance', 'issuance', null];
        if (str_contains($route, 'devices.checklist.')) return ['equipment', 'checklist', $this->action($route)];
        if (str_starts_with($route, 'admin.devices.')) return ['equipment', 'equipment', $this->action($route)];
        if (str_starts_with($route, 'admin.locations.') || str_starts_with($route, 'admin.colleges.')) {
            return ['locations', 'locations', $this->action($route)];
        }
        if (str_starts_with($route, 'admin.offices.')) return ['locations', 'offices', $this->action($route)];
        if (str_starts_with($route, 'admin.staff.')) {
            $isIssuance = str_contains($route, '.devices.');
            return [$isIssuance ? 'issuance' : 'locations', $isIssuance ? 'issuance' : 'staff', $this->action($route)];
        }
        if ($route === 'admin.org-browser') return ['locations', null, null];

        return [null, null, null];
    }

    private function action(string $route): ?string
    {
        if (str_contains($route, 'destroy') || str_contains($route, 'delete') || str_contains($route, 'forceDelete')) return 'delete';
        if (str_contains($route, 'store') || str_contains($route, 'create') || str_contains($route, 'import')) return 'add';
        if (str_contains($route, 'update') || str_contains($route, 'edit') || str_contains($route, 'quickUpdate') || str_contains($route, 'markChecked') || str_contains($route, 'checklist') || str_contains($route, 'link') || str_contains($route, 'unlink') || str_contains($route, 'issue') || str_contains($route, 'reissue') || str_contains($route, 'photo') || str_contains($route, 'restore') || str_contains($route, 'override') || str_contains($route, 'complete')) return 'edit';

        return null;
    }
}
