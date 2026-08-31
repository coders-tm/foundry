<?php

namespace Foundry\Payment\Mappers;

use DateTime;
use Foundry\Models\Payment;
use Foundry\Services\PaymentProvider;
use Paddle\SDK\Entities\Transaction;

class PaddlePayment extends AbstractPayment
{
    /**
     * Create from Paddle Transaction response entity
     */
    public function __construct(Transaction $response)
    {
        $this->paymentMethod = PaymentProvider::PADDLE;

        $this->transactionId = $response->id;
        $this->amount = isset($response->details->totals->grandTotal) ? ((float) $response->details->totals->grandTotal) / 100 : 0.0;
        $this->currency = strtoupper($response->currencyCode->getValue());
        $rawStatus = strtolower($response->status->getValue());
        $this->status = match ($rawStatus) {
            'completed', 'paid' => Payment::STATUS_COMPLETED,
            'canceled', 'cancelled' => Payment::STATUS_CANCELLED,
            'ready', 'billed', 'processing' => Payment::STATUS_PROCESSING,
            default => Payment::STATUS_FAILED,
        };
        $this->note = "Paddle Transaction: {$this->transactionId} (Status: {$this->status})";
        $this->processedAt = $response->createdAt ? DateTime::createFromImmutable($response->createdAt) : new DateTime;
        $this->metadata = $this->extractMetadata($response);
    }

    /**
     * Extract normalized metadata from Paddle response
     *
     * @param  Transaction  $response
     */
    protected function extractMetadata($response): array
    {
        $normalized = [];
        if (! empty($response->payments)) {
            $payment = $response->payments[0];
            if (isset($payment->methodDetails)) {
                $method = $payment->methodDetails;
                $normalized['payment_method_type'] = strtolower($method->type->getValue() ?? 'card');
                if (isset($method->card)) {
                    $normalized['card_brand'] = ucfirst($method->card->type->getValue() ?? 'card');
                    $normalized['last_four'] = $method->card->last4;
                    $normalized['card_exp_month'] = $method->card->expiryMonth;
                    $normalized['card_exp_year'] = $method->card->expiryYear;
                    $normalized['cardholder_name'] = $method->card->cardholderName;
                } elseif ($normalized['payment_method_type'] === 'paypal') {
                    $normalized['paypal_email'] = $method->paypal->email ?? null;
                }
            }
        }
        if (isset($response->checkout->url)) {
            $normalized['checkout_url'] = $response->checkout->url;
        }
        $normalized['payment_method'] = $this->buildDisplayString($normalized);

        return array_filter($normalized);
    }

    /**
     * Build human-readable payment method string
     */
    private function buildDisplayString(array $metadata): string
    {
        if (isset($metadata['card_brand'], $metadata['last_four'])) {
            return "Paddle ({$metadata['card_brand']} •••• {$metadata['last_four']})";
        }

        if (isset($metadata['paypal_email'])) {
            return "Paddle (PayPal: {$metadata['paypal_email']})";
        }

        if (! empty($metadata['payment_method_type'])) {
            return 'Paddle ('.ucfirst($metadata['payment_method_type']).')';
        }

        return 'Paddle';
    }
}
