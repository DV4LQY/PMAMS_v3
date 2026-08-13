<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Publish only public, indexable pages. All application data routes are
     * authenticated and are intentionally excluded from the sitemap.
     */
    public function __invoke(): Response
    {
        $urls = [
            [
                'loc' => url('/login'),
                'changefreq' => 'monthly',
                'priority' => '0.5',
            ],
            [
                'loc' => url('/forgot-password'),
                'changefreq' => 'yearly',
                'priority' => '0.2',
            ],
        ];

        return response()
            ->view('sitemap', compact('urls'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
