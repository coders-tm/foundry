<?php

namespace Foundry\Listeners;

use Foundry\Events\UserSubscribed;
use Foundry\Notifications\UserSignupNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendSignupNotification implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(UserSubscribed $event)
    {
        $event->user->notify(new UserSignupNotification($event->user));
    }
}
