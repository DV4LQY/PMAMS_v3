<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevent authenticated pages from being restored from browser history or
 * the back/forward cache after an account switch or logout.
 */
class PreventBrowserCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // StreamedResponse (used by database downloads) does not expose
        // Laravel's withHeaders() helper. Use the Symfony header bag so this
        // middleware works for both regular and streamed responses.
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
