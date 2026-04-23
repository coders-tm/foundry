<?php

namespace Foundry\Commands;

use Foundry\Foundry;
use Illuminate\Console\Command;

class ResumeSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'foundry:subscriptions-resume';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resume frozen subscriptions that have reached their release date';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $subscriptionModel = Foundry::$subscriptionModel;

        // Get all frozen subscriptions that are due for resume
        $subscriptions = $subscriptionModel::dueForUnfreeze()->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No subscriptions due for resume.');

            return Command::SUCCESS;
        }

        $this->info("Found {$subscriptions->count()} subscription(s) to resume...");

        $resumed = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            try {
                $subscription->unfreeze();
                $resumed++;
                $this->line("✓ Resumed subscription #{$subscription->id} for user #{$subscription->user_id}");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("✗ Failed to resume subscription #{$subscription->id}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Completed: {$resumed} resumed, {$failed} failed.");

        return Command::SUCCESS;
    }
}
