<?php

namespace Foundry\Billable;

use Foundry\Billable\Models\Customer as CustomerModel;
use Foundry\Billable\Models\PaymentMethod as PaymentMethodModel;
use Foundry\Billable\Services\GoCardlessPayment;
use Foundry\Billable\Services\PaypalPayment;
use Foundry\Billable\Services\StripePayment;
use Foundry\Billable\Traits\ManageGoCardless;
use Foundry\Billable\Traits\ManagePaypal;
use Foundry\Billable\Traits\ManageStripe;
use Foundry\Foundry;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Illuminate\Http\Request;

/**
 * BillableManager - Main orchestrator for off-session billable payment operations.
 *
 * Manages user payment methods and executes charges against Payable entities
 * (Orders, Subscriptions, Invoices, etc.) using stored payment methods.
 */
class BillableManager
{
    use ManageGoCardless, ManagePaypal, ManageStripe;

    /**
     * The user model or ID instance.
     */
    protected mixed $user;

    /**
     * The resolved user ID.
     */
    protected string|int|null $userId = null;

    /**
     * The payment method or mandate reference.
     */
    protected mixed $paymentMethod;

    /**
     * Optional payment provider hint.
     */
    protected ?string $provider = null;

    /**
     * Create a new BillableManager instance.
     */
    public function __construct(mixed $user = null, mixed $paymentMethod = null)
    {
        $this->user = $user ?? \Foundry\Billable::user();
        $this->paymentMethod = $paymentMethod;

        if (is_numeric($this->user) || is_string($this->user)) {
            $this->userId = $this->user;
        } elseif (is_object($this->user)) {
            $this->userId = $this->user->id ?? $this->user->user_id ?? null;
        }

        if (! $this->userId && \Foundry\Billable::user()) {
            $this->user = \Foundry\Billable::user();
            $this->userId = $this->user->id;
        }
    }

    /**
     * Set the payment provider hint.
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
     * Resolve saved PaymentMethod model instance.
     */
    public function resolvePaymentMethod(mixed $paymentMethod = null, ?string $provider = null): ?PaymentMethodModel
    {
        if ($paymentMethod instanceof PaymentMethodModel) {
            return $paymentMethod;
        }

        $modelClass = \Foundry\Billable::getPaymentMethodModel();

        if (is_string($paymentMethod) && ! empty($paymentMethod)) {
            $found = $modelClass::where('id', $paymentMethod)
                ->orWhere('provider_id', $paymentMethod)
                ->first();

            if ($found) {
                return $found;
            }
        }

        $query = $modelClass::where('user_id', $this->userId);

        if ($provider) {
            $query->where('provider', $provider);
        }

        return $query->first();
    }

    /**
     * Set up a saved payment method with the appropriate provider.
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function setup()
    {
        $provider = $this->provider;

        if (! $provider && $this->paymentMethod instanceof PaymentMethodModel) {
            $provider = $this->paymentMethod->provider;
        }

        if (! $provider) {
            throw new \Exception('No payment provider configured for setup.');
        }

        $service = match ($provider) {
            'stripe' => new StripePayment($this->user, $this->paymentMethod),
            'paypal' => new PaypalPayment($this->user, $this->paymentMethod),
            'gocardless' => new GoCardlessPayment($this->user, $this->paymentMethod),
            default => throw new \Exception("Unsupported payment provider: {$provider}")
        };

        return $service->setup();
    }

    /**
     * Remove a saved payment method.
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function remove()
    {
        $provider = $this->provider;

        if (! $provider && $this->paymentMethod instanceof PaymentMethodModel) {
            $provider = $this->paymentMethod->provider;
        }

        if (! $provider) {
            $pm = $this->resolvePaymentMethod($this->paymentMethod);
            $provider = $pm?->provider;
        }

        if (! $provider) {
            throw new \Exception('No payment provider configured for removal.');
        }

        $service = match ($provider) {
            'stripe' => new StripePayment($this->user, $this->paymentMethod),
            'paypal' => new PaypalPayment($this->user, $this->paymentMethod),
            'gocardless' => new GoCardlessPayment($this->user, $this->paymentMethod),
            default => throw new \Exception("Unsupported payment provider: {$provider}")
        };

        return $service->remove();
    }

    /**
     * Charge a Payable entity using user's saved payment method.
     *
     * @param  Payable  $payable  The entity being charged
     * @param  mixed  $paymentMethod  Specific payment method or null to use default
     * @param  array  $options  Additional options
     * @return PaymentResult
     *
     * @throws \Exception
     */
    public function charge(Payable $payable, mixed $paymentMethod = null, array $options = []): PaymentResult
    {
        $targetPm = $paymentMethod ?? $this->paymentMethod;
        $pmModel = $this->resolvePaymentMethod($targetPm, $this->provider);

        if (! $pmModel) {
            throw new \Exception('No payment method found attached to user.');
        }

        $provider = $pmModel->provider;

        $service = match ($provider) {
            'stripe' => new StripePayment($this->user, $pmModel),
            'paypal' => new PaypalPayment($this->user, $pmModel),
            'gocardless' => new GoCardlessPayment($this->user, $pmModel),
            default => throw new \Exception("Unsupported payment provider: {$provider}")
        };

        return $service->charge($payable, $pmModel, $options);
    }

    /**
     * Get status of user's billable payment methods.
     */
    public function status(): array
    {
        $pmClass = \Foundry\Billable::getPaymentMethodModel();
        $customerClass = \Foundry\Billable::getCustomerModel();

        $paymentMethod = $pmClass::where('user_id', $this->userId)->first();
        $customer = $customerClass::where('user_id', $this->userId)->first();

        return [
            'enabled' => $paymentMethod !== null,
            'provider' => $paymentMethod?->provider,
            'payment_method' => $paymentMethod?->toArray(),
            'customer' => $customer?->toArray(),
        ];
    }

    /**
     * Handle provider-specific callback processing (e.g. GoCardless redirect flow).
     */
    public function handleCallback(Request $request)
    {
        if (! $this->provider) {
            throw new \Exception('No payment provider configured for callback.');
        }

        if ($this->provider === 'gocardless') {
            $flowId = $request->get('flow_id');
            if (! $flowId) {
                throw new \Exception('Missing flow_id in callback.');
            }

            $client = Foundry::gocardless();
            $completedFlow = $client->redirectFlows()->complete($flowId, ['session_token' => $request->get('session_token')]);

            if (! $completedFlow || ! $completedFlow->links->mandate) {
                throw new \Exception('Failed to complete redirect flow.');
            }

            $this->setPaymentMethod($completedFlow->links->mandate);

            return $this->setup();
        }

        throw new \Exception("Callback handling not supported for provider: {$this->provider}");
    }
}
