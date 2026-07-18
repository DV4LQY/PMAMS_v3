<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // APP_URL is normally correct for `php artisan serve`, but an Apache
        // deployment under htdocs may be mounted below a subfolder such as
        // /pms_systemv2/public. Build generated route/action URLs from the
        // active request in that case so directory links do not become 404s.
        if ($this->app->runningInConsole()) {
            return;
        }

        $request = $this->app->make('request');
        $baseUrl = rtrim((string) $request->getBaseUrl(), '/');

        if ($baseUrl === '') {
            $path = (string) $request->getPathInfo();
            $adminIndex = strpos($path, '/admin');

            if ($adminIndex !== false) {
                $baseUrl = rtrim(substr($path, 0, $adminIndex), '/');
            }
        }

        URL::forceRootUrl($request->getSchemeAndHttpHost() . $baseUrl);
        URL::forceScheme($request->getScheme());
    }
}
