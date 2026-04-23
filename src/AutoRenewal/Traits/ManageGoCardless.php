<?php

namespace Foundry\AutoRenewal\Traits;

use Foundry\AutoRenewal\Payments\GoCardlessPayment;
use Foundry\AutoRenewal\Services\GoCardlessSubscription;
use Foundry\Models\Subscription;

/**
 * Trait for managing GoCardless-related auto-renewal operations.
 *
 * Provides methods for setting up, removing, and charging subscriptions via GoCardless.
 */
trait ManageGoCardless
{
    /**
     * Set up a GoCardless subscription for auto-renewal.
     *
     * @param  Subscription  $subscription
     * @return mixed
     *
     * @throws \Exception
     */
    protected function setupGoCardlessSubscription($subscription, ?string $mandateId = null)
    {
        return (new GoCardlessSubscription($subscription, $mandateId))->setup();
    }

    /**
     * Charge a GoCardless subscription.
     *
     * @param  Subscription  $subscription
     * @return GoCardlessPayment
     *
     * @throws \Exception
     */
    protected function chargeGoCardlessSubscription($subscription, array $options = [])
    {
        return (new GoCardlessSubscription($subscription))->charge($options);
    }

    /**
     * Remove a GoCardless subscription.
     *
     * @param  Subscription  $subscription
     * @return Subscription
     *
     * @throws \Exception
     */
    protected function removeGoCardlessSubscription($subscription)
    {
        return (new GoCardlessSubscription($subscription))->remove();
    }
}
