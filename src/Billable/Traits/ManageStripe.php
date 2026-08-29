<?php

namespace Foundry\Billable\Traits;

use Foundry\Billable\Services\StripePayment;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;

/**
 * Trait for managing Stripe-related billable operations.
 */
trait ManageStripe
{
    /**
     * Set up a Stripe payment method for a user.
     */
    protected function setupStripePayment(mixed $user, mixed $paymentMethodId = null)
    {
        return (new StripePayment($user, $paymentMethodId))->setup();
    }

    /**
     * Charge a Payable entity using Stripe.
     */
    protected function chargeStripePayment(mixed $user, Payable $payable, mixed $paymentMethodId = null, array $options = []): PaymentResult
    {
        return (new StripePayment($user, $paymentMethodId))->charge($payable, $paymentMethodId, $options);
    }

    /**
     * Remove a Stripe payment method for a user.
     */
    protected function removeStripePayment(mixed $user)
    {
        return (new StripePayment($user))->remove();
    }
}
