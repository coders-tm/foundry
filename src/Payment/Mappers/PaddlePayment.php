<?php

namespace Foundry\Payment\Mappers;

use DateTime;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;
use Paddle\SDK\Entities\Transaction;

class PaddlePayment extends AbstractPayment
{
    /**
     * Create from Paddle transaction response (array or object)
     *
     * @param  array|object  $response
     */
    public function __construct($response, PaymentMethod $paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;

        if ($response instanceof Transaction) {
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
            $this->processedAt = $response->createdAt ?? new DateTime;
            $this->metadata = $this->extractMetadata($response);

            return;
        }

        if ($response instanceof \JsonSerializable) {
            $data = $response->jsonSerialize();
        } else {
            $data = is_array($response) ? $response : (array) $response;
        }

        $this->transactionId = $data['id'] ?? $data['transaction_id'] ?? '';

        // Extract amount & currency
        $details = $data['details'] ?? [];
        $totals = $details['totals'] ?? $data['totals'] ?? [];

        $rawAmount = $totals['grand_total'] ?? $data['amount'] ?? 0;
        $this->amount = is_numeric($rawAmount) ? ((float) $rawAmount) / 100 : (float) $rawAmount;
        if (isset($data['amount_formatted']) || (isset($data['grand_total']) && ! isset($totals['grand_total']))) {
            // If grand_total was provided as standard decimal float/string
            $this->amount = (float) ($data['grand_total'] ?? $data['amount']);
        }

        $this->currency = strtoupper($data['currency_code'] ?? $data['currency'] ?? config('app.currency', 'USD'));

        // Map status
        $rawStatus = strtolower($data['status'] ?? 'completed');
        $this->status = match ($rawStatus) {
            'completed', 'paid' => Payment::STATUS_COMPLETED,
            'canceled', 'cancelled' => Payment::STATUS_CANCELLED,
            'ready', 'billed', 'processing' => Payment::STATUS_PROCESSING,
            default => Payment::STATUS_FAILED,
        };

        $this->note = "Paddle Transaction: {$this->transactionId} (Status: {$this->status})";

        $processedTime = $data['created_at'] ?? $data['updated_at'] ?? null;
        $this->processedAt = $processedTime ? new DateTime($processedTime) : new DateTime;

        $this->metadata = $this->extractMetadata($data);
    }

    /**
     * Extract normalized metadata from Paddle response
     */
    protected function extractMetadata($response): array
    {
        if ($response instanceof Transaction) {
            $normalized = [];
            if (! empty($response->payments)) {
                $payment = $response->payments[0];
                if (isset($payment->methodDetails)) {
                    $method = $payment->methodDetails;
                    $normalized['payment_method_type'] = strtolower($method->type->getValue() ?? 'card');
                    if (isset($method->card)) {
                        $normalized['card_brand'] = ucfirst($method->card->type->getValue() ?? 'card');
                        $normalized['last_four'] = $method->card->last4;
                    } elseif (isset($method->paypal)) {
                        $normalized['paypal_email'] = $method->paypal->email;
                    }
                }
            }
            if (isset($response->checkout->url)) {
                $normalized['checkout_url'] = $response->checkout->url;
            }
            $normalized['payment_method'] = $this->buildDisplayString($normalized);

            return array_filter($normalized);
        }

        if ($response instanceof \JsonSerializable) {
            $data = $response->jsonSerialize();
        } else {
            $data = is_array($response) ? $response : (array) $response;
        }
        $normalized = [];

        $paymentMethodDetails = $data['payments'][0]['method_details'] ?? $data['payment_method_details'] ?? [];

        if (! empty($paymentMethodDetails)) {
            $type = strtolower($paymentMethodDetails['type'] ?? 'card');
            $normalized['payment_method_type'] = $type;

            if (isset($paymentMethodDetails['card'])) {
                $card = $paymentMethodDetails['card'];
                $normalized['card_brand'] = ucfirst($card['type'] ?? $card['brand'] ?? 'card');
                $normalized['last_four'] = $card['last4'] ?? $card['last_four'] ?? null;
            } elseif (isset($paymentMethodDetails['paypal'])) {
                $normalized['paypal_email'] = $paymentMethodDetails['paypal']['email'] ?? null;
            }
        }

        if (isset($data['checkout']['url'])) {
            $normalized['checkout_url'] = $data['checkout']['url'];
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
