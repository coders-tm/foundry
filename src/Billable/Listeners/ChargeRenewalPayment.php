<?php

namespace Foundry\Billable\Listeners;

use Foundry\Events\SubscriptionRenewed;
use Foundry\Payment\Payable;

/**
 * Listener for charging renewal payments on subscription renewal.
 *
 * Listens to the SubscriptionRenewed event, constructs a Payable from the
 * subscription invoice, attempts off-session charge via the user's billable
 * model method, and updates the invoice status upon receiving PaymentResult.
 */
class ChargeRenewalPayment
{
    /**
     * Handle the subscription renewal event.
     *
     * @return void
     */
    public function handle(SubscriptionRenewed $event)
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
                        'id'     => $result->getTransactionId(),
                        'status' => $result->getStatus(),
                        'note'   => 'Automatic subscription renewal charge',
                    ]
                );

                logger()->info('Auto-renewal charge successful', [
                    'subscription_id' => $subscription->id,
                    'transaction_id'  => $result->getTransactionId(),
                    'provider'        => $subscription->provider,
                ]);
            }
        } catch (\Exception $e) {
            logger()->error('Auto-renewal charge failed', [
                'subscription_id' => $subscription->id,
                'provider'        => $subscription->provider,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
