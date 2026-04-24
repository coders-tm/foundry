<?php

namespace Foundry\Providers;

use Foundry\Actions\Fortify\RedirectIfTwoFactorAuthenticatable;
use Foundry\Facades\Guard;
use Foundry\Http\Responses\PasswordResetResponse;
use Foundry\Http\Responses\TwoFactorLoginResponse;
use Foundry\Services\GuardManager;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\EnsureLoginIsNotThrottled;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

/**
 * Optional Fortify integration service provider.
 *
 * Register this provider manually in bootstrap/providers.php in applications
 * that use Laravel Fortify for session-based authentication.
 *
 * Apps that need to customise views, actions, or rate limits should extend
 * this class and override individual methods.
 *
 * Publishing:
 *   php artisan vendor:publish --tag=foundry-fortify-provider
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GuardManager::class);

        $this->app->bind(StatefulGuard::class, function ($app) {
            return Auth::guard($app->make(GuardManager::class)->guard());
        });

        $this->app->singleton(PasswordResetResponseContract::class, PasswordResetResponse::class);
        $this->app->singleton(TwoFactorLoginResponseContract::class, TwoFactorLoginResponse::class);
    }

    public function boot(): void
    {
        $this->configureViews();
        $this->configurePasswordResetUrl();
        $this->configureAuthentication();
        $this->configureRateLimiting();
    }

    protected function configureViews(): void
    {
        Fortify::loginView(function (Request $request) {
            if (class_exists(Inertia::class) && $request->header('X-Inertia')) {
                return Inertia::render('auth/Login', [
                    'canResetPassword' => Features::enabled(Features::resetPasswords()),
                    'canRegister' => Features::enabled(Features::registration()),
                    'status' => $request->session()->get('status'),
                ]);
            }

            return view(Guard::isAdmin() ? 'admin.login' : 'auth.login', [
                'status' => $request->session()->get('status'),
            ]);
        });

        Fortify::twoFactorChallengeView(function (Request $request) {
            if (class_exists(Inertia::class) && $request->header('X-Inertia')) {
                return Inertia::render('auth/TwoFactorChallenge');
            }

            return view('auth.two-factor-challenge');
        });

        Fortify::registerView(function (Request $request) {
            if (class_exists(Inertia::class) && $request->header('X-Inertia')) {
                return Inertia::render('auth/Register');
            }

            return view('auth.register');
        });

        Fortify::requestPasswordResetLinkView(function (Request $request) {
            if (class_exists(Inertia::class) && $request->header('X-Inertia')) {
                return Inertia::render('auth/ForgotPassword', [
                    'status' => $request->session()->get('status'),
                ]);
            }

            return view('auth.forgot-password', [
                'status' => $request->session()->get('status'),
            ]);
        });

        Fortify::resetPasswordView(function (Request $request) {
            if (class_exists(Inertia::class) && $request->header('X-Inertia')) {
                return Inertia::render('auth/ResetPassword', [
                    'email' => $request->email,
                    'token' => $request->route('token'),
                ]);
            }

            return view('auth.reset-password', [
                'email' => $request->email,
                'token' => $request->route('token'),
            ]);
        });

        Fortify::verifyEmailView(function (Request $request) {
            if (class_exists(Inertia::class) && $request->header('X-Inertia')) {
                return Inertia::render('auth/VerifyEmail', [
                    'status' => $request->session()->get('status'),
                ]);
            }

            return view('auth.verify-email', [
                'status' => $request->session()->get('status'),
            ]);
        });

        Fortify::confirmPasswordView(function (Request $request) {
            if (class_exists(Inertia::class) && $request->header('X-Inertia')) {
                return Inertia::render('auth/ConfirmPassword');
            }

            return view('auth.confirm-password');
        });
    }

    protected function configurePasswordResetUrl(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return url(route(
                Guard::passwordResetRoute(),
                [
                    'token' => $token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ],
                false
            ));
        });
    }

    protected function configureAuthentication(): void
    {
        Fortify::authenticateThrough(function (Request $request) {
            return array_filter([
                config('fortify.limiters.login') ? null : EnsureLoginIsNotThrottled::class,
                config('fortify.lowercase_usernames') ? CanonicalizeUsername::class : null,
                Features::enabled(Features::twoFactorAuthentication()) ? RedirectIfTwoFactorAuthenticatable::class : null,
                AttemptToAuthenticate::class,
                PrepareAuthenticatedSession::class,
            ]);
        });
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(
                Str::lower($request->input(Fortify::username())).'|'.$request->ip()
            );

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
