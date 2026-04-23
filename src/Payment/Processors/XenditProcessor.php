<?php

namespace Foundry\Payment\Processors;

use Foundry\Contracts\PaymentProcessorInterface;
use Foundry\Foundry;
use Foundry\Models\ExchangeRate;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;
use Foundry\Payment\CallbackResult;
use Foundry\Payment\Mappers\XenditPayment;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Xendit\Invoice\Invoice;

class XenditProcessor extends AbstractPaymentProcessor implements PaymentProcessorInterface
{
    private const SUPPORTED_CURRENCIES = ['IDR', 'PHP', 'VND', 'THB', 'MYR'];

    public function getProvider(): string
    {
        return PaymentMethod::XENDIT;
    }

    public function supportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    public function handleSuccessCallback(Request $request): CallbackResult
    {
        try {
            // Get state ID from query parameter
            $stateId = $request->query('state');

            if (! $stateId) {
                Log::error('Xendit success callback: Missing state parameter');
                CallbackResult::failed(
                    message: 'Invalid payment session. Please try again.'
                );
            }

            // Retrieve payment from database using uuid
            $payment = Payment::where('uuid', $stateId)->first();

            if (! $payment) {
                Log::error('Xendit success callback: Payment not found', [
                    'state_id' => $stateId,
                ]);
                CallbackResult::failed(
                    message: 'Payment session expired or not found. Please try again.'
                );
            }

            $paymentState = $payment->metadata;
            $invoiceId = $paymentState['invoice_id'] ?? null;

            if (! $invoiceId) {
                Log::error('Xendit success callback: Invoice ID missing in payment metadata', [
                    'payment_id' => $payment->id,
                ]);
                CallbackResult::failed(
                    message: 'Invalid payment data. Please contact support.'
                );
            }

            // Verify payment with Xendit API
            $xendit = Foundry::xendit();

            /** @var Invoice $invoice */
            $invoice = $xendit->getInvoice($invoiceId);

            logger('Xendit success callback retrieved invoice:', [$invoice]);

            // Convert Invoice object to array for consistent handling
            if (is_object($invoice)) {
                $invoice = json_decode(json_encode($invoice), true);
            }

            // Check if payment is actually successful
            if (! in_array($invoice['status'], ['PAID', 'SETTLED'])) {
                Log::warning('Xendit success callback: Invoice not paid', [
                    'invoice_id' => $paymentState['invoice_id'],
                    'status' => $invoice['status'],
                ]);
                CallbackResult::failed(
                    message: 'Payment not completed. Status: '.$invoice['status']
                );
            }

            // Validate invoice data matches our expectations
            if ($invoice['external_id'] !== $paymentState['checkout_token']) {
                Log::error('Xendit success callback: External ID mismatch', [
                    'expected' => $paymentState['checkout_token'],
                    'received' => $invoice['external_id'],
                ]);
                CallbackResult::failed(
                    message: 'Payment validation failed. Please contact support.'
                );
            }

            // Update payment record using mapper
            $paymentData = new XenditPayment($invoice, $this->paymentMethod);
            $payment->update($paymentData->toArray());

            return CallbackResult::success(
                message: 'Payment completed successfully!',
                payment: $payment->fresh()
            );
        } catch (\Throwable $e) {
            Log::error('Xendit success callback error', [
                'error' => $e->getMessage(),
            ]);

            CallbackResult::failed(
                message: 'Payment processing error. Please contact support if your payment was deducted.'
            );
        }
    }

    public function handleCancelCallback(Request $request): CallbackResult
    {
        $payment = null;
        try {
            // Get state ID from query parameter
            $stateId = $request->query('state');

            if ($stateId) {
                // Retrieve payment from database
                $payment = Payment::where('uuid', $stateId)->first();

                if ($payment) {
                    $payment->markAsFailed('Payment cancelled by user');
                }
            }
        } catch (\Throwable $e) {
            Log::error('Xendit cancel callback error', [
                'error' => $e->getMessage(),
            ]);
        }

        return CallbackResult::success(
            message: 'Payment was cancelled. You can try again or choose a different payment method.',
            payment: $payment
        );
    }

