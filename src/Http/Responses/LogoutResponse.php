<?php

namespace Foundry\Http\Responses;

use Foundry\Facades\Guard;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

class LogoutResponse implements LogoutResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        return $request->wantsJson()
            ? response()->json(['message' => 'Logged out'], 204)
            : redirect()->to(Guard::isAdmin($request) ? route(Guard::loginRoute($request)) : '/');
    }
}
