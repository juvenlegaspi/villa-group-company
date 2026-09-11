<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DivisionMiddleware
{
    public function handle(Request $request, Closure $next, string ...$divisions): Response
    {
        $user = $request->user();

        abort_unless($user, 403);

        if ($user->isExecutiveViewer() && $request->routeIs(
            'voyage-logs.dashboard',
            'tech-defects.dashboard',
            'vessel-certificates.dashboard'
        )) {
            return $next($request);
        }

        if ($user->canManageAllCompanies()) {
            return $next($request);
        }

        $user->loadMissing('division');
        $allowed = collect($divisions)
            ->contains(fn (string $division) => strcasecmp((string) $user->division?->name, $division) === 0);

        abort_unless($allowed, 403, 'You do not have access to this division.');

        return $next($request);
    }
}
