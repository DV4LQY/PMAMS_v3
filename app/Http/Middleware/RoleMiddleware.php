<?php

namespace App\Http\Middleware;

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

        // Unit Head has the same permission as Admin.
        if ($userRole === 'unit_head') {
            $userRole = 'admin';
        }

        if (! in_array($userRole, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
