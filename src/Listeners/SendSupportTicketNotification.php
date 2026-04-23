<?php

namespace Foundry\Listeners;

use Foundry\Events\SupportTicketCreated;
use Foundry\Notifications\Admins\SupportTicketNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendSupportTicketNotification implements ShouldQueue
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
    public function handle(SupportTicketCreated $event)
    {
        admin_notify(new SupportTicketNotification($event->support_ticket));
    }
}
