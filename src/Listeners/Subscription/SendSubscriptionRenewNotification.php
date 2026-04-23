<?php

namespace Foundry\Listeners\Subscription;

use Foundry\Events\SubscriptionRenewed;
use Foundry\Jobs\Subscription\SendRenewNotificationJob;

class SendSubscriptionRenewNotification
{
    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(SubscriptionRenewed $event)
    {
        if ($event->subscription->expired()) {
            SendRenewNotificationJob::dispatch($event->subscription)->afterResponse();
        }
    }
}
