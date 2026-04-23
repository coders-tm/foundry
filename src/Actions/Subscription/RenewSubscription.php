<?php

namespace Foundry\Actions\Subscription;

use Carbon\Carbon;
use Foundry\Contracts\SubscriptionStatus;
use Foundry\Events\SubscriptionExpired;
use Foundry\Models\PaymentMethod;
use Foundry\Models\Subscription;
use Foundry\Notifications\SubscriptionExpiredNotification;
use Foundry\Services\Period;

class RenewSubscription
{
    /**
     * Renew subscription period.
     *
     * @throws \LogicException
     */
    public function execute(Subscription $subscription): Subscription
    {
        $subscription->assertRenewable();

        // Check if contract is completed
        if ($subscription->total_cycles && $subscription->current_cycle >= $subscription->total_cycles) {
            throw new \LogicException('Contract has reached its total cycles limit.');
        }

        // Detach actions before renewing
        $subscription->detachActions();

        if ($subscription->next_plan) {
            $subscription->plan()->associate($subscription->nextPlan);

            // Sync features from the new plan after downgrade
            $subscription->syncFeaturesFromPlan();

            // Update billing intervals and reset cycle counter for new plan
            $subscription->billing_interval = $subscription->nextPlan->interval->value;
            $subscription->billing_interval_count = $subscription->nextPlan->interval_count;
            $subscription->total_cycles = $subscription->nextPlan->contract_cycles;
            $subscription->current_cycle = 0; // Reset cycle counter when switching to next plan
            $subscription->next_plan = null;
            $subscription->is_downgrade = false;
        }

        // Increment cycle counter (will be 1 if we just reset to 0)
        $subscription->current_cycle = ($subscription->current_cycle ?? 0) + 1;

        // For contract plans with separate billing cycles, use billing interval for renewal
        // Otherwise use the plan's main interval
        $renewalInterval = $subscription->getBillingInterval();
        $renewalIntervalCount = $subscription->getBillingIntervalCount();

        // Renew period - use expires_at as the start for the next period
        // Calculate new period directly to avoid anchor logic interference
        $startDate = $subscription->expires_at ?? Carbon::now();
        $period = new Period(
            $renewalInterval,
            $renewalIntervalCount,
            $startDate
        );

        // Check if this renewal would exceed contract end date for contract plans
        $newExpiresAt = $period->getEndDate();
        if ($subscription->isContract()) {
            // Calculate the actual contract end date from the original start
            $contractPeriod = new Period(
                $subscription->plan->interval->value,
                $subscription->plan->interval_count,
                $subscription->created_at ?? $subscription->starts_at
            );
            $contractEndDate = $contractPeriod->getEndDate();

            // If the new billing period would exceed contract end, cap it at contract end
            if ($newExpiresAt->gt($contractEndDate)) {
                $newExpiresAt = $contractEndDate;
            }
        }

        // Calculate grace period end date from NOW (not from the new expires_at)
        // The grace period starts now since payment is due now
        // Use plan's grace_period_days if available, otherwise fall back to config
        $gracePeriodDays = $subscription->plan->grace_period_days ?? config('foundry.subscription.grace_period_days', 0);

        // If grace period is 0, subscription expires immediately (ends_at = null means no grace period)
        // Otherwise, set ends_at to now + grace period days
        $graceEndsAt = $gracePeriodDays > 0 ? Carbon::now()->addDays($gracePeriodDays) : null;

        // Directly set the new period dates
        $subscription->fill([
            'starts_at' => $period->getStartDate(),
            'expires_at' => $newExpiresAt,
            'ends_at' => $graceEndsAt, // Set grace period end date from now, or null if no grace period
            'trial_ends_at' => null, // Clear trial ends at date
        ])->save();

        // Reset usages for renewal - respects resetable flag
        // Called after save to ensure we use the NEW expires_at
        $subscription->resetUsagesForRenewal();

        // Generate new invoice for the period - subscription enters grace
        $invoice = app(GenerateSubscriptionInvoice::class)->execute($subscription);

        // Check if invoice is already paid (e.g. price 0)
        $isPaid = $invoice && $invoice->is_paid;

        // Try to charge from wallet if balance is available
        if (! $isPaid && $invoice && config('foundry.wallet.auto_charge_on_renewal', true) && $subscription->user) {
            try {
                if ($subscription->user->hasWalletBalance((float) $invoice->grand_total)) {
                    $this->chargeFromWallet($subscription, $invoice);
                    $isPaid = true;

                    // Payment successful, clear grace period
                    $subscription->fill([
                        'status' => SubscriptionStatus::ACTIVE,
                        'ends_at' => null,
                    ])->save();
                } else {
                    throw new \Exception('Insufficient wallet balance.');
                }
            } catch (\Throwable $e) {
                logger()->error('Failed to charge wallet during subscription renewal', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // If not paid and no grace period, expire subscription immediately
        if (! $isPaid && ! $graceEndsAt && $invoice && (float) $invoice->grand_total > 0) {
            $subscription->update([
                'status' => SubscriptionStatus::EXPIRED,
                'ends_at' => null,
            ]);

            // Send notification
            try {
                $subscription->user->notify(new SubscriptionExpiredNotification($subscription));
            } catch (\Throwable $e) {
                logger()->error('Failed to send subscription expired notification', ['error' => $e->getMessage()]);
            }

            // Notify Admins
            try {
                if (function_exists('admin_notify')) {
                    admin_notify(new \Foundry\Notifications\Admins\SubscriptionExpiredNotification($subscription));
                }
            } catch (\Throwable $e) {
                logger()->error('Failed to send admin subscription expired notification', ['error' => $e->getMessage()]);
            }

            // Log
            $subscription->logs()->create([
                'type' => 'expired-notification',
                'message' => 'Notification for expired subscriptions has been successfully sent.',
            ]);

            // Dispatch event
            event(new SubscriptionExpired($subscription));
        }

        // Auto-cancel if contract is now complete
        if ($subscription->total_cycles && $subscription->current_cycle >= $subscription->total_cycles) {
            app(CancelSubscription::class)->cancelNow($subscription);
        }

        return $subscription;
    }

    /**
     * Charge subscription invoice from user's wallet.
     *
     * @param  mixed  $invoice
     *
     * @throws \Exception
     */
    protected function chargeFromWallet(Subscription $subscription, $invoice): void
    {
        $amount = (float) $invoice->grand_total;

        // Debit from wallet
        $transaction = $subscription->user->debitWallet(
            amount: $amount,
            source: 'subscription_renewal',
            description: "Subscription renewal - {$subscription->plan->label}",
            transactionable: $subscription,
            metadata: [
                'subscription_id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'invoice_id' => $invoice->id,
                'cycle' => $subscription->current_cycle,
            ]
        );

        // Mark invoice as paid via wallet
        $walletPaymentMethod = PaymentMethod::where('provider', PaymentMethod::WALLET)->first();

        if ($walletPaymentMethod) {
            $invoice->markAsPaid($walletPaymentMethod->id, [
                'id' => $transaction->id,
                'amount' => $amount,
                'status' => 'succeeded',
                'note' => 'Paid from wallet balance',
                'wallet_transaction_id' => $transaction->id,
            ]);
        }
    }
}
