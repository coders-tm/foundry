<?php

namespace Foundry\Http\Responses;

use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\TwoFactorChallengeViewResponse as TwoFactorChallengeViewResponseContract;

class TwoFactorChallengeViewResponse implements TwoFactorChallengeViewResponseContract
{
    public function toResponse($request)
    {
        Log::info('TwoFactorChallengeViewResponse::toResponse called');

        return Inertia::render('auth/TwoFactorChallenge')->toResponse($request);
    }
}
