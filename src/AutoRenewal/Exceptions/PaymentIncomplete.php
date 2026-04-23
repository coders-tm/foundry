<?php

namespace Foundry\AutoRenewal\Exceptions;

use Exception;
use Foundry\AutoRenewal\Payments\Payment;

/**
 * Exception thrown when a payment is incomplete and requires further action.
 */
class PaymentIncomplete extends Exception
{
    /**
     * The payment instance.
     *
     * @var Payment
     */
    public $payment;

    /**
     * Create a new PaymentIncomplete instance.
     *
     * @return void
     */
    public function __construct(Payment $payment)
    {
        $this->payment = $payment;

        parent::__construct('The payment was incomplete and requires additional action.');
    }

    /**
     * Get the payment instance.
     *
     * @return Payment
     */
    public function payment()
    {
        return $this->payment;
    }
}
