<?php

namespace Foundry\Mandate\Services;

use Foundry\Mandate\Models\Customer as CustomerModel;
use Foundry\Mandate\Models\PaymentMethod as PaymentMethodModel;
use Foundry\Mandate\Responses\RedirectResponse;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Abstract base service for provider-specific payment operations.
 */
abstract class PaymentService
{
    /**
     * The target user model instance.
     *
     * @var Model|object|null
     */
    protected mixed $user = null;

    /**
     * The payment method reference.
     *
     * @var PaymentMethodModel|string|null
     */
    protected mixed $paymentMethod = null;

    /**
     * Create a new payment service instance.
     *
     * @param  Model|object|null  $user
     * @param  PaymentMethodModel|string|null  $paymentMethod
     */
    public function __construct(mixed $user, mixed $paymentMethod = null)
    {
        $this->user = $user;
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * Get the resolved user key.
     */
    public function getUserId(): string|int|null
    {
        return $this->user?->getKey();
    }

    /**
     * Get or create a customer record for the user with the given provider.
     *
     * @param  string|int  $userId  The user model key
     * @param  string  $provider  The payment provider name
     */
    protected function getOrCreateCustomer($userId, string $provider): CustomerModel
    {
        return CustomerModel::firstOrCreate(
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
     * @param  string|int  $userId  The user model key
     * @param  string  $provider  The payment provider name
     */
    protected function getPaymentMethod($userId, string $provider): ?PaymentMethodModel
    {
        return PaymentMethodModel::where('user_id', $userId)
            ->where('provider', $provider)
            ->first();
    }

    /**
     * Create or update a payment method record.
     *
     * @param  string|int  $userId  The user model key
     * @param  string  $provider  The payment provider name
     * @param  string  $providerId  The external provider payment method ID
     * @param  array<string, mixed>  $options  Additional options metadata
     */
    protected function createOrUpdatePaymentMethod($userId, string $provider, string $providerId, array $options = []): PaymentMethodModel
    {
        return PaymentMethodModel::updateOrCreate(
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
     * @param  string|int  $userId  The user model key
     * @param  string  $provider  The payment provider name
     */
    protected function deletePaymentMethod($userId, string $provider): bool
    {
        return (bool) PaymentMethodModel::where('user_id', $userId)
            ->where('provider', $provider)
            ->delete();
    }

    /**
     * Validate setup request parameters.
     *
     * @param  Request  $request  The HTTP request instance
     * @return array<string, mixed>
     */
    public function validate(Request $request): array
    {
        return $request->all();
    }

    /**
     * Confirm payment method setup with provider.
     *
     * @param  array<string, mixed>  $options  Confirmation payload options
     *
     * @throws \Exception
     */
    public function confirm(array $options): PaymentMethodModel
    {
        throw new \Exception('Confirmation not supported for this provider.');
    }

    /**
     * Handle provider-specific redirect callback processing.
     *
     * @param  Request  $request  The HTTP request instance
     * @return PaymentMethodModel|RedirectResponse|bool
     *
     * @throws \Exception
     */
    public function handleCallback(Request $request): mixed
    {
        throw new \Exception('Callback handling not supported for this provider.');
    }

    /**
     * Set up a saved payment method.
     *
     * @return PaymentMethodModel|RedirectResponse|null
     *
     * @throws \Exception
     */
    abstract public function setup(): mixed;

    /**
     * Remove a saved payment method.
     *
     * @return bool
     *
     * @throws \Exception
     */
    abstract public function remove(): mixed;

    /**
     * Charge a Payable entity.
     *
     * @param  Payable  $payable  The payable model or order instance
     * @param  PaymentMethodModel|string|null  $paymentMethod  Optional payment method instance or provider ID
     * @param  array<string, mixed>  $options  Additional charge options
     *
     * @throws \Exception
     */
    abstract public function charge(Payable $payable, mixed $paymentMethod = null, array $options = []): PaymentResult;
}
