<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ((int) $request->user()?->must_change_password === 1) {
            return redirect('/change-password')
                ->with('warning', 'Change your temporary password before continuing.');
        }

        return $next($request);
    }
}
