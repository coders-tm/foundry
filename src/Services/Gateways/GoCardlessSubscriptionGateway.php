<?php

namespace Foundry\Services\Gateways;

use Foundry\Foundry;
use Foundry\Models\Subscription;
use GoCardlessPro\Client as GoCardlessClient;
use GoCardlessPro\Resources\Payment;
use Illuminate\Support\Facades\Log;

class GoCardlessSubscriptionGateway extends CommonSubscriptionGateway
{
    /**
     * @var GoCardlessClient
     */
    protected $provider;

    /**
     * Create a new service instance.
     *
     * @param  Subscription  $subscription
     * @return void
     */
    public function __construct($subscription)
    {
        parent::__construct($subscription);
        $this->gateway = 'gocardless';
        $this->provider = Foundry::gocardless();
    }

    public function getProviderId()
    {
        return data_get($this->options, $this->gateway.'_provider_id');
    }

    /**
     * Set up a redirect flow for the subscription
     */
    public function setup(mixed $options = null): array
    {
        try {
            if ($this->getProviderId()) {
                // If we already have a mandate ID, we can skip the setup process
                // and directly create the subscription
                $this->updatePlan(false, true);

                return parent::setup();
            }

            $description = 'Direct Debit for '.$this->plan->label;
            $redirectUrl = route('payment.gocardless.success', [
                'state' => $this->subscription->id,
            ]);

            // Create the redirect flow
            $redirectFlow = $this->provider->redirectFlows()->create([
                'params' => [
                    'description' => $description,
                    'session_token' => (string) $this->subscription->id,
                    'success_redirect_url' => $redirectUrl,
                    'prefilled_customer' => $this->getCustomerDetails(),
                    'scheme' => $this->getScheme(),
                    'metadata' => [
                        'subscription_id' => (string) $this->subscription->id,
                    ],
                ],
            ]);

            return [
                'data' => $this->subscription?->toResponse(['usages', 'next_plan', 'plan']),
                'flow_id' => $redirectFlow->id,
                'redirect_url' => $redirectFlow->redirect_url,
                'message' => __('You will be redirected to set up Direct Debit payments with GoCardless'),
            ];
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * Complete the redirect flow and set up the mandate
     *
     * @param  string  $flowId
     * @return GoCardlessPro\Resources\RedirectFlow
     */
    public function completeSetup($flowId)
    {
        try {
            // Get redirect flow details
            $redirectFlow = $this->provider->redirectFlows()->get($flowId);

            // Complete the flow
            $flow = $this->provider->redirectFlows()->complete($flowId, [
                'params' => [
                    'session_token' => $redirectFlow->session_token,
                ],
            ]);

            $mandateId = $flow->links->mandate;
            $customerId = $flow->links->customer;

            // Add the mandate ID to the subscription options
            $options = $this->options ?? [];
            $options[$this->gateway.'_provider_id'] = $mandateId;
            $options[$this->gateway.'_flow_id'] = $flow->id;
            $options[$this->gateway.'_customer_id'] = $customerId;

            $this->options = $options;

            $this->subscription->options = $options;
            $this->subscription->save();

            // Create the subscription using the mandate ID
            $this->create();

            // Charge the initial payment immediately
            $this->charge('Initial payment for '.$this->plan->label);

            // Update customer metadata after saving options
            $this->updateCustomerMetadata($customerId);

            return $flow;
        } catch (\Throwable $e) {
            Log::error('GoCardless flow completion error', [
                'flow_id' => $flowId,
                'subscription_id' => $this->subscription->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create a new GoCardless subscription
     *
     * @return object
     */
    public function create(array $options = [])
    {
        try {
            $this->assertProviderId();

            $mandateId = $this->getProviderId();
            $upcomingInvoice = $this->upcomingInvoice;
            $plan = $this->plan;
            $dayOfMonth = $this->subscription->expires_at?->format('j') ?? -1;

            $subscriptionData = [
                'params' => [
                    'amount' => $upcomingInvoice->rawAmount(),
                    'currency' => $this->preferredCurrency($upcomingInvoice->currency),
                    'interval_unit' => $this->mapIntervalToGoCardless($plan->interval->value),
                    'interval' => $plan->interval_count,
                    'day_of_month' => $dayOfMonth > 28 ? -1 : $dayOfMonth,
                    'links' => [
                        'mandate' => $mandateId,
                    ],
                    'metadata' => [
                        'subscription_id' => (string) $this->subscription->id,
                        'source' => config('app.name'),
                    ],
                ],
            ];

            // Merge with any additional options
            if (! empty($options)) {
                $subscriptionData['params'] = array_merge($subscriptionData['params'], $options);
            }

            return $this->provider->subscriptions()->create($subscriptionData);
        } catch (\Throwable $e) {
            Log::error('Failed to create GoCardless subscription', [
                'error' => $e->getMessage(),
                'subscription_id' => $this->subscription->id,
                'mandate_id' => $this->getProviderId(),
            ]);
            throw $e;
        }
    }

    /**
     * Update an existing GoCardless subscription
     *
     * @return object
     */
    public function update(array $params = [])
    {
        try {
            $activeSubscriptions = $this->getActiveSubscriptions();
            $subscriptionId = null;
            if (empty($activeSubscriptions->records)) {
                return null;
            }
            $subscriptionId = $activeSubscriptions->records[0]->id;

            return $this->provider->subscriptions()->update($subscriptionId, [
                'params' => $params,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to update GoCardless subscription', [
                'error' => $e->getMessage(),
                'gocardless_subscription_id' => $subscriptionId,
                'subscription_id' => $this->subscription->id,
            ]);
            throw $e;
        }
    }

    /**
     * Update the subscription plan based on changes
     *
     * @param  bool  $intervalChanged  Whether the interval has changed
     * @param  bool  $priceChanged  Whether the price has changed
     * @return mixed
     */
    public function updatePlan(bool $intervalChanged, bool $priceChanged)
    {
        try {
            $activeSubscriptions = $this->getActiveSubscriptions();

            // For GoCardless, we need to cancel current subscriptions and create a new one
            // if the interval changed (GoCardless doesn't allow interval updates)
            if ($intervalChanged) {
                // Cancel any existing active subscriptions for this mandate
                $this->cancel([
                    'reason' => 'Customer changed subscription plan',
                ]);

                // Create a new subscription with the new plan using the existing mandate
                return $this->create();
            }
            // If only price changed, we can update the existing subscription
            elseif ($priceChanged) {
                // If we have active subscriptions, update the first one
                if (! empty($activeSubscriptions->records)) {
                    // Update the subscription amount in GoCardless
                    return $this->update([
                        'amount' => $this->subscription->upcomingInvoice()->rawAmount(),
                    ]);
                } else {
                    // No active subscriptions found, create a new one
                    return $this->create();
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('Failed to update GoCardless subscription plan', [
                'error' => $e->getMessage(),
                'subscription_id' => $this->subscription->id,
                'mandate_id' => $this->getProviderId(),
            ]);
            throw $e;
        }
    }

    /**
     * Cancel a GoCardless subscription
     *
     * @param  string  $subscriptionId
     * @return object
     */
    protected function cancelSubscription($subscriptionId, array $metadata = [])
    {
        try {
            $params = [];

            if (! empty($metadata)) {
                $params['metadata'] = $metadata;
            } else {
                $params['metadata'] = [
                    'reason' => 'Customer canceled subscription',
                ];
            }

            return $this->provider->subscriptions()->cancel($subscriptionId, [
                'params' => $params,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to cancel GoCardless subscription', [
                'error' => $e->getMessage(),
                'gocardless_subscription_id' => $subscriptionId,
                'subscription_id' => $this->subscription->id,
            ]);
            throw $e;
        }
    }

    /**
     * Get active subscriptions for the mandate
     *
     * @return object
     */
    protected function getActiveSubscriptions()
    {
        try {
            $mandateId = $this->getProviderId();
            if (empty($mandateId)) {
                return (object) ['records' => []];
            }

            return $this->provider->subscriptions()->list([
                'params' => [
                    'mandate' => $mandateId,
                    'status' => 'active,pending_customer_approval',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to get active GoCardless subscriptions', [
                'error' => $e->getMessage(),
                'subscription_id' => $this->subscription->id,
                'mandate_id' => $this->getProviderId(),
            ]);
            throw $e;
        }
    }

    /**
     * Cancel all active subscriptions for the mandate
     *
     * @return bool
     */
    public function cancel(array $metadata = [])
    {
        try {
            $activeSubscriptions = $this->getActiveSubscriptions();

            if (empty($activeSubscriptions->records)) {
                return false;
            }

            foreach ($activeSubscriptions->records as $activeSubscription) {
                $this->cancelSubscription($activeSubscription->id, $metadata);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to cancel active GoCardless subscriptions', [
                'error' => $e->getMessage(),
                'subscription_id' => $this->subscription->id,
                'mandate_id' => $this->getProviderId(),
            ]);
            throw $e;
        }
    }

    /**
     * Charge a one-time payment using the subscription's mandate
     *
     * @param  string|null  $description  Payment description
     * @param  array  $metadata  Additional metadata for the payment
     * @return Payment|null
     *
     * @throws \Exception
     */
    public function charge($description = null, array $metadata = [])
    {
        $this->assertProviderId();

        $order = $this->order;
        $mandateId = $this->getProviderId();
        $amount = $order->rawAmount();

        // Don't process payments below minimum amount
        if ($amount < 100) {
            return null;
        }

        try {
            $description = $description ?? 'Payment for '.$this->plan->label;

            $paymentMetadata = array_merge([
                'order_id' => (string) $this->order->id,
                'source' => config('app.name'),
            ], $metadata);

            $payment = $this->provider->payments()->create([
                'params' => [
                    'amount' => $amount,
                    'currency' => $this->preferredCurrency($order->currency),
                    'description' => $description,
                    'metadata' => $paymentMetadata,
                    'links' => [
                        'mandate' => $mandateId,
                    ],
                ],
            ]);

            // Mark order as pending payment
            $order->markAsPaymentPending(config('gocardless.id'), [
                'id' => $payment->id,
                'status' => $payment->status,
            ]);

            return $payment;
        } catch (\Throwable $e) {
            Log::error('Failed to create GoCardless payment', [
                'error' => $e->getMessage(),
                'subscription_id' => $this->subscription->id,
                'mandate_id' => $mandateId,
            ]);

            throw $e;
        }
    }

    /**
     * Maps internal interval values to GoCardless-compatible interval values
     *
     * @param  string  $interval
     * @return string
     */
    protected function mapIntervalToGoCardless($interval)
    {
        $map = [
            'week' => 'weekly',
            'month' => 'monthly',
            'year' => 'yearly',
        ];

        // Convert to lowercase and remove any trailing 's'
        $normalizedInterval = strtolower(rtrim($interval, 's'));

        if ($normalizedInterval === 'day') {
            throw new \Exception('GoCardless does not support daily recurring payments. Please select a weekly or longer interval.');
        }

        // Return mapped value or default to 'monthly' if not found
        return $map[$normalizedInterval] ?? 'monthly';
    }

    /**
     * Get the preferred currency in uppercase format as required by GoCardless
     *
     * @param  string  $currency
     * @return string
     */
    protected function preferredCurrency($currency = null)
    {
        $currency = strtoupper($currency ?? config('app.currency', 'gbp'));

        // Validate against supported GoCardless currencies
        $supportedCurrencies = ['AUD', 'CAD', 'DKK', 'EUR', 'GBP', 'NZD', 'SEK', 'USD'];

        if (! in_array($currency, $supportedCurrencies)) {
            // If not supported, default to GBP
            Log::warning("Currency {$currency} not supported by GoCardless, defaulting to GBP");

            return 'GBP';
        }

        return $currency;
    }

    /**
     * Get customer details for GoCardless prefilled fields
     *
     * @return array
     */
    protected function getCustomerDetails()
    {
        $user = $this->subscription->user;
        $address = $user->address?->toArray() ?? [];

        return array_filter([
            'email' => $user->email,
            'phone_number' => preg_replace('/[^\d+]/', '', $user->phone_number),
            'given_name' => $user->first_name,
            'family_name' => $user->last_name,
            'address_line1' => $address['line1'] ?? '',
            'city' => $address['city'] ?? '',
            'postal_code' => $address['postal_code'] ?? '',
            'country_code' => $address['country_code'] ?? config('app.country_code'),
        ]);
    }

    /**
     * Get the scheme based on the country code
     * TODO: Implement this method to return the scheme based on the country_code
     *
     * @return string
     */
    protected function getScheme()
    {
        $country = config('app.country_code');

        // Map countries to schemes
        $schemes = config('gocardless.schemes', []);

        return $schemes[$country] ?? 'bacs';
    }

    /**
     * Update customer metadata with user information
     *
     * @param  string  $customerId
     * @return void
     */
    protected function updateCustomerMetadata($customerId)
    {
        try {
            if (empty($customerId)) {
                throw new \Exception('No customer ID available for updating metadata');
            }

            $this->provider->customers()->update($customerId, [
                'params' => [
                    'metadata' => [
                        'user_id' => (string) $this->subscription->user_id,
                        'source' => config('app.name'),
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to update customer metadata: '.$e->getMessage(), [
                'customer_id' => $customerId,
                'subscription_id' => $this->subscription->id,
            ]);
        }
    }

    /**
     * Ensure a provider ID exists before proceeding
     *
     * @return void
     *
     * @throws \Exception If no mandate ID is available
     */
    protected function assertProviderId()
    {
        $mandateId = $this->getProviderId();
        if (empty($mandateId)) {
            throw new \Exception('No mandate ID available for subscription '.$this->subscription->id);
        }
    }
}
