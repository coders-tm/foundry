<?php

namespace Foundry\Payment\Stripe;

use Foundry\Foundry;
use Foundry\Payment\Stripe\Exceptions\IncompletePayment;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Traits\ForwardsCalls;
use JsonSerializable;
use Stripe\PaymentIntent;
use Stripe\PaymentIntent as StripePaymentIntent;

class Payment implements Arrayable, Jsonable, JsonSerializable
{
    use ForwardsCalls;

    /**
     * Create a new Payment instance.
     *
     * @return void
     */
    public function __construct(protected StripePaymentIntent $paymentIntent)
    {
        //
    }

    /**
     * Get the total amount that will be paid.
     */
    public function amount(): string
    {
        return Foundry::formatAmount($this->rawAmount(), $this->paymentIntent->currency);
    }

    /**
     * Get the raw total amount that will be paid.
     */
    public function rawAmount(): int
    {
        return $this->paymentIntent->amount;
    }

    /**
     * The Stripe PaymentIntent client secret.
     */
    public function clientSecret(): string
    {
        return $this->paymentIntent->client_secret;
    }

    /**
     * Capture a payment that is being held for the customer.
     *
     * @return PaymentIntent
     */
    public function capture(array $options = [])
    {
        return $this->paymentIntent->capture($options);
    }

    /**
     * Determine if the payment needs a valid payment method.
     */
    public function requiresPaymentMethod(): bool
    {
        return $this->paymentIntent->status === StripePaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD;
    }

    /**
     * Determine if the payment needs an extra action like 3D Secure.
     */
    public function requiresAction(): bool
    {
        return $this->paymentIntent->status === StripePaymentIntent::STATUS_REQUIRES_ACTION;
    }

    /**
     * Determine if the payment needs to be confirmed.
     */
    public function requiresConfirmation(): bool
    {
        return $this->paymentIntent->status === StripePaymentIntent::STATUS_REQUIRES_CONFIRMATION;
    }

    /**
     * Determine if the payment needs to be captured.
     */
    public function requiresCapture(): bool
    {
        return $this->paymentIntent->status === StripePaymentIntent::STATUS_REQUIRES_CAPTURE;
    }

    /**
     * Cancel the payment.
     *
     * @return PaymentIntent
     */
    public function cancel(array $options = [])
    {
        return $this->paymentIntent->cancel($options);
    }

    /**
     * Determine if the payment was canceled.
     */
    public function isCanceled(): bool
    {
        return $this->paymentIntent->status === StripePaymentIntent::STATUS_CANCELED;
    }

    /**
     * Determine if the payment was successful.
     */
    public function isSucceeded(): bool
    {
        return $this->paymentIntent->status === StripePaymentIntent::STATUS_SUCCEEDED;
    }

    /**
     * Determine if the payment is processing.
     */
    public function isProcessing(): bool
    {
        return $this->paymentIntent->status === StripePaymentIntent::STATUS_PROCESSING;
    }

    /**
     * Validate if the payment intent was successful and throw an exception if not.
     *
     *
     * @throws IncompletePayment
     */
    public function validate(): void
    {
        if ($this->requiresPaymentMethod()) {
            throw IncompletePayment::paymentMethodRequired($this);
        } elseif ($this->requiresAction()) {
            throw IncompletePayment::requiresAction($this);
        } elseif ($this->requiresConfirmation()) {
            throw IncompletePayment::requiresConfirmation($this);
        }
    }

    /**
     * The Stripe PaymentIntent instance.
     *
     * @return PaymentIntent
     */
    public function asStripePaymentIntent(array $expand = [])
    {
        if ($expand) {
            return $this->customer()->stripe()->paymentIntents->retrieve(
                $this->paymentIntent->id, ['expand' => $expand]
            );
        }

        return $this->paymentIntent;
    }

    /**
     * Refresh the PaymentIntent instance from the Stripe API.
     *
     * @return $this
     */
    public function refresh(array $expand = [])
    {
        $this->paymentIntent = $this->asStripePaymentIntent($expand);

        return $this;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->asStripePaymentIntent()->toArray();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Dynamically get values from the Stripe object.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->paymentIntent->{$key};
    }

    /**
     * Dynamically pass missing methods to the PaymentIntent instance.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->forwardCallTo($this->paymentIntent, $method, $parameters);
    }
}
