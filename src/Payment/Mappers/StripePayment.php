<?php

namespace Foundry\Payment\Mappers;

use DateTime;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;
use Stripe\Charge;
use Stripe\PaymentIntent;

class StripePayment extends AbstractPayment
{
    /**
     * Create from Stripe PaymentIntent or Charge object
     *
     * @param  PaymentIntent|Charge|object  $response  Stripe SDK response object
     * @param  PaymentMethod  $paymentMethod  Payment method (required)
     */
    public function __construct($response, PaymentMethod $paymentMethod)
    {
        // Set payment method
        $this->paymentMethod = $paymentMethod;

        // Extract basic info
        $this->transactionId = $response->id;

        // Store amount in BASE currency
        $this->amount = $response->amount / 100;
        $this->currency = strtoupper($response->currency ?? config('app.currency', 'USD'));

        // Map Stripe status to our payment status
        $this->status = match ($response->status) {
            'succeeded', 'requires_capture' => Payment::STATUS_COMPLETED,
            'canceled' => Payment::STATUS_CANCELLED,
            'processing', 'requires_action', 'requires_confirmation', 'requires_payment_method' => Payment::STATUS_PROCESSING,
            default => Payment::STATUS_FAILED,
        };

        $this->note = "Stripe Payment Intent: {$this->transactionId} (Status: {$this->status})";

        if (isset($response->last_payment_error)) {
            $this->note .= " - Error: {$response->last_payment_error->message}";
        }

        if (isset($response->next_action->type)) {
            $this->note .= " - Next action: {$response->next_action->type}";
        }

        // Set processed time
        $this->processedAt = isset($response->created) ? new DateTime("@{$response->created}") : new DateTime;

        // Extract metadata from SDK object and add gateway amount
        $this->metadata = $this->extractMetadata($response);
    }

