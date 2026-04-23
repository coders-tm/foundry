<?php

namespace Foundry\Http\Responses;

use Foundry\Services\GuardManager;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;

class PasswordResetResponse implements PasswordResetResponseContract
{
    public function __construct(protected GuardManager $guard) {}

    public function toResponse($request)
    {
        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->route($this->guard->loginRoute())
                ->with('status', __($request->status ?? 'passwords.reset'));
    }
}
