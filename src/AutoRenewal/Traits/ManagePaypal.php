<?php

namespace Foundry\AutoRenewal\Traits;

use Foundry\AutoRenewal\Payments\PaypalPayment;
use Foundry\AutoRenewal\Services\PaypalSubscription;
use Foundry\Models\Subscription;

/**
 * Trait for managing PayPal-related auto-renewal operations.
 *
 * Provides methods for setting up, removing, and charging subscriptions via PayPal.
 */
trait ManagePaypal
{
    /**
     * Set up a PayPal subscription for auto-renewal.
     *
     * @param  Subscription  $subscription
     * @return mixed
     *
     * @throws \Exception
     */
    protected function setupPaypalSubscription($subscription, mixed $paymentMethod = null)
    {
        return (new PaypalSubscription($subscription, $paymentMethod))->setup();
    }

    /**
     * Charge a PayPal subscription.
     *
     * @param  Subscription  $subscription
     * @return PaypalPayment
     *
     * @throws \Exception
     */
    protected function chargePaypalSubscription($subscription, array $options = [])
    {
        return (new PaypalSubscription($subscription))->charge($options);
    }

    /**
     * Remove a PayPal subscription.
     *
     * @param  Subscription  $subscription
     * @return Subscription
     *
     * @throws \Exception
     */
    protected function removePaypalSubscription($subscription)
    {
        return (new PaypalSubscription($subscription))->remove();
    }
}
