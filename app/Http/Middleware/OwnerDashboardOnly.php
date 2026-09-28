<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OwnerDashboardOnly
{
    private const ADMIN_ONLY_ROUTES = [
        'users.*',
        'organization.*',
        'departments.*',
    ];

    private const MUTATION_FORM_ROUTES = [
        '*.create',
        '*.edit',
        'vessel-certificates.add',
        'vessel-certificates.renew',
        'tech-defects.repair-closeout',
    ];

    private const ALLOWED_PERSONAL_MUTATIONS = [
        'profile.update',
        'notifications.read',
        'notifications.read-all',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isExecutiveViewer()) {
            return $next($request);
        }

        if ($request->routeIs(...self::ADMIN_ONLY_ROUTES)) {
            abort(403, 'User Management and Organization Setup are restricted to system administrators.');
        }

        if ($request->routeIs(...self::MUTATION_FORM_ROUTES)) {
            abort(403, 'Executive Viewer access is read-only.');
        }

        if (! $request->isMethodSafe() && ! $request->routeIs(...self::ALLOWED_PERSONAL_MUTATIONS)) {
            abort(403, 'Executive Viewer access is read-only.');
        }

        return $next($request);
    }
}
