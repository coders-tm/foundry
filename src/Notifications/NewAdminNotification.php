<?php

namespace Foundry\Notifications;

use Foundry\Models\Admin;
use Foundry\Models\Notification as Template;

class NewAdminNotification extends BaseNotification
{
    public $admin;

    public $password;

    public $subject;

    public $message;

    /**
     * Create a new notification instance.
     *
     * @param  Admin  $admin
     * @param  string  $password
     * @return void
     */
    public function __construct($admin, $password = '********')
    {
        $this->admin = $admin;
        $this->password = $password;

        $template = Template::default('admin:new-account');

        // Use structured data for dual-format support
        $data = [
            'admin' => $this->admin->getShortCodes(),
            'password' => $this->password,
            'login_url' => admin_url('auth/login'),
        ];

        // Render using NotificationTemplateRenderer
        $rendered = $template->render($data);

        parent::__construct($rendered['subject'], $rendered['content']);
    }
}