    public function setupPaymentIntent(Request $request, Payable $payable): array
    {
        try {
            $xendit = Foundry::xendit();

            // Get the gateway currency and amount from Payable
            $gatewayCurrency = $payable->getCurrency();
            $amount = $payable->getGatewayAmount();
            $baseCurrency = ExchangeRate::getBaseCurrency();

            // Xendit supports: IDR, PHP, VND, THB, MYR
            // If the detected currency is not supported by Xendit, default to IDR
            if (! in_array($gatewayCurrency, $this->supportedCurrencies())) {
                $gatewayCurrency = 'IDR';

                // Convert to IDR if not already in the right currency
                if ($baseCurrency !== 'IDR') {
                    try {
                        $amount = ExchangeRate::convertAmount(
                            $payable->getGrandTotal(),
                            $baseCurrency,
                            'IDR'
                        );
                    } catch (\RuntimeException $e) {
                        throw new \Exception(
                            "Currency {$baseCurrency} is not supported. ".
                                'Missing exchange rate for conversion to IDR. '.
                                'Please add the exchange rate or use IDR currency.'
                        );
                    }
                }
            }

            // Create a pending payment record first to get the uuid
            $payment = Payment::create([
                'paymentable_type' => $payable->isOrder() ? Foundry::$orderModel : get_class($payable->getSource()),
                'paymentable_id' => $payable->getSourceId(),
                'payment_method_id' => $this->getPaymentMethodId(),
                'transaction_id' => 'pending_'.uniqid(), // Placeholder until we get the invoice ID
                'amount' => $payable->getGrandTotal(),
                'status' => Payment::STATUS_PENDING,
                'note' => 'Xendit payment initiated',
                'metadata' => array_merge($payable->getMetadata(), [
                    'amount' => $payable->getGrandTotal(),
                    'currency' => $baseCurrency,
                    'gateway_currency' => $gatewayCurrency,
                    'gateway_amount' => round($amount),
                    'created_at' => now()->toISOString(),
                ]),
            ]);

            // Create invoice using Xendit's Invoice API (simpler and more reliable)
            $invoiceData = [
                'external_id' => $payable->getReferenceId(),
                'amount' => round($amount), // Ensure amount is integer
                'description' => "Order payment for {$payable->getCustomerEmail()}".
                    ($baseCurrency !== $gatewayCurrency ? " (converted from {$baseCurrency})" : ''),
                'invoice_duration' => 86400, // 24 hours
                'customer' => [
                    'given_names' => $payable->getCustomerFirstName(),
                    'surname' => $payable->getCustomerLastName(),
                    'email' => $payable->getCustomerEmail(),
                    'mobile_number' => $payable->getCustomerPhone(),
                ],
                'customer_notification_preference' => [
                    'invoice_created' => ['email'],
                    'invoice_reminder' => ['email'],
                    'invoice_paid' => ['email'],
                ],
                'success_redirect_url' => $this->getSuccessUrl(['state' => $payment->uuid]),
                'failure_redirect_url' => $this->getCancelUrl(['state' => $payment->uuid]),
                'currency' => $gatewayCurrency,
            ];

            $invoice = $xendit->createInvoice($invoiceData);

            // Update the payment record with actual Xendit invoice details
            $payment->update([
                'transaction_id' => $invoice['id'],
                'note' => "Xendit payment initiated (Invoice: {$invoice['id']})",
                'metadata' => array_merge($payment->metadata, [
                    'invoice_id' => $invoice['id'],
                    'external_id' => $invoice['external_id'],
                ]),
            ]);

            return [
                'success' => true,
                'payment_url' => $invoice['invoice_url'],
                'invoice_id' => $invoice['id'],
                'external_id' => $invoice['external_id'],
                'currency' => $gatewayCurrency,
                'amount' => round($amount),
                'state_id' => $payment->uuid,
            ];
        } catch (\Throwable $e) {
            Log::error('Xendit setupPaymentIntent error', array_merge(
                $payable->getMetadata(),
                ['error' => $e->getMessage()]
            ));

            // Provide specific error message for currency issues
            if (strpos($e->getMessage(), 'currency') !== false && strpos($e->getMessage(), 'not configured') !== false) {
                throw new \Exception('Currency not supported. Xendit only supports IDR currency in your account configuration. Please contact Xendit to enable additional currencies or use a different payment method.');
            }

            throw new \Exception('Failed to create Xendit payment: '.$e->getMessage());
        }
    }

    public function confirmPayment(Request $request, Payable $payable): PaymentResult
    {
        // Confirmation is handled via success callback
        return PaymentResult::failed('Direct payment confirmation not supported. Use success callback handling.');
    }
}
