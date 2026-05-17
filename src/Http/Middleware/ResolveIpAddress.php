<?php

namespace Foundry\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ResolveIpAddress
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Trigger lazy IP & location resolution on-demand (used by tests and route middleware).
        $request->ipLocation();

        return $next($request);
    }
}
