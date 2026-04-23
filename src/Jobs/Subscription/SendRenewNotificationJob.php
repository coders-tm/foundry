<?php

namespace Foundry\Jobs\Subscription;

use Foundry\Models\Subscription;
use Foundry\Notifications\SubscriptionRenewedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job to send subscription renewed notification to the user.
 */
class SendRenewNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The subscription instance.
     */
    public Subscription $subscription;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    /**
     * Execute the job.
     *
     * Sends the renewal notification to the subscription's user.
     */
    public function handle(): void
    {
        if ($this->subscription->user) {
            $this->subscription->user->notify(
                new SubscriptionRenewedNotification($this->subscription)
            );
        }
    }
}
