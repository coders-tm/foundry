<?php

namespace Foundry\Services\Gateways;

use Foundry\Contracts\SubscriptionGateway;
use Foundry\Models\Order;
use Foundry\Models\PaymentMethod;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Models\User;
use Foundry\Repositories\InvoiceRepository;

class CommonSubscriptionGateway implements SubscriptionGateway
{
    /**
     * @var Subscription
     */
    protected $subscription;

    /**
     * @var Plan
     */
    protected $plan;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var Order
     */
    protected $order;

    /**
     * @var InvoiceRepository
     */
    protected $upcomingInvoice;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var string
     */
    protected $gateway = 'manual';

    /**
     * Create a new service instance.
     *
     * @param  Subscription  $subscription
     * @return void
     */
    public function __construct($subscription)
    {
        $this->gateway = $subscription->provider ?? 'manual';
        $this->subscription = $subscription;
        $this->plan = $subscription->plan;
        $this->user = $subscription->user;
        $this->order = $subscription->latestInvoice;
        $this->upcomingInvoice = $subscription->upcomingInvoice();
        $this->options = $subscription->options ?? [];
    }

    /**
     * Set up the subscription.
     */
    public function setup(mixed $options = null): array
    {
        $payment = false;
        $redirectUrl = null;

        if ($this->payable()) {
            $payment = true;
            $redirectUrl = $this->getRedirectUrl();
        }

        return array_filter([
            'data' => $this->subscription?->toResponse(['usages', 'next_plan', 'plan']),
            'redirect_url' => $redirectUrl ?? null,
            'message' => $payment ? __('Please contact our reception to make payment and complete your subscription!') : __('You have successfully subscribe to :plan plan.', [
                'plan' => $this->plan->label,
            ]),
        ]);
    }

    protected function payable()
    {
        return $this->order?->has_due && ! in_array($this->gateway, [
            PaymentMethod::MANUAL,
            PaymentMethod::GOCARDLESS,
        ]);
    }

    protected function getRedirectUrl()
    {
        return user_route('/payment/'.$this->order->id, [
            'redirect' => user_route('/billing'),
        ]);
    }

    public function getProviderId()
    {
        return data_get($this->options, $this->gateway.'_provider_id');
    }

    public function completeSetup($setupId)
    {
        // do nothing
    }

    public function create(array $options = [])
    {
        // do nothing
    }

    public function update(array $params = [])
    {
        // do nothing
    }

    public function updatePlan(bool $hasIntervalChanged, bool $hasPriceChanged)
    {
        // do nothing
    }

    public function cancel(array $metadata = [])
    {
        // do nothing
        return true;
    }

    public function charge($description, array $metadata = [])
    {
        // do nothing
    }
}
