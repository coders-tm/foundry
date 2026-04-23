<?php

namespace Foundry\Listeners;

use Foundry\Events\SupportTicketReplyCreated;
use Foundry\Notifications\SupportTicketReplyNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendSupportTicketReplyNotification implements ShouldQueue
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
    public function handle(SupportTicketReplyCreated $event)
    {
        if ($event->reply->byAdmin()) {
            $event->support_ticket_user->notify(new SupportTicketReplyNotification($event->reply));
        } else {
            admin_notify(new SupportTicketReplyNotification($event->reply));
        }
    }
}
