<?php

namespace Foundry\Mandate;

use Foundry\Mandate\Models\PaymentMethod as PaymentMethodModel;
use Foundry\Mandate\Responses\RedirectResponse;
use Foundry\Mandate\Services\PaymentService;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Orchestrator for off-session payment method operations.
 */
class BillerManager
{
    /**
     * The target user model instance.
     *
     * @var Model|object
     */
    protected readonly mixed $owner;

    /**
     * The payment method or mandate reference.
     *
     * @var PaymentMethodModel|string|null
     */
    protected mixed $paymentMethod;

    /**
     * Explicit payment provider identifier.
     */
    protected ?string $provider = null;

    /**
     * Create a new BillerManager instance.
     *
     * @param  Model|object  $owner  User model or billable entity instance
     * @param  PaymentMethodModel|string|null  $paymentMethod  Payment method reference or model
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        mixed $owner,
        mixed $paymentMethod = null,
    ) {
        if ($owner === null) {
            throw new \InvalidArgumentException(
                'BillerManager requires an owner instance.'
            );
        }

        $this->owner = $owner;
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * Set the payment provider hint.
     *
     * @param  string|null  $provider  Payment provider name
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
     * @param  PaymentMethodModel|string|null  $paymentMethod  Payment method model or ID
     * @return $this
     */
    public function setPaymentMethod(mixed $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    /**
     * Find a saved payment method by provider and provider ID.
     *
     * @param  string  $provider  Payment provider name
     * @param  string  $providerId  Provider payment method ID
     */
    public function findPaymentMethod(string $provider, string $providerId): PaymentMethodModel
    {
        return PaymentMethodModel::where([
            'user_id' => $this->owner->getKey(),
            'provider' => $provider,
            'provider_id' => $providerId,
        ])->firstOrFail();
    }

    /**
     * Confirm payment method setup with provider.
     *
     * @param  string  $provider  Payment provider name
     * @param  array<string, mixed>  $options  Confirmation options
     *
     * @throws \Exception
     */
    public function confirm(string $provider, array $options): PaymentMethodModel
    {
        return $this->getService($provider)->confirm($options);
    }

    /**
     * Resolve a saved payment method for the owner.
     *
     * @param  PaymentMethodModel|string|null  $paymentMethod  Optional payment method model or ID
     * @param  string|null  $provider  Optional payment provider name
     */
    public function resolvePaymentMethod(mixed $paymentMethod = null, ?string $provider = null): ?PaymentMethodModel
    {
        $ownerId = $this->owner->getKey();

        if ($paymentMethod instanceof PaymentMethodModel) {
            return $paymentMethod;
        }

        if (is_string($paymentMethod) && ! empty($paymentMethod)) {
            $found = PaymentMethodModel::where('user_id', $ownerId)
                ->where(function ($query) use ($paymentMethod) {
                    $query->where('id', $paymentMethod)->orWhere('provider_id', $paymentMethod);
                })
                ->first();

            if ($found) {
                return $found;
            }
        }

        $query = PaymentMethodModel::where('user_id', $ownerId);

        if ($provider) {
            $query->where('provider', $provider);
        }

        return $query->first();
    }

    /**
     * Resolve and return the provider-specific PaymentService instance.
     *
     * @param  string|null  $provider  Payment provider name
     * @param  PaymentMethodModel|string|null  $paymentMethod  Payment method model or ID
     *
     * @throws \InvalidArgumentException
     */
    public function getService(?string $provider = null, mixed $paymentMethod = null): PaymentService
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
            throw new \InvalidArgumentException(
                'Payment provider is required. Set it via setProvider() or pass a provider string.'
            );
        }

        return PaymentGatewayBuilder::make($resolvedProvider)
            ->setUser($this->owner)
            ->setPaymentMethod($targetPm)
            ->build();
    }

    /**
     * Validate the setup request parameters.
     *
     * @param  Request  $request  HTTP request instance
     * @return array<string, mixed>
     */
    public function validate(Request $request): array
    {
        return $this->getService()->validate($request);
    }

    /**
     * Set up a saved payment method.
     *
     * @return PaymentMethodModel|RedirectResponse|null
     *
     * @throws \Exception
     */
    public function setup(): mixed
    {
        return $this->getService()->setup();
    }

    /**
     * Remove the owner's saved payment method.
     *
     * @param  PaymentMethodModel|string|null  $paymentMethod  Optional payment method model or ID
     * @return bool
     *
     * @throws \Exception
     */
    public function removePaymentMethod(mixed $paymentMethod = null): mixed
    {
        $targetPm = $paymentMethod ?? $this->paymentMethod;

        return $this->getService(paymentMethod: $targetPm)->remove();
    }

    /**
     * Charge a Payable entity using the owner's saved payment method.
     *
     * @param  Payable  $payable  The payable model or order instance to charge
     * @param  PaymentMethodModel|string|null  $paymentMethod  Optional payment method instance or provider ID
     * @param  array<string, mixed>  $options  Additional charge options
     *
     * @throws \Exception
     */
    public function charge(Payable $payable, mixed $paymentMethod = null, array $options = []): PaymentResult
    {
        $targetPm = $paymentMethod ?? $this->paymentMethod;
        $pmModel = $this->resolvePaymentMethod($targetPm, $this->provider);

        if (! $pmModel) {
            throw new \Exception('No payment method found for this user.');
        }

        return PaymentGatewayBuilder::make($pmModel->provider)
            ->setUser($this->owner)
            ->setPaymentMethod($pmModel)
            ->charge($payable, $pmModel, $options);
    }

    /**
     * Get all saved payment methods for the owner.
     *
     * @return Collection<int, PaymentMethodModel>
     */
    public function paymentMethods(): Collection
    {
        return $this->owner->paymentMethods;
    }

    /**
     * Handle provider-specific redirect callback.
     *
     * @param  Request  $request  HTTP request instance
     * @return PaymentMethodModel|RedirectResponse|bool
     *
     * @throws \Exception
     */
    public function handleCallback(Request $request): mixed
    {
        return $this->getService()->handleCallback($request);
    }
}
