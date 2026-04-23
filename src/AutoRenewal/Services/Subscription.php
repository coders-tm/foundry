<?php

namespace Foundry\AutoRenewal\Services;

use Foundry\Models\Subscription as SubscriptionModel;

/**
 * Abstract base service for provider-specific subscription auto-renewal logic.
 *
 * This class provides the foundation for implementing provider-specific
 * subscription management, including setup, removal, and charging operations.
 */
abstract class Subscription
{
    /**
     * The subscription model instance.
     *
     * @var SubscriptionModel
     */
    protected $subscription;

    /**
     * The payment method reference (provider-specific format).
     */
    protected mixed $paymentMethod;

    /**
     * Create a new subscription service instance.
     */
    public function __construct(SubscriptionModel $subscription, mixed $paymentMethod = null)
    {
        $this->subscription = $subscription;
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * Set up auto-renewal for the subscription with the provider.
     *
     * This method should:
     * - Create or retrieve the customer record with the provider
     * - Register the payment method with the provider
     * - Configure the subscription for automatic renewal
     *
     * @return SubscriptionModel
     *
     * @throws \Exception
     */
    abstract public function setup();

    /**
     * Remove auto-renewal from the subscription with the provider.
     *
     * This method should:
     * - Cancel the subscription with the provider
     * - Clean up any provider-specific resources
     * - Update the subscription model state
     *
     * @return SubscriptionModel
     *
     * @throws \Exception
     */
    abstract public function remove();

    /**
     * Charge the subscription at the provider.
     *
     * This method should:
     * - Retrieve the customer from the provider
     * - Retrieve the payment method from the provider
     * - Attempt to charge the subscription amount
     * - Log the charge attempt and result
     *
     * @return array
     *
     * @throws \Exception
     */
    abstract public function charge(array $options = []);
}
