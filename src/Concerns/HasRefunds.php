<?php

namespace Foundry\Concerns;

use Foundry\Events\RefundProcessed;
use Foundry\Exceptions\RefundException;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;
use Foundry\Models\Refund;
use Foundry\Payment\Processor;
use Illuminate\Support\Facades\DB;

trait HasRefunds
{
    /**
     * Refund - automatically attempts to refund all refundable payments.
     *
     * @param  string|null  $reason  Reason for refund
     * @param  bool  $toWallet  Force refund of all payments to wallet
     * @return Refund|null Returns the last refund transaction or null if no refund made
     *
     * @throws \Exception
     */
    public function refund(?string $reason = null, bool $toWallet = false)
    {
        // If force valid, refund everything to wallet
        if ($toWallet) {
            return $this->refundToWallet($reason);
        }

        // Find all completed payments that have refundable amount
        // We order by created_at desc to process newest first (though order shouldn't matter for full refund)
        $payments = $this->payments()
            ->where('status', Payment::STATUS_COMPLETED)
            ->where(function ($query) {
                $query->whereNull('refund_amount')
                    ->orWhereRaw('amount > COALESCE(refund_amount, 0)');
            })
            ->orderBy('created_at', 'desc')
            ->get();

        if ($payments->isEmpty()) {
            // No refundable payment found, try wallet refund (cleans up any untracked refundable amount)
            return $this->refundToWallet($reason);
        }

        $lastRefund = null;

        foreach ($payments as $payment) {
            // Skip if somehow already refunded (double check)
            if ($payment->refundable_amount <= 0) {
                continue;
            }

            try {
                // Determine if payment is wallet
                $isWallet = $payment->paymentMethod?->provider === PaymentMethod::WALLET;

                if ($isWallet) {
                    // Refund this specific wallet payment to wallet
                    $lastRefund = $this->refundPaymentToWallet($payment, $reason);
                } else {
                    // Try gateway refund
                    $lastRefund = $this->refundToOriginalPayment($payment, $reason);
                }
            } catch (RefundException $e) {
                // If refund not supported by gateway, fall back to wallet for this payment
                if ($e->isNotSupported()) {
                    $lastRefund = $this->refundPaymentToWallet($payment, $reason);
                } else {
                    throw $e;
                }
            }
        }

        return $lastRefund;
    }

    /**
     * Refund order amount to user's wallet.
     * Consumes the entire refundable amount of the order.
     *
     * @param  string|null  $reason  Reason for refund
     * @return Refund
     *
     * @throws \Exception
     */
    public function refundToWallet(?string $reason = null)
    {
        if (! $this->customer) {
            throw new \Exception('Cannot refund to wallet: Order has no customer.');
        }

        $refundAmount = $this->refundable_amount;

        if ($refundAmount <= 0) {
            throw new \Exception('Refund amount must be greater than zero.');
        }

        return DB::transaction(function () use ($refundAmount, $reason) {
            // Credit the wallet
            $transaction = $this->customer->creditWallet(
                amount: $refundAmount,
                source: 'refund',
                description: "Refund for order #{$this->number}".($reason ? ": {$reason}" : ''),
                transactionable: $this,
                metadata: [
                    'order_id' => $this->id,
                    'order_number' => "#{$this->number}",
                    'refund_reason' => $reason,
                ]
            );

            // Create refund record
            $refund = $this->refunds()->create([
                'amount' => $refundAmount,
                'reason' => $reason,
                'to_wallet' => true,
                'wallet_transaction_id' => $transaction->id,
            ]);

            // Sync order status
            $this->syncCurrentStatus();

            // Fire generic refund event
            event(new RefundProcessed($refund));

            return $refund;
        });
    }

    /**
     * Refund a specific payment to the wallet.
     *
     * @return Refund
     */
    protected function refundPaymentToWallet(Payment $payment, ?string $reason = null)
    {
        if (! $this->customer) {
            throw new \Exception('Cannot refund to wallet: Order has no customer.');
        }

        $refundAmount = $payment->refundable_amount;

        if ($refundAmount <= 0) {
            // Should not happen if filtered correctly, but safely return null or throw?
            // Throwing to match other behaviors
            throw new \Exception('Payment amount must be greater than zero to refund.');
        }

        return DB::transaction(function () use ($payment, $refundAmount, $reason) {
            // Credit the wallet
            $transaction = $this->customer->creditWallet(
                amount: $refundAmount,
                source: 'refund',
                description: "Refund for payment #{$payment->transaction_id}".($reason ? ": {$reason}" : ''),
                transactionable: $this, // Still link to order as transactionable? or payment? Usually order.
                metadata: [
                    'order_id' => $this->id,
                    'order_number' => "#{$this->number}",
                    'payment_id' => $payment->id,
                    'refund_reason' => $reason,
                ]
            );

            // Create refund record attached to payment
            $refund = $this->refunds()->create([
                'amount' => $refundAmount,
                'reason' => $reason,
                'payment_id' => $payment->id,
                'to_wallet' => true,
                'wallet_transaction_id' => $transaction->id,
            ]);

            // Update payment status
            $payment->processRefund($reason);

            // Sync order status
            $this->syncCurrentStatus();

            // Fire generic refund event
            event(new RefundProcessed($refund));

            return $refund;
        });
    }

    /**
     * Refund order amount to the original payment method via payment gateway.
     *
     * @param  Payment  $payment  The payment to refund
     * @param  string|null  $reason  Reason for refund
     * @return Refund
     *
     * @throws \Exception
     * @throws RefundException
     */
    public function refundToOriginalPayment(Payment $payment, ?string $reason = null)
    {
        $refundAmount = $payment->refundable_amount;

        if ($refundAmount <= 0) {
            throw new \Exception('Refund amount must be greater than zero.');
        }

        if ($refundAmount > $this->refundable_amount) {
            throw new \Exception('Refund amount exceeds order refundable amount.');
        }

        // Get the payment method and processor
        $paymentMethod = $payment->paymentMethod;

        if (! $paymentMethod) {
            throw new \Exception('Payment method not found for this payment.');
        }

        // Create processor instance
        $processor = Processor::make($paymentMethod->provider);
        $processor->setPaymentMethod($paymentMethod);

        // Check if processor supports refund
        if (! $processor->supportsRefund()) {
            throw new RefundException(
                "Refunds are not supported for the {$paymentMethod->provider} payment method. ".
                    "Use 'Refund to Wallet' option to credit the customer's wallet balance.",
                ['error_type' => 'not_supported']
            );
        }

        return DB::transaction(function () use ($processor, $payment, $refundAmount, $reason) {
            // Process refund through payment gateway
            $refundResult = $processor->refund($payment, $refundAmount, $reason);

            // Update payment status
            $payment->processRefund($reason);

            // Create refund record
            $refund = $this->refunds()->create([
                'amount' => $refundAmount,
                'reason' => $reason,
                'payment_id' => $payment->id,
                'to_wallet' => false,
                'metadata' => $refundResult->toArray(),
            ]);

            // Sync order status
            $this->syncCurrentStatus();

            return $refund;
        });
    }
}
