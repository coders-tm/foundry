<?php

namespace Foundry\Billable\Services;

use Foundry\Billable;
use Foundry\Billable\Models\Customer as CustomerModel;
use Foundry\Billable\Models\PaymentMethod as PaymentMethodModel;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Illuminate\Http\Request;

/**
 * Abstract base service for provider-specific billable payment operations.
 *
 * Manages customer registration, payment method attachment, and off-session charging
 * for any Payable entity (Orders, Subscriptions, Invoices, etc.) using saved payment methods.
 */
abstract class BillablePayment
{
    /**
     * The target user model or billable entity instance.
     *
     * @var mixed
     */
    protected mixed $user = null;

    /**
     * The payment method reference (provider-specific ID or model).
     */
    protected mixed $paymentMethod = null;

    /**
     * Create a new billable payment service instance.
     *
     * @param  mixed  $user  User model or billable entity instance
     * @param  mixed  $paymentMethod  Payment method identifier or model instance
     */
    public function __construct(mixed $user = null, mixed $paymentMethod = null)
    {
        $this->user = $user ?? Billable::user();
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * Get the resolved user ID.
     */
    public function getUserId(): string|int|null
    {
        return $this->user?->getKey();
    }

    /**
     * Get or create a customer record for the user with the given provider.
     *
     * @param  string  $userId
     * @param  string  $provider
     * @return CustomerModel
     */
    protected function getOrCreateCustomer($userId, $provider)
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
     * @param  string  $userId
     * @param  string  $provider
     * @return PaymentMethodModel|null
     */
    protected function getPaymentMethod($userId, $provider)
    {
        return PaymentMethodModel::where('user_id', $userId)
            ->where('provider', $provider)
            ->first();
    }

    /**
     * Create or update a payment method record.
     *
     * @param  string  $userId
     * @param  string  $provider
     * @param  string  $providerId
     * @return PaymentMethodModel
     */
    protected function createOrUpdatePaymentMethod($userId, $provider, $providerId, array $options = [])
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
     * @param  string  $userId
     * @param  string  $provider
     * @return bool
     */
    protected function deletePaymentMethod($userId, $provider)
    {
        return (bool) PaymentMethodModel::where('user_id', $userId)
            ->where('provider', $provider)
            ->delete();
    }

    /**
     * Validate setup request parameters for this payment provider.
     */
    public function validate(Request $request): array
    {
        return $request->all();
    }

    /**
     * Confirm payment method setup with provider (e.g. 3DS setup intent confirmation).
     *
     * @param  array  $options  Additional options (e.g. payment_method, setup_intent)
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
     * @return mixed
     *
     * @throws \Exception
     */
    public function handleCallback(Request $request)
    {
        throw new \Exception('Callback handling not supported for this provider.');
    }

    /**
     * Set up a saved payment method with the provider for the user.
     *
     * @return mixed
     *
     * @throws \Exception
     */
    abstract public function setup();

    /**
     * Remove a saved payment method with the provider for the user.
     *
     * @return mixed
     *
     * @throws \Exception
     */
    abstract public function remove();

    /**
     * Charge a Payable entity at the provider using a saved payment method.
     *
     * @param  Payable  $payable  The payable entity to charge
     * @param  mixed  $paymentMethod  Specific payment method model/ID or null to use default
     * @param  array  $options  Additional provider options
     * @return PaymentResult The result of the payment charge
     *
     * @throws \Exception
     */
    abstract public function charge(Payable $payable, mixed $paymentMethod = null, array $options = []): PaymentResult;
}
