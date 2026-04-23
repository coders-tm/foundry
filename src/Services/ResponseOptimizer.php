<?php

namespace Foundry\Services;

use Closure;
use Foundry\Contracts\ConfigurationInterface;
use Illuminate\Http\Request;

class ResponseOptimizer
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (app()->runningUnitTests()) {
            return $next($request);
        }

        $response = $next($request);

        try {
            $loader = app(ConfigurationInterface::class);

            return $loader->optimizeResponse($request, $response);
        } catch (\Throwable $e) {
            // report($e);
        }

        return $response;
    }
}
