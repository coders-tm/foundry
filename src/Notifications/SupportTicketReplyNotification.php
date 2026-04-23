<?php

namespace Foundry\Notifications;

use Foundry\Models\SupportTicket\Reply;

class SupportTicketReplyNotification extends BaseNotification
{
    public $subject;

    public $message;

    /**
     * Create a new notification instance.
     *
     * @param  Reply  $reply
     * @return void
     */
    public function __construct($reply)
    {
        $template = $reply->renderNotification();

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
