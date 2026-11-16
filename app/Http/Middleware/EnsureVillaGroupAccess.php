<?php

namespace App\Http\Middleware;

use App\Services\VillaGroupAccessService;
use Closure;
use Illuminate\Http\Request;

class EnsureVillaGroupAccess
{
    public function handle(Request $request, Closure $next, string $module)
    {
        abort_unless($request->user() && app(VillaGroupAccessService::class)->canAccess($request->user(), $module), 403, 'Your Villa Group assignment does not grant access to this module.');
        return $next($request);
    }
}
