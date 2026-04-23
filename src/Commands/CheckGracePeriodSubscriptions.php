<?php

namespace Foundry\Commands;

use Foundry\Contracts\SubscriptionStatus;
use Foundry\Models\Subscription;
use Illuminate\Console\Command;

class CheckGracePeriodSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'foundry:subscriptions-grace-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for grace period subscriptions and mark them as expired if grace period has ended';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for subscriptions with expired grace periods...');

        $count = 0;

        // Find subscriptions in ACTIVE status where grace period has expired (ends_at is in the past)
        $subscriptions = Subscription::query()
            ->where('status', SubscriptionStatus::ACTIVE)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now());

        foreach ($subscriptions->cursor() as $subscription) {
            $subscription->update([
                'status' => SubscriptionStatus::EXPIRED,
            ]);

            // TODO: Send notification to user about expiration
            // $subscription->sendExpiredNotification();

            $count++;
        }

        if ($count === 0) {
            $this->info('No subscriptions found with expired grace periods.');

            return Command::SUCCESS;
        }

        $this->info("Marked {$count} subscription(s) as expired after grace period ended.");

        return Command::SUCCESS;
    }
}
