<?php

namespace Foundry\Billable\Traits;

use Foundry\Billable\Services\GoCardlessPayment;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;

/**
 * Trait for managing GoCardless-related billable operations.
 */
trait ManageGoCardless
{
    /**
     * Set up a GoCardless payment method (mandate) for a user.
     */
    protected function setupGoCardlessPayment(mixed $user, ?string $mandateId = null)
    {
        return (new GoCardlessPayment($user, $mandateId))->setup();
    }

    /**
     * Charge a Payable entity using GoCardless.
     */
    protected function chargeGoCardlessPayment(mixed $user, Payable $payable, mixed $mandateId = null, array $options = []): PaymentResult
    {
        return (new GoCardlessPayment($user, $mandateId))->charge($payable, $mandateId, $options);
    }

    /**
     * Remove a GoCardless mandate for a user.
     */
    protected function removeGoCardlessPayment(mixed $user)
    {
        return (new GoCardlessPayment($user))->remove();
    }
}
