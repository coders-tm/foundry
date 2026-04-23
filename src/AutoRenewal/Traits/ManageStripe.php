<?php

namespace Foundry\AutoRenewal\Traits;

use Foundry\AutoRenewal\Payments\StripePayment;
use Foundry\AutoRenewal\Services\StripeSubscription;
use Foundry\Models\Subscription;

/**
 * Trait for managing Stripe-related auto-renewal operations.
 *
 * Provides methods for setting up, removing, and charging subscriptions via Stripe.
 */
trait ManageStripe
{
    /**
     * Set up a Stripe subscription for auto-renewal.
     *
     * @param  Subscription  $subscription
     * @return mixed
     *
     * @throws \Exception
     */
    protected function setupStripeSubscription($subscription, ?string $paymentMethodId = null)
    {
        return (new StripeSubscription($subscription, $paymentMethodId))->setup();
    }

    /**
     * Charge a Stripe subscription.
     *
     * @param  Subscription  $subscription
     * @return StripePayment
     *
     * @throws \Exception
     */
    protected function chargeStripeSubscription($subscription, array $options = [])
    {
        return (new StripeSubscription($subscription))->charge($options);
    }

    /**
     * Remove a Stripe subscription.
     *
     * @param  Subscription  $subscription
     * @return Subscription
     *
     * @throws \Exception
     */
    protected function removeStripeSubscription($subscription)
    {
        return (new StripeSubscription($subscription))->remove();
    }
}
