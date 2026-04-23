<?php

namespace Foundry\Notifications;

use Foundry\Models\Notification as Template;
use Foundry\Models\User;
use Illuminate\Bus\Queueable;

class UserResetPasswordNotification extends BaseNotification
{
    use Queueable;

    public $user;

    public $reset;

    /**
     * Create a new notification instance.
     *
     * @param  User  $user
     * @return void
     */
    public function __construct($user, array $reset)
    {
        $this->user = $user;
        $this->reset = $reset;

        $template = Template::default('common:password-reset-request');

        $rendered = $template->render([
            'user' => $this->user->getShortCodes(),
            'reset' => [
                'url' => $this->reset['url'] ?? null,
                'token' => $this->reset['token'] ?? null,
                'expires' => $this->reset['expires'] ?? null,
            ],
        ]);

        parent::__construct($rendered['subject'], $rendered['content']);
    }
}
