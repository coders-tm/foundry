<?php

namespace Foundry\Actions\Subscription;

use Foundry\Contracts\SubscriptionStatus;
use Foundry\Events\SubscriptionRenewed;
use Foundry\Models\Subscription;

class ProcessSubscriptionPayment
{
    /**
     * Process payment for the subscription.
     *
     * @param  mixed  $paymentMethod  The payment method to use
     * @param  array  $options  Additional options for processing payment
     *
     * @throws \InvalidArgumentException
     */
    public function pay(Subscription $subscription, $paymentMethod, array $options = []): Subscription
    {
        if (empty($paymentMethod)) {
            throw new \InvalidArgumentException('Please provide a payment method.');
        }

        try {
            if ($subscription->hasIncompletePayment()) {
                if ($subscription->expired()) {
                    event(new SubscriptionRenewed($subscription));
                }

                $subscription->loadMissing('latestInvoice');

                $invoice = $subscription->latestInvoice ?? (
                    app(GenerateSubscriptionInvoice::class)->execute($subscription, true, true)
                );

                if ($invoice) {
                    $invoice->markAsPaid($paymentMethod, array_merge([
                        'note' => 'Marked the manual payment as received',
                    ], $options));
                }
            }
        } finally {
            $subscription->fill([
                'status' => SubscriptionStatus::ACTIVE,
                'ends_at' => null,
            ])->save();

            $subscription->syncUsages();
        }

        return $subscription;
    }

    /**
     * Handle payment confirmation callback.
     *
     * @param  mixed|null  $order  The order being confirmed
     */
    public function paymentConfirmation(Subscription $subscription, $order = null): Subscription
    {
        if ($subscription->expired()) {
            event(new SubscriptionRenewed($subscription));
        }

        // Transition from PENDING, GRACE, or EXPIRED to ACTIVE on successful payment
        // Clear grace period (ends_at) when payment is received
        $subscription->fill([
            'status' => SubscriptionStatus::ACTIVE,
            'ends_at' => null,
        ])->save();

        return $subscription;
    }

    /**
     * Handle payment failure callback.
     *
     * @param  mixed|null  $order  The failed order
     */
    public function paymentFailed(Subscription $subscription, $order = null): Subscription
    {
        // Determine the appropriate status based on current state
        if ($subscription->status === SubscriptionStatus::PENDING) {
            // Stay in PENDING or move to INCOMPLETE
            $subscription->fill([
                'status' => SubscriptionStatus::INCOMPLETE,
            ])->save();
        }
        // If ACTIVE, keep status as ACTIVE - onGracePeriod() will handle grace logic

        // TODO: Notify user about payment failure
        // $this->sendPaymentFailedNotification();

        return $subscription;
    }
}
