<?php

namespace Foundry\Payment;

use Foundry\Exceptions\PaymentException;
use Foundry\Models\Payment;

class CallbackResult
{
    public function __construct(
        public string $message,
        public ?Payment $payment = null
    ) {}

    public static function success(
        string $message = 'Operation completed successfully',
        ?Payment $payment = null
    ): self {
        return new self(
            message: $message,
            payment: $payment
        );
    }

    public static function failed(string $message, array $metadata = []): never
    {
        throw new PaymentException($message, $metadata);
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function getPaymentData(): ?Payment
    {
        return $this->payment;
    }

    public function getMessageType(): string
    {
        return 'success';
    }

    public function toArray(): array
    {
        return [
            'success' => true,
            'message' => $this->message,
            'payment' => $this->payment,
        ];
    }
}
