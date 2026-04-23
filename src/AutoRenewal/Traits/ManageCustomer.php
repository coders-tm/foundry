<?php

namespace Foundry\AutoRenewal\Traits;

use Foundry\AutoRenewal\AutoRenewal;

/**
 * Trait for managing customer-related operations in auto-renewal.
 *
 * Provides helper methods for retrieving and managing customer data.
 */
trait ManageCustomer
{
    /**
     * Get or create a customer record for the user with the given provider.
     *
     * @param  string  $userId
     * @param  string  $provider
     * @return mixed
     */
    protected function getOrCreateCustomer($userId, $provider)
    {
        $model = AutoRenewal::getCustomerModel();

        return $model::firstOrCreate(
            [
                'user_id' => $userId,
                'provider' => $provider,
            ],
            [
                'options' => [],
            ]
        );
    }

    /**
     * Get a payment method record for the user with the given provider.
     *
     * @param  string  $userId
     * @param  string  $provider
     * @return mixed|null
     */
    protected function getPaymentMethod($userId, $provider)
    {
        $model = AutoRenewal::getPaymentMethodModel();

        return $model::where('user_id', $userId)
            ->where('provider', $provider)
            ->first();
    }

    /**
     * Create or update a payment method record.
     *
     * @param  string  $userId
     * @param  string  $provider
     * @param  string  $providerId
     * @return mixed
     */
    protected function createOrUpdatePaymentMethod($userId, $provider, $providerId, array $options = [])
    {
        $model = AutoRenewal::getPaymentMethodModel();

        return $model::updateOrCreate(
            [
                'user_id' => $userId,
                'provider' => $provider,
            ],
            [
                'provider_id' => $providerId,
                'options' => $options,
            ]
        );
    }

    /**
     * Delete a payment method record.
     *
     * @param  string  $userId
     * @param  string  $provider
     * @return bool
     */
    protected function deletePaymentMethod($userId, $provider)
    {
        $model = AutoRenewal::getPaymentMethodModel();

        return (bool) $model::where('user_id', $userId)
            ->where('provider', $provider)
            ->delete();
    }
}
