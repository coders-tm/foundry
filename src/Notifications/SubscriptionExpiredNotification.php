<?php

namespace Foundry\Notifications;

use Foundry\Models\Subscription;

class SubscriptionExpiredNotification extends BaseNotification
{
    public $subject;

    public $message;

    /**
     * Create a new notification instance.
     *
     * @param  Subscription  $subscription
     * @return void
     */
    public function __construct($subscription)
    {
        $template = $subscription->renderNotification('user:subscription-expired');

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
