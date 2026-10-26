<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if ($permission === 'shipping.dry_docking.access') {
            abort_unless($request->user()?->canAccessDryDocking(), 403);
        }

        if ($request->user()?->isExecutiveViewer() && $request->isMethodSafe()) {
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
