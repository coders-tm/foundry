<?php

namespace Foundry\Notifications;

use Foundry\Models\Import;
use Foundry\Models\Notification as Template;

class ImportCompletedNotification extends BaseNotification
{
    public Import $import;

    public $subject;

    public $message;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Import $import)
    {
        $this->import = $import;

        $template = Template::default('admin:import-completed');

        // Render using NotificationTemplateRenderer
        $rendered = $template->render($this->import->getShortCodes());

        parent::__construct($rendered['subject'], $rendered['content']);
    }
}
