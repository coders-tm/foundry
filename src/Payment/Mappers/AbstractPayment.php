<?php

namespace Foundry\Payment\Mappers;

use DateTime;
use Foundry\Contracts\PaymentInterface;

abstract class AbstractPayment implements PaymentInterface
{
    protected mixed $paymentMethod = null;

    protected string $transactionId;

    protected float $amount; // Always stored in base currency

    protected string $currency; // Order/checkout currency

    protected string $status;

    protected ?string $note;

    protected ?DateTime $processedAt;

    protected array $metadata;

    /**
     * Extract metadata from payment gateway response and build payment method display string
     * Implement this in child classes to parse provider-specific response data
     *
     * @param  mixed  $response  Payment gateway SDK response object
     * @return array Normalized metadata array (must include 'payment_method' key with display string)
     */
    abstract protected function extractMetadata($response): array;

    public function getPaymentMethod(): mixed
    {
        return $this->paymentMethod;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function getProcessedAt(): ?DateTime
    {
        return $this->processedAt;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get human-readable payment method description from metadata
     * Examples: "Visa •••• 4242", "PayPal (user@example.com)", "UPI (user@upi)"
     */
    public function toString(): string
    {
        return $this->metadata['payment_method'] ?? 'Unknown';
    }

    /**
     * Convert to array format for database storage
     */
    public function toArray(): array
    {
        $provider = is_array($this->paymentMethod)
            ? ($this->paymentMethod['provider'] ?? 'unknown')
            : (string) ($this->paymentMethod ?? 'unknown');

        return [
            'provider' => $provider,
            'transaction_id' => $this->getTransactionId(),
            'status' => $this->getStatus(),
            'note' => $this->getNote(),
            'processed_at' => $this->getProcessedAt(),
            'metadata' => $this->getMetadata() + [
                'gateway_currency' => $this->getCurrency(),
                'gateway_amount' => $this->getAmount(),
            ],
        ];
    }
}
