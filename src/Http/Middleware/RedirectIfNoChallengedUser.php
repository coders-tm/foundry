<?php

namespace Foundry\Http\Middleware;

use Closure;
use Foundry\Facades\Guard;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfNoChallengedUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('login.id')) {
            return redirect()->route(Guard::loginRoute($request));
        }

        return $next($request);
    }
}
