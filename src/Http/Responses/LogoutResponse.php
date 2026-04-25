<?php

namespace Foundry\Http\Responses;

use Foundry\Facades\Guard;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LogoutResponse implements LogoutResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     * @return Response
     */
    public function toResponse($request)
    {
        return $request->wantsJson()
            ? response()->json(['message' => 'Logged out'], 204)
            : redirect()->to(Guard::isAdmin($request) ? route(Guard::loginRoute($request)) : '/');
    }
}
