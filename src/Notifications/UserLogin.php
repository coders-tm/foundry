<?php

namespace Foundry\Notifications;

use Foundry\Models\Log;
use Foundry\Models\Notification as Template;
use Illuminate\Bus\Queueable;

class UserLogin extends BaseNotification
{
    use Queueable;

    public $log;

    public $user;

    /**
     * Create a new notification instance.
     *
     * @param  Log  $log
     * @return void
     */
    public function __construct($log)
    {
        $this->log = $log;
        $this->user = $log->logable;

        $template = Template::default('common:user-login');

        // Render using NotificationTemplateRenderer
        $rendered = $template->render([
            'user' => $this->user->getShortCodes(),
            'log' => [
                'time' => $this->log->options['time'] ?? null,
                'device' => $this->log->options['device'] ?? null,
                'location' => $this->log->options['location'] ?? null,
                'ip' => $this->log->options['ip'] ?? null,
            ],
        ]);

        parent::__construct($rendered['subject'], $rendered['content']);
    }
}
