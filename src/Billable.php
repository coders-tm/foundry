<?php

namespace Foundry;

/**
 * Billable configuration and model registry.
 *
 * Provides a facade-like interface for configuring billable model bindings.
 */
class Billable
{
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
