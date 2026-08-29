<?php

namespace Foundry;

/**
 * Billable configuration and model registry.
 *
 * Provides a facade-like interface for configuring billable settings,
 * including model bindings and resolving authenticatable billable users.
 */
class Billable
{
    /**
     * The callback used to resolve the authenticated billable user.
     *
     * @var callable|null
     */
    public static $userResolver;

    /**
     * Set the customer model class.
     *
     * @param  string  $model
     * @return void
     */
    public static function useCustomerModel($model)
    {
        Foundry::useBillableCustomerModel($model);
    }

    /**
     * Set the payment method model class.
     *
     * @param  string  $model
     * @return void
     */
    public static function usePaymentMethodModel($model)
    {
        Foundry::useBillablePaymentMethodModel($model);
    }

    /**
     * Register a callback to resolve the authenticated billable user.
     */
    public static function resolveUserUsing(callable $callback): void
    {
        static::$userResolver = $callback;
    }

    /**
     * Resolve the authenticated billable user.
     */
    public static function user()
    {
        if (static::$userResolver) {
            return call_user_func(static::$userResolver);
        }

        return auth()->user();
    }
}
