<?php

namespace Foundry\Commands;

use Foundry\Contracts\SubscriptionStatus;
use Foundry\Events\ResetFeatureUsages;
use Foundry\Events\SubscriptionRenewed;
use Foundry\Foundry;
use Foundry\Models\Log;
use Foundry\Models\Subscription;
use Illuminate\Console\Command;

class SubscriptionsRenew extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'foundry:subscriptions-renew';

    /**
     * The console command description.
     *
     * This command handles subscription renewals which includes:
     * - Generating invoices for the next billing cycle
     * - Resetting feature usages (credits) based on billing interval
     * - Tracking contract cycles for contract-based plans
     * - Setting grace periods for payment
     *
     * @var string
     */
    protected $description = 'Renew subscriptions and reset feature usages (credits)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $subscriptions = Foundry::$subscriptionModel::query()
            ->active()
            ->where('expires_at', '<=', now());

        $renewedCount = 0;
        $errorCount = 0;

        /** @var Subscription $subscription */
        foreach ($subscriptions->cursor() as $subscription) {
            try {
                $subscription->attachAction('renew');

                // Handle expired trialing subscriptions - mark as expired instead of renewing
                if ($subscription->onTrialExpired()) {
                    $subscription->update(['status' => SubscriptionStatus::EXPIRED]);

                    $subscription->logs()->create([
                        'type' => 'renew',
                        'message' => 'Expired trial subscription marked as expired.',
                    ]);

                    $this->info("Subscription #{$subscription->id} trial expired - marked as expired");
                    $renewedCount++;

                    continue;
                }

                // Get usage data before renewal for event dispatch
                $usagesBeforeRenewal = $subscription->usagesToArray();

                // Renew subscription - this automatically:
                // 1. Resets feature usages (credits reset on each billing cycle)
                // 2. Generates invoice for next period
                // 3. Updates subscription dates based on billing interval
                // 4. Tracks billing cycles for contract plans
                $subscription->renew();

                // Dispatch the SubscriptionRenewed event
                event(new SubscriptionRenewed($subscription));

                // Dispatch the ResetFeatureUsages event (for notification purposes)
                event(new ResetFeatureUsages($subscription, $usagesBeforeRenewal));

                // Log the renewal action with details
                $cycleInfo = $subscription->total_cycles ? "{$subscription->current_cycle}/{$subscription->total_cycles}" : $subscription->current_cycle;

                $subscription->logs()->create([
                    'type' => 'renew',
                    'message' => "Subscription renewed successfully! Cycle {$cycleInfo}. Credits reset.",
                ]);

                $this->info("Subscription #{$subscription->id} renewed! ({$cycleInfo}, Credits reset)");
                $renewedCount++;
            } catch (\Throwable $e) {
                $message = "Subscription #{$subscription->id} unable to renew! {$e->getMessage()}";

                $subscription->logs()->create([
                    'type' => 'renew',
                    'status' => Log::STATUS_ERROR,
                    'message' => $message,
                ]);

                $this->error($message);
                $errorCount++;
            }
        }

        $this->info("\nRenewal complete: {$renewedCount} renewed, {$errorCount} errors");

        return 0;
    }
}
