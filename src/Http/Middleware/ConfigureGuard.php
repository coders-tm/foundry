<?php

namespace Foundry\Http\Middleware;

use Closure;
use Foundry\Services\GuardManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dynamically reconfigures Fortify when the request targets admin routes.
 *
 * Prepend this middleware to the web stack so that every downstream Fortify
 * controller, action, and response operates under the correct guard, password
 * broker, home path, and feature set.
 *
 * Inertia::share is called conditionally — only when inertiajs/inertia-laravel
 * is installed — so the middleware remains usable without Inertia.
 */
class ConfigureGuard
{
    public function __construct(protected GuardManager $guardManager) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = $this->guardManager->guard($request);

        config([
            'fortify.guard' => $guard,
            'fortify.passwords' => $this->guardManager->passwordBroker(),
            'fortify.prefix' => $this->guardManager->prefix(),
            'fortify.home' => $this->guardManager->home(),
        ]);

        Inertia::share('auth.guard', $this->guardManager->guard($request));

        $response = $next($request);

        if ($response instanceof RedirectResponse && $response->getTargetUrl() === route('login') && $this->guardManager->isAdmin($request)) {
            $response->setTargetUrl(route('admin.login'));
        }

        return $response;
    }
}
