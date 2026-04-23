<?php

namespace Foundry\AutoRenewal\Services;

use Foundry\AutoRenewal\Exceptions\PaymentIncomplete;
use Foundry\AutoRenewal\Payments\StripePayment;
use Foundry\AutoRenewal\Traits\ManageCustomer;
use Foundry\Foundry;
use Foundry\Models\Subscription as SubscriptionModel;
use Stripe\Exception\CardException;

/**
 * Stripe subscription service for auto-renewal operations.
 *
 * Handles Stripe-specific subscription setup, charging, and removal without
 * relying on Laravel Cashier. Uses the raw Stripe SDK directly.
 */
class StripeSubscription extends Subscription
{
    use ManageCustomer;

    /**
     * The provider constant.
     *
     * @var string
     */
    public const PROVIDER = 'stripe';

    /**
     * Create a new Stripe subscription service instance.
     */
    public function __construct(SubscriptionModel $subscription, ?string $paymentMethodId = null)
    {
        parent::__construct($subscription, $paymentMethodId);
    }

    /**
     * Set up auto-renewal for the subscription.
     *
     * Creates or retrieves the Stripe customer, adds/updates the payment method,
     * and prepares the subscription for auto-renewal.
     *
     * @return SubscriptionModel
     *
     * @throws \Exception
     */
    public function setup()
    {
        // Get or create customer record
        $customer = $this->getOrCreateCustomer(
            $this->subscription->user_id,
            self::PROVIDER
        );

        // If we have a payment method, create or update it
        if ($this->paymentMethod) {
            $this->addOrUpdatePaymentMethod($this->paymentMethod);
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
     * @throws \Exception
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
     * Charge the subscription with Stripe.
     *
     *
     * @throws \Exception
     */
    public function charge(array $options = []): StripePayment
    {
        $paymentMethod = $this->getPaymentMethod(
            $this->subscription->user_id,
            self::PROVIDER
        );

        if (! $paymentMethod) {
            throw new \Exception('No payment method found for charging.');
        }

        $customer = $this->getOrCreateCustomer(
            $this->subscription->user_id,
            self::PROVIDER
        );

        if (! $customer->provider_id) {
            throw new \Exception('No Stripe customer ID found.');
        }

        // Prepare charge parameters
        $chargeParams = array_merge([
            'amount' => (int) ($this->subscription->plan->price * 100),
            'currency' => config('stripe.currency', 'USD'),
            'customer' => $customer->provider_id,
            'payment_method' => $paymentMethod->provider_id,
            'off_session' => true,
            'confirm' => true,
            'description' => "Subscription renewal for {$this->subscription->name}",
            'metadata' => [
                'subscription_id' => $this->subscription->id,
                'user_id' => $this->subscription->user_id,
                'order_id' => $this->subscription->latest_order_id,
            ],
        ], $options);

        try {
            $stripe = Foundry::stripe();
            $paymentIntent = $stripe->paymentIntents->create($chargeParams);

            $payment = new StripePayment($paymentIntent->toArray());

            // If the payment requires action, throw an exception
            if ($payment->status() === 'requires_action') {
                throw new PaymentIncomplete($payment);
            }

            // Return the payment result
            return $payment;
        } catch (CardException $e) {
            // Handle specific card errors that might imply 3DS or other issues
            if ($e->getStripeCode() === 'authentication_required') {
                $paymentIntent = $e->getError()->payment_intent ?? null;
                if ($paymentIntent) {
                    $payment = new StripePayment(is_array($paymentIntent) ? $paymentIntent : $paymentIntent->toArray());
                    throw new PaymentIncomplete($payment);
                }
            }

            // Log the error and re-throw
            // Log the error and re-throw
            logger()->error('Stripe charge failed', [
                'subscription_id' => $this->subscription->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception("Charge failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Create or retrieve the Stripe customer.
     *
     * @return mixed
     *
     * @throws \Exception
     */
    protected function getOrCreateStripeCustomer()
    {
        $customer = $this->getOrCreateCustomer(
            $this->subscription->user_id,
            self::PROVIDER
        );

        if ($customer->provider_id) {
            return Foundry::stripe()->customers->retrieve($customer->provider_id);
        }

        // Create a new Stripe customer
        $stripeCustomer = $this->createStripeCustomer();

        $customer->update([
            'provider_id' => $stripeCustomer->id,
        ]);

        return $stripeCustomer;
    }

    /**
     * Create a new Stripe customer.
     *
     * @return mixed
     *
     * @throws \Exception
     */
    protected function createStripeCustomer()
    {
        $user = $this->subscription->user;

        $params = [
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => [
                'user_id' => $user->id,
            ],
        ];

        if ($user->phone_number ?? false) {
            $params['phone'] = $user->phone_number;
        }

        if ($address = $user->address ?? null) {
            $params['address'] = [
                'line1' => $address->line1,
                'line2' => $address->line2 ?? '',
                'city' => $address->city,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
                'country' => $address->country_code ?? '',
            ];
        }

        return Foundry::stripe()->customers->create($params);
    }

    /**
     * Add or update a payment method for the customer.
     *
     * @param  string  $paymentMethodId
     * @return void
     *
     * @throws \Exception
     */
    protected function addOrUpdatePaymentMethod($paymentMethodId)
    {
        $stripeCustomer = $this->getOrCreateStripeCustomer();
        $stripe = Foundry::stripe();

        // Retrieve the payment method
        $paymentMethod = $stripe->paymentMethods->retrieve($paymentMethodId);

        if (! $paymentMethod) {
            throw new \Exception('Payment method not found.');
        }

        // Use the returned ID in case of aliasing (e.g. pm_card_visa)
        $paymentMethodId = $paymentMethod->id;

        // Attach to customer if not already attached
        if ($paymentMethod->customer !== $stripeCustomer->id) {
            $paymentMethod = $stripe->paymentMethods->attach($paymentMethodId, [
                'customer' => $stripeCustomer->id,
            ]);
        }

        // Set as default payment method
        $stripe->customers->update($stripeCustomer->id, [
            'invoice_settings' => [
                'default_payment_method' => $paymentMethodId,
            ],
        ]);

        // Store payment method reference
        $this->createOrUpdatePaymentMethod(
            $this->subscription->user_id,
            self::PROVIDER,
            $paymentMethodId,
            [
                'card_brand' => $paymentMethod->card->brand ?? '',
                'card_last_four' => $paymentMethod->card->last4 ?? '',
                'card_exp_month' => $paymentMethod->card->exp_month ?? '',
                'card_exp_year' => $paymentMethod->card->exp_year ?? '',
            ]
        );
    }
}
