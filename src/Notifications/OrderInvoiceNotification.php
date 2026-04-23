<?php

namespace Foundry\Notifications;

use Foundry\Mail\NotificationMail;
use Foundry\Models\Notification;
use Foundry\Models\Order;

class OrderInvoiceNotification extends BaseNotification
{
    public $subject;

    public $message;

    /**
     * Create a new notification instance.
     *
     * @param  Order  $order
     */
    public function __construct($order)
    {
        $template = Notification::default('user:invoice-sent');
        $rendered = $template->render(['order' => $order->getShortCodes()]);

        $this->subject = $rendered['subject'];
        $this->message = $rendered['content'];
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

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): NotificationMail
    {
        return new NotificationMail(
            emailSubject: $this->subject,
            htmlContent: (string) $this->message,
            notifiable: $notifiable,
            fromName: config('app.name'),
            mailAttachments: []
        );
    }
}
