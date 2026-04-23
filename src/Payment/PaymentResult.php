<?php

namespace Foundry\Payment;

use Foundry\Contracts\PaymentInterface;
use Foundry\Exceptions\PaymentException;

class PaymentResult
{
    public function __construct(
        protected bool $success,
        protected ?PaymentInterface $paymentData = null,
        protected ?string $transactionId = null,
        protected ?string $status = null,
        protected array $metadata = []
    ) {}

    public static function success(
        ?PaymentInterface $paymentData,
        string $transactionId,
        string $status = 'success',
        array $metadata = []
    ): self {
        return new self(
            success: true,
            paymentData: $paymentData,
            transactionId: $transactionId,
            status: $status,
            metadata: $metadata
        );
    }

    public static function failed(string $error, array $metadata = []): never
    {
        throw new PaymentException($error, $metadata);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getPaymentData(): ?PaymentInterface
    {
        return $this->paymentData;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        $result = [
            'success' => $this->success,
        ];

        if ($this->paymentData) {
            $result['payment_data'] = $this->paymentData;
        }

        if ($this->transactionId) {
            $result['transaction_id'] = $this->transactionId;
        }

        if ($this->status) {
            $result['status'] = $this->status;
        }

        if (! empty($this->metadata)) {
            $result = array_merge($result, $this->metadata);
        }

        return $result;
    }
}
