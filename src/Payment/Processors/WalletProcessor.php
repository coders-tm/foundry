<?php

namespace Foundry\Payment\Processors;

use Foundry\Contracts\PaymentProcessorInterface;
use Foundry\Models\Order;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;
use Foundry\Models\Subscription;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Foundry\Payment\RefundResult;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WalletProcessor extends AbstractPaymentProcessor implements PaymentProcessorInterface
{
    private const SUPPORTED_CURRENCIES = [];

    public function getProvider(): string
    {
        return PaymentMethod::WALLET;
    }

    public function supportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    public function setupPaymentIntent(Request $request, Payable $payable): array
    {
        $user = $request->user();

        if (! $user) {
            throw ValidationException::withMessages([
                'user' => 'User must be authenticated to use wallet payment.',
            ]);
        }

        $amount = $payable->getGrandTotal();
        $currency = config('app.currency', 'USD');
        $walletBalance = $user->getWalletBalance();

        return [
            'message' => 'Wallet payment ready',
            'amount' => $amount,
            'currency' => $currency,
            'wallet_balance' => $walletBalance,
            'formatted_balance' => format_amount($walletBalance, $currency),
            'has_sufficient_balance' => $user->hasWalletBalance($amount),
        ];
    }

    public function confirmPayment(Request $request, Payable $payable): PaymentResult
    {
        $user = $request->user();

        if (! $user) {
            PaymentResult::failed('Wallet payment cannot be processed. Only customer wallet payments are supported at this time.');
        }

        $amount = $payable->getGrandTotal();
        $currency = config('app.currency', 'USD');

        // Check if user has sufficient balance
        if (! $user->hasWalletBalance($amount)) {
            $available = $user->getWalletBalance();
            PaymentResult::failed(
                'Insufficient wallet balance. Required: '.format_amount($amount, $currency).
                    ', Available: '.format_amount($available, $currency)
            );
        }

        try {
            // Determine transaction description based on payable type
            $description = $this->getTransactionDescription($payable);
            $source = $payable->getSource();

            // Debit from wallet
            $transaction = $user->debitWallet(
                amount: $amount,
                source: 'payment',
                description: $description,
                transactionable: $source,
                metadata: [
                    'payable_type' => $source ? get_class($source) : $payable->getType(),
                    'payable_id' => $source?->id ?? $payable->getReferenceId(),
                ]
            );

            return PaymentResult::success(
                paymentData: null, // Wallet doesn't use PaymentInterface
                transactionId: (string) $transaction->id,
                status: 'succeeded',
                metadata: [
                    'payment_method' => 'wallet',
                    'wallet_transaction_id' => $transaction->id,
                    'previous_balance' => $transaction->balance_before,
                    'current_balance' => $transaction->balance_after,
                    'amount' => $transaction->amount,
                ]
            );
        } catch (\Throwable $e) {
            PaymentResult::failed('Wallet payment failed: '.$e->getMessage());
        }
    }

    /**
     * Get transaction description based on payable type
     */
    protected function getTransactionDescription(Payable $payable): string
    {
        $source = $payable->getSource();

        if (! $source) {
            return ucfirst($payable->getType()).' payment';
        }

        $modelClass = class_basename($source);

        return match (true) {
            $source instanceof Subscription => "Subscription payment - {$source->plan->label}",
            $source instanceof Order => "Order payment - Order #{$source->number}",
            default => "{$modelClass} payment - ID #{$source->id}",
        };
    }

    /**
     * {@inheritdoc}
     *
     * Wallet payments cannot be refunded to the original payment method
     * because the wallet is the customer's balance - funds came from the wallet
     * and cannot be "returned" to it. Use Order::refundToWallet() to credit
     * a refund to wallet balance from other payment methods.
     */
    public function supportsRefund(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * Wallet payments are non-refundable. The money was deducted from the
     * customer's wallet balance - there's no external payment gateway to refund.
     * To give money back to the customer, use Order::refundToWallet() which
     * credits the wallet balance.
     */
    public function refund(Payment $payment, ?float $amount = null, ?string $reason = null): RefundResult
    {
        RefundResult::notSupported(
            'Wallet payments cannot be refunded to the original payment method. '.
                'Use the "Refund to Wallet" option to credit the customer\'s wallet balance.'
        );
    }
}
