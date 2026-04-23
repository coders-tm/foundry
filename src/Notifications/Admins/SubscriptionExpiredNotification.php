<?php

namespace Foundry\Notifications\Admins;

use Foundry\Models\Subscription;
use Foundry\Notifications\BaseNotification;

class SubscriptionExpiredNotification extends BaseNotification
{
    public $user;

    public $subscription;

    public $status;

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
        $template = $subscription->renderNotification('admin:subscription-expired');

        $this->subject = $template->subject;
        $this->message = $template->content;

        parent::__construct($this->subject, $this->message);
    }
}
