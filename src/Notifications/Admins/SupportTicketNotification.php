<?php

namespace Foundry\Notifications\Admins;

use Foundry\Models\SupportTicket;
use Foundry\Notifications\BaseNotification;

class SupportTicketNotification extends BaseNotification
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
        $contactUs = $support_ticket->isContactUs();
        $type = $contactUs ? 'admin:contact-us-notification' : 'admin:support-ticket-notification';
        $template = $support_ticket->renderNotification($type);

        $this->subject = $template->subject;
        $this->message = $template->content;

        parent::__construct($this->subject, $this->message);
    }
}
