<?php

namespace Foundry\Actions\Fortify;

use Foundry\Services\GuardManager;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Authentication pipeline action that intercepts login after credential
 * validation and redirects to the 2FA challenge if the user has it enabled.
 *
 * Uses GuardManager to resolve the correct guard and the guard-specific
 * two-factor challenge route so admin and user guards each get their own
 * challenge page without any hard-coded route names here.
 */
class RedirectIfTwoFactorAuthenticatable
{
    public function __construct(
        protected GuardManager $guardManager,
        protected LoginRateLimiter $limiter
    ) {}

    public function __invoke($request, $next)
    {
        $user = $this->validateCredentials($request);

        if (Fortify::confirmsTwoFactorAuthentication()) {
            if (
                optional($user)->two_factor_secret &&
                ! is_null(optional($user)->two_factor_confirmed_at) &&
                in_array(TwoFactorAuthenticatable::class, class_uses_recursive($user))
            ) {
                return $this->twoFactorChallengeResponse($request, $user);
            }

            return $next($request);
        }

        if (
            optional($user)->two_factor_secret &&
            in_array(TwoFactorAuthenticatable::class, class_uses_recursive($user))
        ) {
            return $this->twoFactorChallengeResponse($request, $user);
        }

        return $next($request);
    }

    protected function validateCredentials($request)
    {
        $guard = Auth::guard($this->guardManager->guard());

        if (Fortify::$authenticateUsingCallback) {
            return tap(call_user_func(Fortify::$authenticateUsingCallback, $request), function ($user) use ($request, $guard) {
                if (! $user) {
                    $this->fireFailedEvent($request, $guard);
                    $this->throwFailedAuthenticationException($request);
                }
            });
        }

        $provider = $guard->getProvider();

        return tap($provider->retrieveByCredentials($request->only(Fortify::username(), 'password')), function ($user) use ($provider, $request, $guard) {
            if (! $user || ! $provider->validateCredentials($user, ['password' => $request->password])) {
                $this->fireFailedEvent($request, $guard, $user);
                $this->throwFailedAuthenticationException($request);
            }

            if (config('hashing.rehash_on_login', true) && method_exists($provider, 'rehashPasswordIfRequired')) {
                $provider->rehashPasswordIfRequired($user, ['password' => $request->password]);
            }
        });
    }

    protected function throwFailedAuthenticationException($request): void
    {
        $this->limiter->increment($request);

        throw ValidationException::withMessages([
            Fortify::username() => [__('auth.failed')],
        ]);
    }

    protected function fireFailedEvent($request, $guard, $user = null): void
    {
        event(new Failed($guard->name ?? config('fortify.guard'), $user, [
            Fortify::username() => $request->{Fortify::username()},
            'password' => $request->password,
        ]));
    }

    protected function twoFactorChallengeResponse($request, $user)
    {
        $request->session()->put([
            'login.id' => $user->getKey(),
            'login.remember' => $request->boolean('remember'),
        ]);

        TwoFactorAuthenticationChallenged::dispatch($user);

        return $request->wantsJson()
            ? response()->json(['two_factor' => true])
            : redirect()->route($this->guardManager->twoFactorRoute());
    }
}
