<?php

namespace Foundry\Notifications;

use Foundry\Models\Notification as Template;
use Foundry\Models\User;

class UserSignupNotification extends BaseNotification
{
    public $user;

    public $subject;

    public $message;

    public $subscription;

    /**
     * Create a new notification instance.
     *
     * @param  User  $user
     * @return void
     */
    public function __construct($user)
    {
        $this->user = $user;
        $this->subscription = $user->subscription();

        $template = Template::default('user:signup');

        // Render using NotificationTemplateRenderer
        $rendered = $template->render([
            'user' => $user->getShortCodes(),
            'subscription' => $this->subscription ? $this->subscription->getShortCodes() : null,
        ]);

        parent::__construct($rendered['subject'], $rendered['content']);
    }
}
