<?php

namespace Foundry\Billable\Traits;

use Foundry\Billable\Services\PaypalPayment;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;

/**
 * Trait for managing PayPal-related billable operations.
 */
trait ManagePaypal
{
    /**
     * Set up a PayPal payment method for a user.
     */
    protected function setupPaypalPayment(mixed $user, mixed $paymentMethod = null)
    {
        return (new PaypalPayment($user, $paymentMethod))->setup();
    }

    /**
     * Charge a Payable entity using PayPal.
     */
    protected function chargePaypalPayment(mixed $user, Payable $payable, mixed $paymentMethod = null, array $options = []): PaymentResult
    {
        return (new PaypalPayment($user, $paymentMethod))->charge($payable, $paymentMethod, $options);
    }

    /**
     * Remove a PayPal payment method for a user.
     */
    protected function removePaypalPayment(mixed $user)
    {
        return (new PaypalPayment($user))->remove();
    }
}