    /**
     * Extract standardized payment method metadata from Stripe response
     */
    protected function extractMetadata($response): array
    {
        $normalized = [];

        // Get payment method details from multiple sources
        $paymentMethodDetails = null;
        $paymentMethodObject = null;

        // 1. Try to get from expanded payment_method object (if we used expand parameter)
        if (isset($response->payment_method) && is_object($response->payment_method)) {
            $paymentMethodObject = $response->payment_method;
        }

        // 2. Try to get from charges array
        if (isset($response->charges->data[0]->payment_method_details)) {
            $paymentMethodDetails = $response->charges->data[0]->payment_method_details;
        } elseif (isset($response->payment_method_details)) {
            $paymentMethodDetails = $response->payment_method_details;
        }

        // 3. Try to get from expanded latest_charge
        if (! $paymentMethodDetails && isset($response->latest_charge) && is_object($response->latest_charge)) {
            if (isset($response->latest_charge->payment_method_details)) {
                $paymentMethodDetails = $response->latest_charge->payment_method_details;
            }
        }

        // If we have the expanded payment method object, extract details from it
        if ($paymentMethodObject && ! $paymentMethodDetails) {
            $type = $paymentMethodObject->type ?? 'unknown';
            $normalized['payment_method_type'] = $type;

            // Card payments
            if ($type === 'card' && isset($paymentMethodObject->card)) {
                $card = $paymentMethodObject->card;
                $normalized['card_brand'] = ucfirst($card->brand ?? 'card');
                $normalized['last_four'] = $card->last4 ?? null;
                $normalized['exp_month'] = $card->exp_month ?? null;
                $normalized['exp_year'] = $card->exp_year ?? null;
                $normalized['card_funding'] = $card->funding ?? null;
                $normalized['country'] = $card->country ?? null;

                // Wallet information
                if (isset($card->wallet)) {
                    $walletType = $card->wallet->type ?? 'unknown';
                    $normalized['wallet_type'] = $walletType;
                }
            }

            // Bank account payments
            if ($type === 'us_bank_account' && isset($paymentMethodObject->us_bank_account)) {
                $bank = $paymentMethodObject->us_bank_account;
                $normalized['bank_name'] = $bank->bank_name ?? null;
                $normalized['last_four'] = $bank->last4 ?? null;
                $normalized['account_type'] = $bank->account_type ?? null;
            }

            // SEPA Direct Debit
            if ($type === 'sepa_debit' && isset($paymentMethodObject->sepa_debit)) {
                $sepa = $paymentMethodObject->sepa_debit;
                $normalized['bank_name'] = $sepa->bank_code ?? null;
                $normalized['last_four'] = $sepa->last4 ?? null;
                $normalized['country'] = $sepa->country ?? null;
            }
        }

        // If we don't have details yet, try from payment_method_details
        if (! $paymentMethodDetails && empty($normalized)) {
            $normalized['payment_method'] = 'Stripe';

            return $normalized;
        }

        if ($paymentMethodDetails && empty($normalized)) {
            $type = $paymentMethodDetails->type ?? 'unknown';
            $normalized['payment_method_type'] = $type;

            // Card payments
            if ($type === 'card' && isset($paymentMethodDetails->card)) {
                $card = $paymentMethodDetails->card;
                $normalized['card_brand'] = ucfirst($card->brand ?? 'card');
                $normalized['last_four'] = $card->last4 ?? null;
                $normalized['exp_month'] = $card->exp_month ?? null;
                $normalized['exp_year'] = $card->exp_year ?? null;
                $normalized['card_funding'] = $card->funding ?? null;
                $normalized['country'] = $card->country ?? null;

                // Wallet information
                if (isset($card->wallet)) {
                    $walletType = $card->wallet->type ?? 'unknown';
                    $normalized['wallet_type'] = $walletType;
                }
            }

            // Bank account payments
            if ($type === 'us_bank_account' && isset($paymentMethodDetails->us_bank_account)) {
                $bank = $paymentMethodDetails->us_bank_account;
                $normalized['bank_name'] = $bank->bank_name ?? null;
                $normalized['last_four'] = $bank->last4 ?? null;
                $normalized['account_type'] = $bank->account_type ?? null;
            }

            // SEPA Direct Debit
            if ($type === 'sepa_debit' && isset($paymentMethodDetails->sepa_debit)) {
                $sepa = $paymentMethodDetails->sepa_debit;
                $normalized['bank_name'] = $sepa->bank_code ?? null;
                $normalized['last_four'] = $sepa->last4 ?? null;
                $normalized['country'] = $sepa->country ?? null;
            }
        }

        // Add receipt URL if available
        if (isset($response->charges->data[0]->receipt_url)) {
            $normalized['receipt_url'] = $response->charges->data[0]->receipt_url;
        } elseif (isset($response->latest_charge) && is_object($response->latest_charge) && isset($response->latest_charge->receipt_url)) {
            $normalized['receipt_url'] = $response->latest_charge->receipt_url;
        }

        // Build display string
        $normalized['payment_method'] = $this->buildDisplayString($normalized);

        return array_filter($normalized);
    }

    /**
     * Build human-readable payment method display string
     */
    private function buildDisplayString(array $metadata): string
    {
        // Card payment with wallet
        if (isset($metadata['wallet_type'], $metadata['card_brand'], $metadata['last_four'])) {
            $walletName = ucwords(str_replace('_', ' ', $metadata['wallet_type']));

            return "{$walletName} ({$metadata['card_brand']} •••• {$metadata['last_four']})";
        }

        // Card payment
        if (isset($metadata['card_brand'], $metadata['last_four'])) {
            return "{$metadata['card_brand']} •••• {$metadata['last_four']}";
        }

        // Bank account
        if (isset($metadata['bank_name'], $metadata['last_four'])) {
            return "{$metadata['bank_name']} •••• {$metadata['last_four']}";
        }

        // SEPA
        if (isset($metadata['payment_method_type']) && $metadata['payment_method_type'] === 'sepa_debit' && isset($metadata['last_four'])) {
            return "SEPA Direct Debit •••• {$metadata['last_four']}";
        }

        // Klarna
        if (isset($metadata['payment_method_type']) && $metadata['payment_method_type'] === 'klarna') {
            return 'Klarna';
        }

        // Afterpay
        if (isset($metadata['payment_method_type']) && $metadata['payment_method_type'] === 'afterpay_clearpay') {
            return 'Afterpay / Clearpay';
        }

        // Alipay
        if (isset($metadata['payment_method_type']) && $metadata['payment_method_type'] === 'alipay') {
            return 'Alipay';
        }

        // Unknown payment type
        if (! empty($metadata['payment_method_type'])) {
            return ucfirst(str_replace('_', ' ', $metadata['payment_method_type']));
        }

        return 'Stripe';
    }
}
