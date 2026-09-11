<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OwnerDashboardOnly
{
    private const ALLOWED_ROUTES = [
        'dashboard',
        'division.dashboard',
        'voyage-logs.dashboard',
        'tech-defects.dashboard',
        'vessel-certificates.dashboard',
        'supplier.report',
        'profile',
        'profile.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isExecutiveViewer() && ! $request->routeIs(...self::ALLOWED_ROUTES)) {
            return redirect()->route('dashboard')
                ->with('error', 'Owner accounts have read-only dashboard access.');
        }

        return $next($request);
    }
}
