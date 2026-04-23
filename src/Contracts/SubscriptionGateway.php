<?php

namespace Foundry\Contracts;

interface SubscriptionGateway
{
    /**
     * Set up a redirect flow for the subscription
     *
     * @return array Redirect URL to payment provider
     */
    public function setup(mixed $options = null): array;

    /**
     * Get the provider ID from the subscription
     *
     * @return string|null
     */
    public function getProviderId();

    /**
     * Complete the setup process
     *
     * @param  mixed  $setupId
     * @return mixed
     */
    public function completeSetup($setupId);

    /**
     * Create a new subscription with the payment provider
     *
     * @return mixed
     */
    public function create(array $options = []);

    /**
     * Update current subscription
     *
     * @return mixed
     */
    public function update(array $params = []);

    /**
     * Cancel current subscriptions
     *
     * @return bool
     */
    public function cancel(array $metadata = []);

    /**
     * Create a payment
     *
     * @param  string  $description
     * @return mixed
     */
    public function charge($description, array $metadata = []);

    /**
     * Update the subscription plan
     *
     * @return void
     */
    public function updatePlan(bool $hasIntervalChanged, bool $hasPriceChanged);
}
