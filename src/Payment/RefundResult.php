<?php

namespace Foundry\Payment;

use Foundry\Exceptions\RefundException;

class RefundResult
{
    public function __construct(
        protected bool $success,
        protected ?string $refundId = null,
        protected ?float $amount = null,
        protected ?string $status = null,
        protected array $metadata = []
    ) {}

    /**
     * Create a successful refund result.
     */
    public static function success(
        string $refundId,
        float $amount,
        string $status = 'refunded',
        array $metadata = []
    ): self {
        return new self(
            success: true,
            refundId: $refundId,
            amount: $amount,
            status: $status,
            metadata: $metadata
        );
    }

    /**
     * Create a failed refund result that throws an exception.
     */
    public static function failed(string $error, array $metadata = []): never
    {
        throw new RefundException($error, $metadata);
    }

    /**
     * Create a refund result indicating the operation is not supported.
     */
    public static function notSupported(string $reason = 'Refund not supported for this payment method'): never
    {
        throw new RefundException($reason, ['error_type' => 'not_supported']);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getRefundId(): ?string
    {
        return $this->refundId;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
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

        if ($this->refundId) {
            $result['refund_id'] = $this->refundId;
        }

        if ($this->amount !== null) {
            $result['amount'] = $this->amount;
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
