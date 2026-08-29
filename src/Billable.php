<?php

namespace Foundry;

/**
 * Billable configuration and model registry.
 *
 * Provides a facade-like interface for configuring billable settings,
 * including model bindings and central vs tenant configuration.
 */
class Billable
{
    /**
     * Use central configuration for billable functionality.
     *
     * When true, all billable data is stored in a central location.
     * When false, data is stored per-tenant (if applicable).
     *
     * @var bool
     */
    public static $central = false;

    /**
     * Get the customer model class name.
     */
    public static function getCustomerModel(): string
    {
        return Foundry::$billableCustomerModel;
    }

    /**
     * Get the payment method model class name.
     */
    public static function getPaymentMethodModel(): string
    {
        return Foundry::$billablePaymentMethodModel;
    }

    /**
     * Configure billable to use central storage.
     *
     * @param  bool  $central
     * @return void
     */
    public static function useCentral($central = true)
    {
        self::$central = $central;
    }

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
}

