<?php

namespace Foundry\Payment\Processors;

use Foundry\Contracts\PaymentProcessorInterface;
use Foundry\Models\PaymentMethod;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Illuminate\Http\Request;

class ManualProcessor extends AbstractPaymentProcessor implements PaymentProcessorInterface
{
    private const SUPPORTED_CURRENCIES = [];

    public function getProvider(): string
    {
        return PaymentMethod::MANUAL;
    }

    public function supportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    public function setupPaymentIntent(Request $request, Payable $payable): array
    {
        // Manual payments don't need a setup intent
        return [
            'message' => 'Manual payment ready for processing',
            'amount' => $payable->getGrandTotal(),
            'currency' => strtoupper(config('app.currency', 'USD')),
        ];
    }

    public function confirmPayment(Request $request, Payable $payable): PaymentResult
    {
        $request->validate([
            'reference_number' => 'required|string',
            'payment_notes' => 'nullable|string',
        ]);

        try {
            // Manual payment doesn't have external payment data
            // Return minimal data for the controller to handle
            return PaymentResult::success(
                paymentData: null, // Manual payments don't have payment data
                transactionId: $request->reference_number,
                status: 'pending_verification',
                metadata: ['notes' => $request->payment_notes]
            );
        } catch (\Throwable $e) {
            PaymentResult::failed($e->getMessage());
        }
    }
}
