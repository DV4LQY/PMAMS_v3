<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        if (! auth()->check()) {
            abort(403);
        }

        $userRole = auth()->user()->role;

        // Super Admin is unrestricted and may access every role-protected
        // module, including admin-only organization and user routes.
        if ($userRole === User::ROLE_SUPER_ADMIN) {
            return $next($request);
        }

        // Unit Head has the same permission as Admin.
        if ($userRole === User::ROLE_UNIT_HEAD) {
            $userRole = User::ROLE_ADMIN;
        }

        if (! in_array($userRole, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
