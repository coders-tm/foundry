<?php

namespace Foundry\AutoRenewal\Payments;

/**
 * Base payment wrapper providing a consistent interface for payment objects.
 *
 * This abstract class defines the interface for payment wrappers that normalize
 * responses from different payment providers (Stripe, GoCardless, etc.).
 */
abstract class Payment
{
    /**
     * The underlying payment data.
     *
     * @var array
     */
    protected $data;

    /**
     * Create a new payment instance.
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Get the payment ID.
     */
    abstract public function id(): string;

    /**
     * Get the payment amount in the smallest currency unit.
     */
    abstract public function amount(): int;

    /**
     * Get the payment currency code.
     */
    abstract public function currency(): string;

    /**
     * Get the payment status.
     */
    abstract public function status(): string;

    /**
     * Get the timestamp when the payment was created.
     */
    abstract public function createdAt(): string;

    /**
     * Get the underlying payment data.
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Get the underlying payment data as JSON.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
