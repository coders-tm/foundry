<?php

namespace Foundry\Billable\Services;

use Foundry\Billable\Billable;
use Foundry\Billable\Traits\ManageCustomer;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;

/**
 * Abstract base service for provider-specific billable payment operations.
 *
 * Manages customer registration, payment method attachment, and off-session charging
 * for any Payable entity (Orders, Subscriptions, Invoices, etc.) using saved payment methods.
 */
abstract class BillablePayment
{
    use ManageCustomer;

    /**
     * The target user model instance or ID.
     */
    protected mixed $user = null;

    /**
     * The resolved user ID.
     */
    protected string|int|null $userId = null;

    /**
     * The payment method reference (provider-specific ID or model).
     */
    protected mixed $paymentMethod = null;

    /**
     * Create a new billable payment service instance.
     */
    public function __construct(mixed $user = null, mixed $paymentMethod = null)
    {
        $this->user = $user ?? \Foundry\Billable::user();

        if (is_numeric($this->user) || is_string($this->user)) {
            $this->userId = $this->user;
        } elseif (is_object($this->user)) {
            $this->userId = $this->user->id ?? $this->user->user_id ?? null;
        }

        if (! $this->userId && \Foundry\Billable::user()) {
            $this->user = \Foundry\Billable::user();
            $this->userId = $this->user->id;
        }

        $this->paymentMethod = $paymentMethod;
    }

    /**
     * Get the resolved user ID.
     */
    public function getUserId(): string|int|null
    {
        return $this->userId;
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
