<?php

namespace Foundry\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GuardMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        if (guard(...$guards)) {
            return $next($request);
        }

        return $this->failed();
    }

    protected function failed()
    {
        if (request()->expectsJson()) {
            return response()->json([
                'message' => __('Unauthenticated.'),
            ], 401);
        }

        return redirect()->guest('/login');
    }
}
