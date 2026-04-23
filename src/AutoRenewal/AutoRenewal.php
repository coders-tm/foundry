<?php

namespace Foundry\AutoRenewal;

use Foundry\Foundry;

/**
 * AutoRenewal configuration and model registry.
 *
 * Provides a facade-like interface for configuring auto-renewal settings,
 * including model bindings and central vs tenant configuration.
 */
class AutoRenewal
{
    /**
     * Use central configuration for auto renewal.
     *
     * When true, all auto-renewal data is stored in a central location.
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
        return Foundry::$autoRenewalCustomerModel;
    }

    /**
     * Get the payment method model class name.
     */
    public static function getPaymentMethodModel(): string
    {
        return Foundry::$autoRenewalPaymentMethodModel;
    }

    /**
     * Configure auto-renewal to use central storage.
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
        Foundry::useAutoRenewalCustomerModel($model);
    }

    /**
     * Set the payment method model class.
     *
     * @param  string  $model
     * @return void
     */
    public static function usePaymentMethodModel($model)
    {
        Foundry::useAutoRenewalPaymentMethodModel($model);
    }
}
