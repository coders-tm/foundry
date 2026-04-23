<?php

namespace Foundry\Notifications;

use Foundry\Models\SupportTicket;

class SupportTicketConfirmation extends BaseNotification
{
    public $subject;

    public $message;

    /**
     * Create a new notification instance.
     *
     * @param  SupportTicket  $support_ticket
     * @return void
     */
    public function __construct($support_ticket)
    {
        $template = $support_ticket->renderNotification();

        $this->subject = $template->subject;
        $this->message = $template->content;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
