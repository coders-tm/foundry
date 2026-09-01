<?php

namespace Foundry\Commands;

use Foundry\Events\ResetFeatureUsages;
use Foundry\Foundry;
use Foundry\Models\Log;
use Illuminate\Console\Command;

class ResetSubscriptionsUsages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'foundry:reset-subscriptions-usages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset the subscription usages for credit reset schedules';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $creditResetSubscriptions = Foundry::$subscriptionModel::query()
            ->active()
            ->whereNotNull('credit_resets_at')
            ->where('credit_resets_at', '<=', now())
            ->where('expires_at', '>', now());

        foreach ($creditResetSubscriptions->cursor() as $subscription) {
            try {
                $this->resetSubscriptionUsages($subscription);

                $subscription->advanceCreditResetsAt()->save();

                $subscription->logs()->create([
                    'type' => 'credit-reset',
                    'message' => 'Credit usage has been reset and next reset date advanced.',
                ]);

                $this->info("Credit usage of subscription #{$subscription->id} has been reset!");
            } catch (\Throwable $e) {
                $message = "Subscription #{$subscription->id} unable to reset credits! {$e->getMessage()}";

                $subscription->logs()->create([
                    'type' => 'usages-reset',
                    'status' => Log::STATUS_ERROR,
                    'message' => $message,
                ]);

                $this->error($message);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Reset usages for a subscription and dispatch event.
     */
    protected function resetSubscriptionUsages($subscription): void
    {
        event(new ResetFeatureUsages($subscription, $subscription->usagesToArray()));

        $subscription->resetUsages();

        $subscription->logs()->create([
            'type' => 'usages-reset',
            'message' => 'Usages has been reset successfully!',
        ]);

        $this->info("Usages of subscription #{$subscription->id} has been reset!");
    }
}
