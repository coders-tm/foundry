<?php

namespace Foundry\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  Request  $request
     * @return Response
     *
     * @throws Throwable
     */
    public function render($request, Throwable $e)
    {
        if ($e instanceof AuthorizationException) {
            $e = new AuthorizationException(__('You do not have permission to access. Please contact your administrator to request access.'), $e->getCode());
        }

        return parent::render($request, $e);
    }
}
