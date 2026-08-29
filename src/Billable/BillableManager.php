<?php

namespace Foundry\Billable;

use Foundry\Billable;
use Foundry\Billable\Models\Customer as CustomerModel;
use Foundry\Billable\Models\PaymentMethod as PaymentMethodModel;
use Foundry\Billable\Services\BillablePayment;
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
    /**
     * The user model or billable entity instance.
     *
     * @var mixed
     */
    protected mixed $user = null;

    /**
     * The payment method or mandate reference.
     */
    protected mixed $paymentMethod;

    /**
     * Explicit payment provider identifier.
     */
    protected ?string $provider = null;

    /**
     * Create a new BillableManager instance.
     *
     * @param  mixed  $user  User model or billable entity instance
     * @param  mixed  $paymentMethod  Payment method reference or model
     */
    public function __construct(mixed $user = null, mixed $paymentMethod = null)
    {
        $this->user = $user ?? Billable::user();
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * Set the target user.
     *
     * @param  mixed  $user  User model or billable entity instance
     * @return $this
     */
    public function setUser(mixed $user): self
    {
        $this->user = $user ?? Billable::user();

        return $this;
    }

    /**
     * Set the explicit payment provider hint.
     *
     * @return $this
     */
    public function setProvider(?string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Set the payment method or mandate reference.
     *
     * @return $this
     */
    public function setPaymentMethod(mixed $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    /**
     * Find a saved payment method for the user by provider and provider_id.
     */
    public function findPaymentMethod(string $provider, string $providerId): PaymentMethodModel
    {
        return PaymentMethodModel::where([
            'user_id' => $this->user?->getKey(),
            'provider' => $provider,
            'provider_id' => $providerId,
        ])->firstOrFail();
    }

    /**
     * Confirm payment method setup with provider (e.g. 3DS confirmation).
     *
     * @throws \Exception
     */
    public function confirm(string $provider, array $options): PaymentMethodModel
    {
        return $this->getService($provider)->confirm($options);
    }

    /**
     * Resolve saved PaymentMethod model instance for user.
     */
    public function resolvePaymentMethod(mixed $paymentMethod = null, ?string $provider = null): ?PaymentMethodModel
    {
        $userId = $this->user?->getKey();

        if ($paymentMethod instanceof PaymentMethodModel) {
            return $paymentMethod;
        }

        if (is_string($paymentMethod) && ! empty($paymentMethod)) {
            $found = PaymentMethodModel::where('user_id', $userId)
                ->where(function ($query) use ($paymentMethod) {
                    $query->where('id', $paymentMethod)->orWhere('provider_id', $paymentMethod);
                })
                ->first();

            if ($found) {
                return $found;
            }
        }

        $query = PaymentMethodModel::where('user_id', $userId);

        if ($provider) {
            $query->where('provider', $provider);
        }

        return $query->first();
    }

    /**
     * Resolve the provider-specific BillablePayment service instance cleanly.
     *
     * @throws \InvalidArgumentException
     */
    public function getService(?string $provider = null, mixed $paymentMethod = null): BillablePayment
    {
        $targetPm = $paymentMethod ?? $this->paymentMethod;
        $resolvedProvider = $provider ?? $this->provider;

        if (! $resolvedProvider && $targetPm instanceof PaymentMethodModel) {
            $resolvedProvider = $targetPm->provider;
        }

        if (! $resolvedProvider) {
            $pm = $this->resolvePaymentMethod($targetPm);
            $resolvedProvider = $pm?->provider;
            $targetPm = $targetPm ?? $pm;
        }

        if (! $resolvedProvider) {
            throw new \InvalidArgumentException('Payment provider is required. Please set provider using setProvider().');
        }

        return BillableProcessor::make($resolvedProvider)
            ->setUser($this->user)
            ->setPaymentMethod($targetPm)
            ->build();
    }

    /**
     * Validate the setup request using the provider service.
     */
    public function validate(Request $request): array
    {
        return $this->getService()->validate($request);
    }

    /**
     * Set up a saved payment method with the provider.
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function setup()
    {
        return $this->getService()->setup();
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
        return $this->getService()->remove();
    }

    /**
     * Charge a Payable entity using user's saved payment method.
     *
     * @param  Payable  $payable  The entity being charged
     * @param  mixed  $paymentMethod  Specific payment method or null to use default
     * @param  array  $options  Additional options
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

        return BillableProcessor::make($pmModel->provider)
            ->setUser($this->user)
            ->setPaymentMethod($pmModel)
            ->charge($payable, $pmModel, $options);
    }

    /**
     * Get status of user's billable payment methods.
     */
    public function status(): array
    {
        $userId = $this->user?->getKey();
        $paymentMethod = PaymentMethodModel::where('user_id', $userId)->first();
        $customer = CustomerModel::where('user_id', $userId)->first();

        return [
            'enabled' => $paymentMethod !== null,
            'provider' => $paymentMethod?->provider,
            'payment_method' => $paymentMethod?->toArray(),
            'customer' => $customer?->toArray(),
        ];
    }

    /**
     * Handle provider-specific callback processing.
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function handleCallback(Request $request)
    {
        return $this->getService()->handleCallback($request);
    }
}
