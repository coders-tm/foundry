<?php

namespace Foundry;

/**
 * Billable configuration and model registry.
 *
 * Provides a facade-like interface for configuring billable settings,
 * including model bindings.
 */
class Billable
{
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


