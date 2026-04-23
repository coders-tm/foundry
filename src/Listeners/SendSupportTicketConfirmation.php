<?php

namespace Foundry\Listeners;

use Foundry\Events\SupportTicketCreated;
use Foundry\Notifications\SupportTicketConfirmation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendSupportTicketConfirmation implements ShouldQueue
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
        if ($event->support_ticket->user) {
            $event->support_ticket->user->notify(new SupportTicketConfirmation($event->support_ticket));
        } else {
            Notification::route('mail', [
                $event->support_ticket->email => $event->support_ticket->name,
            ])->notify(new SupportTicketConfirmation($event->support_ticket));
        }
    }
}
