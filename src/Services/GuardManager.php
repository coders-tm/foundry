<?php

namespace Foundry\Services;

use Closure;
use Illuminate\Http\Request;

/**
 * Resolves the authentication guard context based on the current request.
 *
 * This is the single source of truth for determining which guard (user/admin)
 * should be used throughout the request lifecycle.
 */
class GuardManager
{
    /**
     * The current request instance.
     */
    protected ?Request $request = null;

    /**
     * The resolved guard context name.
     */
    protected ?string $resolved = null;

    /**
     * A custom resolver callback to determine the guard context.
     */
    protected ?Closure $customResolver = null;

    /**
     * Set the current request instance.
     */
    public function setRequest(Request $request): static
    {
        $this->request = $request;

        return $this->forgetResolved();
    }

    /**
     * Get the current request instance.
     */
    public function getRequest(): Request
    {
        return $this->request ?? request();
    }

    /**
     * Clear the resolved guard context.
     */
    public function forgetResolved(): static
    {
        $this->resolved = null;

        return $this;
    }

    /**
     * Register a custom resolver callback.
     */
    public function resolveUsing(?Closure $callback): static
    {
        $this->customResolver = $callback;

        return $this->forgetResolved();
    }

    /**
     * Get the resolved guard context name (e.g., 'admin' or 'user').
     */
    public function current(?Request $request = null): string
    {
        // If an explicit request is passed, we resolve it without poisoning state
        if ($request && $request !== $this->request) {
            return $this->resolveContextName($request);
        }

        return $this->resolved ??= $this->resolveContextName($this->getRequest());
    }

    /**
     * Alias for current() to get the current context name.
     */
    public function key(?Request $request = null): string
    {
        return $this->current($request);
    }

    /**
     * Alias for current() to get the current context name.
     */
    public function context(): string
    {
        return $this->current();
    }

    /**
     * Resolve the context name from the given request.
     */
    protected function resolveContextName(Request $request): string
    {
        if ($this->customResolver) {
            return (string) ($this->customResolver)($request);
        }

        $guards = config('foundry.guards', []);

        foreach ($guards as $name => $config) {
            if ($request->is(...($config['paths'] ?? []))) {
                return $name;
            }
        }

        // Fallback to legacy detection if config is missing
        $adminPrefix = (string) config('foundry.admin_prefix', 'admin');

        return $request->is("{$adminPrefix}", "{$adminPrefix}/*") ? 'admin' : 'user';
    }

    /**
     * Check if the resolved context matches the given name.
     */
    public function is(string $name): bool
    {
        return $this->current() === $name;
    }

    /**
     * Check if the current context is admin.
     */
    public function isAdmin(?Request $request = null): bool
    {
        return ($request ? $this->current($request) : $this->current()) === 'admin';
    }

    /**
     * Get the current guard name.
     */
    public function guard(?Request $request = null): string
    {
        return $this->resolveValue('guard', $request);
    }

    /**
     * Get the current password broker name.
     */
    public function passwordBroker(?Request $request = null): string
    {
        return $this->resolveValue('password_broker', $request);
    }

    /**
     * Get the home path for the current context.
     */
    public function home(?Request $request = null): string
    {
        return $this->resolveValue('home', $request);
    }

    /**
     * Get the login route name for the current context.
     */
    public function loginRoute(?Request $request = null): string
    {
        return $this->resolveValue('login_route', $request);
    }

    /**
     * Get the two-factor login route name for the current context.
     */
    public function twoFactorRoute(?Request $request = null): string
    {
        return $this->resolveValue('two_factor_route', $request);
    }

    /**
     * Get the password reset route name for the current context.
     */
    public function passwordResetRoute(?Request $request = null): string
    {
        return $this->resolveValue('password_reset_route', $request);
    }

    /**
     * Get the URL prefix for the current context.
     */
    public function prefix(?Request $request = null): string
    {
        return $this->isAdmin($request) ? (string) config('foundry.admin_prefix', 'admin') : '';
    }

    /**
     * Resolve a setting for the active guard, falling back to defaults.
     */
    protected function resolveValue(string $key, ?Request $request = null): string
    {
        return (string) ($this->guardConfig($key, $request) ?: $this->defaultValue($key, $request));
    }

    /**
     * Retrieve a value from the active guard's auth config block.
     */
    protected function guardConfig(string $key, ?Request $request = null): string
    {
        $context = $this->current($request);

        return (string) config("auth.guards.{$context}.{$key}", '');
    }

    /**
     * Get the default value for a given key based on the context.
     */
    protected function defaultValue(string $key, ?Request $request = null): string
    {
        $context = $this->current($request);

        // Check the new structured config first
        $value = config("foundry.guards.{$context}.{$key}");

        if (! is_null($value)) {
            return (string) $value;
        }

        // Fallback to hardcoded legacy defaults
        $isAdmin = $context === 'admin';

        return match ($key) {
            'guard' => $isAdmin ? 'admin' : 'user',
            'password_broker' => $isAdmin ? 'admin' : 'user',
            'home' => $isAdmin ? '/admin' : '/dashboard',
            'login_route' => $isAdmin ? 'admin.login' : 'login',
            'two_factor_route' => $isAdmin ? 'admin.two-factor.login' : 'two-factor.login',
            'password_reset_route' => $isAdmin ? 'admin.password.reset' : 'password.reset',
            default => '',
        };
    }
}
