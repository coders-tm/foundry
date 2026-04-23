<?php

namespace Foundry\Notifications\Admins;

use Foundry\Models\Subscription;
use Foundry\Notifications\BaseNotification;

class SubscriptionCanceledNotification extends BaseNotification
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
        $template = $subscription->renderNotification('admin:subscription-cancel');

        $this->subject = $template->subject;
        $this->message = $template->content;

        parent::__construct($this->subject, $this->message);
    }
}
