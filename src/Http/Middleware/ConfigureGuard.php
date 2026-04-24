<?php

namespace Foundry\Http\Middleware;

use Closure;
use Foundry\Facades\Guard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dynamically reconfigures Fortify when the request targets admin routes.
 *
 * Prepend this middleware to the web stack so that every downstream Fortify
 * controller, action, and response operates under the correct guard, password
 * broker, home path, and feature set.
 */
class ConfigureGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        Guard::setRequest($request);

        config([
            'fortify.guard' => Guard::guard(),
            'fortify.passwords' => Guard::passwordBroker(),
            'fortify.prefix' => Guard::prefix(),
            'fortify.home' => Guard::home(),
        ]);

        Inertia::share('auth.guard', Guard::guard());

        return $next($request);
    }
}
