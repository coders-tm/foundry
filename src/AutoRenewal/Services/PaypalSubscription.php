<?php

namespace Foundry\AutoRenewal\Services;

use Exception;
use Foundry\AutoRenewal\Payments\PaypalPayment;
use Foundry\AutoRenewal\Traits\ManageCustomer;
use Foundry\Foundry;
use Foundry\Models\Subscription as SubscriptionModel;

/**
 * PayPal subscription service for auto-renewal operations.
 *
 * Handles PayPal-specific subscription setup, charging, and removal.
 * Uses the srmklive/paypal SDK via Foundry::paypal().
 */
class PaypalSubscription extends Subscription
{
    use ManageCustomer;

    /**
     * The provider constant.
     *
     * @var string
     */
    public const PROVIDER = 'paypal';

    /**
     * Create a new PayPal subscription service instance.
     */
    public function __construct(SubscriptionModel $subscription, mixed $paymentMethod = null)
    {
        parent::__construct($subscription, $paymentMethod);
    }

    /**
     * Set up auto-renewal for the subscription.
     *
     * @return SubscriptionModel
     *
     * @throws Exception
     */
    public function setup()
    {
        // Get or create customer record
        $this->getOrCreateCustomer(
            $this->subscription->user_id,
            self::PROVIDER
        );

        // If we have a payment method (Vault ID or Order ID for vaulting), store it
        if ($this->paymentMethod) {
            $this->createOrUpdatePaymentMethod(
                $this->subscription->user_id,
                self::PROVIDER,
                $this->paymentMethod,
                [
                    'type' => 'paypal',
                ]
            );
        }

        // Mark subscription as having auto-renewal enabled
        $this->subscription->auto_renewal_enabled = true;
        $this->subscription->save();

        return $this->subscription;
    }

    /**
     * Remove auto-renewal from the subscription.
     *
     * @return SubscriptionModel
     *
     * @throws Exception
     */
    public function remove()
    {
        // Delete payment method record
        $this->deletePaymentMethod(
            $this->subscription->user_id,
            self::PROVIDER
        );

        // Disable auto-renewal
        $this->subscription->auto_renewal_enabled = false;
        $this->subscription->save();

        return $this->subscription;
    }

    /**
     * Charge the subscription with PayPal.
     *
     *
     * @throws Exception
     */
    public function charge(array $options = []): PaypalPayment
    {
        $paymentMethod = $this->getPaymentMethod(
            $this->subscription->user_id,
            self::PROVIDER
        );

        if (! $paymentMethod) {
            throw new Exception('No PayPal payment method found for charging.');
        }

        try {
            $paypal = Foundry::paypal();

            // Standard PayPal REST v2 Charge (Capture) logic
            // Assuming the paymentMethod->provider_id is a Vault ID or a reference that can be charged off-session

            $data = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => config('paypal.currency', 'USD'),
                            'value' => number_format($this->subscription->plan->price, 2, '.', ''),
                        ],
                        'description' => "Subscription renewal for {$this->subscription->name}",
                        'reference_id' => $this->subscription->id,
                    ],
                ],
                'payment_source' => [
                    'paypal' => [
                        'vault_id' => $paymentMethod->provider_id,
                    ],
                ],
            ];

            // Merge with additional options
            $data = array_merge_recursive($data, $options);

            $response = $paypal->createOrder($data);

            if (isset($response['id']) && $response['status'] === 'CREATED') {
                // For vaulted payments, we try to capture immediately if set up for off-session
                // Note: Actual SDK method might vary depending on the version of srmklive/paypal
                $response = $paypal->capturePaymentOrder($response['id']);
            }

            if (! isset($response['id']) || (isset($response['status']) && $response['status'] === 'FAILED')) {
                throw new Exception('PayPal charge failed: '.json_encode($response));
            }

            return new PaypalPayment($response);
        } catch (Exception $e) {
            logger()->error('PayPal charge failed', [
                'subscription_id' => $this->subscription->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Charge failed: {$e->getMessage()}", 0, $e);
        }
    }
}
