<?php

namespace Foundry\Services;

use Illuminate\Http\Request;

/**
 * Resolves the authentication guard context based on the current request.
 *
 * This is the single source of truth for determining which guard (user/admin)
 * should be used throughout the request lifecycle. Guard behaviour is driven
 * entirely by the foundry.auth.guards config so downstream apps only need to
 * adjust that config rather than subclassing this service.
 */
class GuardManager
{
    protected ?string $resolved = null;

    public function key(?Request $request = null): string
    {
        $request ??= request();
        $adminPrefix = config('foundry.admin_prefix', 'admin');

        return ($request->is($adminPrefix) || $request->is("{$adminPrefix}/*"))
            ? 'admin'
            : 'user';
    }

    public function guard(?Request $request = null): string
    {
        return $this->config('guard', $request) ?: $this->key($request);
    }

    public function isAdmin(?Request $request = null): bool
    {
        return $this->key($request) === 'admin';
    }

    public function passwordBroker(): string
    {
        return $this->config('password_broker') ?: ($this->isAdmin() ? 'admin' : 'user');
    }

    public function home(): string
    {
        return $this->config('home') ?: ($this->isAdmin() ? '/admin' : '/dashboard');
    }

    public function loginRoute(): string
    {
        return $this->config('login_route') ?: ($this->isAdmin() ? 'admin.login' : 'login');
    }

    public function twoFactorRoute(): string
    {
        return $this->config('two_factor_route') ?: ($this->isAdmin() ? 'admin.two-factor.login' : 'two-factor.login');
    }

    public function passwordResetRoute(): string
    {
        return $this->config('password_reset_route') ?: ($this->isAdmin() ? 'admin.password.reset' : 'password.reset');
    }

    public function prefix(): string
    {
        return $this->isAdmin() ? config('foundry.admin_prefix', 'admin') : '';
    }

    /**
     * Retrieve a value from the active guard's config block.
     * Checks auth.guards first (centralized), then falls back to foundry.auth.guards.
     */
    protected function config(string $key, ?Request $request = null): string
    {
        $guardKey = $this->key($request);

        return config("auth.guards.{$guardKey}.{$key}")
            ?? config("foundry.auth.guards.{$guardKey}.{$key}")
            ?? '';
    }
}
