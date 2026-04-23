<?php

namespace Foundry\AutoRenewal\Listeners;

use Foundry\AutoRenewal\AutoRenewalManager;
use Foundry\Events\SubscriptionRenewed;

/**
 * Listener for charging renewal payments on subscription renewal.
 *
 * Listens to the SubscriptionRenewed event and automatically attempts to charge
 * the subscription via the configured payment provider if auto-renewal is enabled.
 */
class ChargeRenewalPayment
{
    /**
     * Handle the subscription renewal event.
     *
     * @param  \Foundry\Events\Subscription\SubscriptionRenewed  $event
     * @return void
     */
    public function handle(SubscriptionRenewed $event)
    {
        $subscription = $event->subscription;

        // Only charge if auto-renewal is enabled
        if (! ($subscription->auto_renewal_enabled ?? false)) {
            return;
        }

        // Only charge if a provider is configured
        if (! $subscription->provider) {
            return;
        }

        try {
            $manager = new AutoRenewalManager($subscription);
            $payment = $manager->charge();

            // Log successful charge
            logger()->info('Auto-renewal charge successful', [
                'subscription_id' => $subscription->id,
                'payment_id' => $payment->id(),
                'amount' => $payment->amount(),
                'provider' => $subscription->provider,
            ]);
        } catch (\Exception $e) {
            // Log charging error - don't rethrow to avoid disrupting the event
            logger()->error('Auto-renewal charge failed', [
                'subscription_id' => $subscription->id,
                'provider' => $subscription->provider,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
