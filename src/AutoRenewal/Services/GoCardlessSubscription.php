<?php

namespace Foundry\AutoRenewal\Services;

use Foundry\AutoRenewal\Payments\GoCardlessPayment;
use Foundry\AutoRenewal\Traits\ManageCustomer;
use Foundry\Contracts\SubscriptionStatus;
use Foundry\Foundry;
use Foundry\Models\Subscription as SubscriptionModel;
use Foundry\Services\Gateways\GoCardlessSubscriptionGateway;

/**
 * GoCardless subscription service for auto-renewal operations.
 *
 * Handles GoCardless-specific subscription setup, charging, and removal.
 * Uses the Foundry GoCardlessSubscriptionGateway for provider interactions.
 */
class GoCardlessSubscription extends Subscription
{
    use ManageCustomer;

    /**
     * The provider constant.
     *
     * @var string
     */
    public const PROVIDER = 'gocardless';

    /**
     * The GoCardless subscription gateway.
     */
    protected GoCardlessSubscriptionGateway $gateway;

    /**
     * Create a new GoCardless subscription service instance.
     */
    public function __construct(SubscriptionModel $subscription, ?string $mandateId = null)
    {
        parent::__construct($subscription, $mandateId);
        $this->gateway = new GoCardlessSubscriptionGateway($subscription);
    }

    /**
     * Set up auto-renewal for the subscription.
     *
     * Creates or retrieves the GoCardless mandate, and prepares the subscription
     * for auto-renewal with the mandate reference.
     *
     * @return SubscriptionModel
     *
     * @throws \Exception
     */
    public function setup()
    {
        // If we already have a mandate ID, proceed with setup
        if ($this->paymentMethod) {
            return $this->updateMandateAndSetup($this->paymentMethod);
        }

        // Otherwise, we'll return a redirect response for the user to complete
        throw new \Exception('GoCardless requires a redirect flow to be completed.');
    }

    /**
     * Handle GoCardless redirect flow completion and setup mandate.
     *
     * @param  string  $mandateId
     * @return SubscriptionModel
     *
     * @throws \Exception
     */
    protected function updateMandateAndSetup($mandateId)
    {
        // Retrieve mandate details from GoCardless
        $mandate = Foundry::gocardless()->mandates()->get($mandateId);

        if (! $mandate || $mandate->status !== 'active') {
            throw new \Exception('Mandate is not active or not found.');
        }

        // Get or create customer record
        $customer = $this->getOrCreateCustomer(
            $this->subscription->user_id,
            self::PROVIDER
        );

        // Update customer with GoCardless customer ID
        if ($mandate->links->customer) {
            $customer->update([
                'provider_id' => $mandate->links->customer,
            ]);
        }

        // Store payment method (mandate) reference
        $this->createOrUpdatePaymentMethod(
            $this->subscription->user_id,
            self::PROVIDER,
            $mandateId,
            [
                'status' => $mandate->status,
                'reference' => $mandate->reference,
                'scheme' => $mandate->scheme,
                'next_possible_charge_date' => $mandate->next_possible_charge_date,
            ]
        );

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
     * @throws \Exception
     */
    public function remove()
    {
        try {
            // Cancel with the gateway (this updates the subscription status)
            $this->gateway->cancel();
        } catch (\Exception $e) {
            logger()->error('GoCardless cancellation error', [
                'subscription_id' => $this->subscription->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Delete payment method (mandate) reference
        $this->deletePaymentMethod(
            $this->subscription->user_id,
            self::PROVIDER
        );

        // Update subscription status
        if ($this->subscription->status === SubscriptionStatus::PENDING) {
            $this->subscription->status = SubscriptionStatus::INCOMPLETE;
        }

        $this->subscription->auto_renewal_enabled = false;
        $this->subscription->save();

        return $this->subscription;
    }

    /**
     * Charge the subscription with GoCardless.
     *
     *
     * @throws \Exception
     */
    public function charge(array $options = []): GoCardlessPayment
    {
        $paymentMethod = $this->getPaymentMethod(
            $this->subscription->user_id,
            self::PROVIDER
        );

        if (! $paymentMethod) {
            throw new \Exception('No payment method (mandate) found for charging.');
        }

        try {
            // Use the gateway to charge
            $payment = $this->gateway->charge();

            return new GoCardlessPayment([
                'id' => $payment->id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'created_at' => $payment->created_at,
            ]);
        } catch (\Exception $e) {
            logger()->error('GoCardless charge failed', [
                'subscription_id' => $this->subscription->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception("Charge failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Create a GoCardless redirect flow for mandate setup.
     *
     * This should be called before setup() when no mandate exists.
     *
     * @return array
     *
     * @throws \Exception
     */
    public function createRedirectFlow()
    {
        return $this->gateway->setup();
    }
}
