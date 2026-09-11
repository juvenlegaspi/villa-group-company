<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if ($request->user()?->isExecutiveViewer() && $request->routeIs(
            'voyage-logs.dashboard',
            'tech-defects.dashboard',
            'vessel-certificates.dashboard'
        )) {
            return $next($request);
        }

        abort_unless(
            $request->user()?->hasPermission($permission),
            403,
            'Your department or position is not authorized to access this module.'
        );

        return $next($request);
    }
}
