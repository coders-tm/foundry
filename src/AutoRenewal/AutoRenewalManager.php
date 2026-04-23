<?php

namespace Foundry\AutoRenewal;

use Foundry\AutoRenewal\Traits\ManageGoCardless;
use Foundry\AutoRenewal\Traits\ManagePaypal;
use Foundry\AutoRenewal\Traits\ManageStripe;
use Foundry\Foundry;
use Foundry\Models\Subscription;
use Illuminate\Http\Request;

/**
 * AutoRenewalManager - Main orchestrator for auto-renewal operations.
 *
 * Coordinates subscription setup, removal, and charging across different
 * payment providers (Stripe, GoCardless). Acts as the main entry point
 * for auto-renewal functionality.
 */
class AutoRenewalManager
{
    use ManageGoCardless, ManagePaypal, ManageStripe;

    /**
     * The subscription model instance.
     *
     * @var Subscription
     */
    protected $subscription;

    /**
     * The payment method or mandate reference.
     */
    protected mixed $paymentMethod;

    /**
     * The payment provider name.
     */
    protected ?string $provider;

    /**
     * Create a new AutoRenewalManager instance.
     */
    public function __construct(Subscription $subscription, mixed $paymentMethod = null)
    {
        $this->subscription = $subscription;
        $this->paymentMethod = $paymentMethod;
        $this->provider = $subscription->provider;
    }

    /**
     * Set the payment provider.
     *
     * @return $this
     */
    public function setProvider(?string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Set the payment method or mandate.
     *
     * @return $this
     */
    public function setPaymentMethod(mixed $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    /**
     * Set up the subscription for auto-renewal.
     *
     * Routes to the appropriate provider-specific setup method.
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function setup()
    {
        if (! $this->provider) {
            throw new \Exception('No payment provider configured for this subscription.');
        }

        $method = 'setup'.ucfirst($this->provider).'Subscription';

        if (! method_exists($this, $method)) {
            throw new \Exception("Payment provider `{$this->provider}` doesn't support auto renewal.");
        }

        return $this->$method($this->subscription, $this->paymentMethod);
    }

    /**
     * Remove auto-renewal from the subscription.
     *
     * Routes to the appropriate provider-specific remove method.
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function remove()
    {
        if (! $this->provider) {
            throw new \Exception('No payment provider configured for this subscription.');
        }

        $method = 'remove'.ucfirst($this->provider).'Subscription';

        if (! method_exists($this, $method)) {
            throw new \Exception("Payment provider `{$this->provider}` doesn't support auto renewal removal.");
        }

        return $this->$method($this->subscription);
    }

    /**
     * Charge the subscription.
     *
     * Routes to the appropriate provider-specific charge method.
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function charge(array $options = [])
    {
        if (! $this->provider) {
            throw new \Exception('No payment provider configured for this subscription.');
        }

        $method = 'charge'.ucfirst($this->provider).'Subscription';

        if (! method_exists($this, $method)) {
            throw new \Exception("Payment provider `{$this->provider}` doesn't support charging.");
        }

        return $this->$method($this->subscription, $options);
    }

    /**
     * Get the status of auto-renewal for this subscription.
     */
    public function status(): array
    {
        $paymentMethod = null;
        $customer = null;

        if ($this->provider) {
            $model = AutoRenewal::getPaymentMethodModel();
            $paymentMethod = $model::where('user_id', $this->subscription->user_id)
                ->where('provider', $this->provider)
                ->first();

            $customerModel = AutoRenewal::getCustomerModel();
            $customer = $customerModel::where('user_id', $this->subscription->user_id)
                ->where('provider', $this->provider)
                ->first();
        }

        return [
            'enabled' => $this->subscription->auto_renewal_enabled ?? false,
            'provider' => $this->provider,
            'payment_method' => $paymentMethod?->toArray(),
            'customer' => $customer?->toArray(),
        ];
    }

    /**
     * Handle provider-specific callback processing.
     *
     * Currently used for GoCardless redirect flow completion.
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function handleCallback(Request $request)
    {
        if (! $this->provider) {
            throw new \Exception('No payment provider configured for callback.');
        }

        // For GoCardless, handle redirect flow
        if ($this->provider === 'gocardless') {
            $flowId = $request->get('flow_id');
            if (! $flowId) {
                throw new \Exception('Missing flow_id in callback.');
            }

            // Complete the redirect flow and get mandate ID
            $client = Foundry::gocardless();
            $completedFlow = $client->redirectFlows()->complete($flowId, ['session_token' => $request->get('session_token')]);

            if (! $completedFlow || ! $completedFlow->links->mandate) {
                throw new \Exception('Failed to complete redirect flow.');
            }

            // Setup with the mandate
            $this->setPaymentMethod($completedFlow->links->mandate);

            return $this->setup();
        }

        throw new \Exception("Callback handling not supported for provider: {$this->provider}");
    }
}
