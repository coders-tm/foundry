<?php

namespace Foundry\Mandate\Listeners;

use Foundry\Events\SubscriptionRenewed;
use Foundry\Payment\Payable;

/**
 * Handles automatic charging for subscription renewals.
 */
class ChargeRenewalPayment
{
    /**
     * Handle the subscription renewal event.
     */
    public function handle(SubscriptionRenewed $event): void
    {
        $subscription = $event->subscription;

        if (! ($subscription->auto_renewal_enabled ?? false)) {
            return;
        }

        if (! $subscription->provider) {
            return;
        }

        $invoice = $subscription->latestInvoice;

        if (! $invoice) {
            return;
        }

        try {
            $payable = Payable::fromOrder($invoice);
            $result = $subscription->user->charge($payable);

            if ($result->isSuccess()) {
                $invoice->markAsPaid(
                    $subscription->provider,
                    [
                        'id' => $result->getTransactionId(),
                        'status' => $result->getStatus(),
                        'note' => 'Automatic subscription renewal charge',
                    ]
                );

                logger()->info('Auto-renewal charge successful', [
                    'subscription_id' => $subscription->id,
                    'transaction_id' => $result->getTransactionId(),
                    'provider' => $subscription->provider,
                ]);
            }
        } catch (\Exception $e) {
            logger()->error('Auto-renewal charge failed', [
                'subscription_id' => $subscription->id,
                'provider' => $subscription->provider,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
