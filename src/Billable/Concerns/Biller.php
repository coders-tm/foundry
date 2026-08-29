<?php

namespace Foundry\Billable\Concerns;

use Foundry\Billable\BillableManager;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;

trait Biller
{
    /**
     * Return a BillableManager scoped to this model instance.
     *
     * The user is bound at construction time — no silent fallback to a
     * request-resolved user. Use this for chained setup/removal flows.
     *
     *   $user->billable('stripe')->setup();
     *   $user->billable()->status();
     */
    public function billable(?string $provider = null): BillableManager
    {
        $manager = new BillableManager($this);

        if ($provider !== null) {
            $manager->setProvider($provider);
        }

        return $manager;
    }

    /**
     * Charge a Payable using this user's saved payment method.
     *
     * Shortcut for the most common case — readable at the call-site:
     *
     *   $user->charge($invoice);
     *   $user->charge($invoice, $specificPaymentMethod);
     */
    public function charge(
        Payable $payable,
        mixed $paymentMethod = null,
        array $options = []
    ): PaymentResult {
        return $this->billable()->charge($payable, $paymentMethod, $options);
    }
}
