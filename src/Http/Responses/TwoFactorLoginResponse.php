<?php

namespace Foundry\Http\Responses;

use Foundry\Services\GuardManager;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function __construct(protected GuardManager $guard) {}

    public function toResponse($request)
    {
        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->intended($this->guard->home());
    }
}
