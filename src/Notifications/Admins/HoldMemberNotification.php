<?php

namespace Foundry\Notifications\Admins;

use Foundry\Models\Notification as Template;
use Foundry\Models\User;
use Foundry\Notifications\BaseNotification;

class HoldMemberNotification extends BaseNotification
{
    public $user;

    public $status;

    public $subject;

    public $message;

    /**
     * Create a new notification instance.
     *
     * @param  User  $user
     * @return void
     */
    public function __construct($user)
    {
        $this->user = $user;

        $template = Template::default('admin:hold-release');

        // Use structured data for dual-format support
        $data = [
            'user' => [
                'name' => $this->user->name,
                'id' => $this->user->id,
                'first_name' => $this->user->first_name,
                'last_name' => $this->user->last_name,
                'email' => $this->user->email,
                'phone_number' => $this->user->phone_number,
            ],
        ];

        // Render using NotificationTemplateRenderer
        $rendered = $template->render($data);

        $this->subject = $rendered['subject'];
        $this->message = $rendered['content'];

        parent::__construct($this->subject, $this->message);
    }
}
