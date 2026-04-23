<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use function Orchestra\Testbench\default_skeleton_path;

return Application::configure(basePath: $APP_BASE_PATH ?? default_skeleton_path())
    ->withRouting(
        api: array_filter([
            default_skeleton_path('routes/api.php'),
            __DIR__.'/../routes/api.php',
        ]),
        web: array_filter([
            default_skeleton_path('routes/web.php'),
            __DIR__.'/../routes/web.php',
        ]),
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: [
            'cart_token',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
