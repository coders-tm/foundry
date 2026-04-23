<?php

namespace Foundry\Services\Subscription;

use Foundry\Models\Notification;
use Foundry\Models\Subscription;

class SubscriptionNotificationRenderer
{
    public function __construct(protected Subscription $subscription) {}

    /**
     * Render a notification from template for the subscription.
     *
     * @param  string  $type  The notification type
     * @param  array  $additionalData  Additional data to merge
     */
    public function render(string $type, array $additionalData = []): ?Notification
    {
        $template = Notification::default($type);

        if (! $template) {
            return null;
        }

        $data = array_merge(
            (new SubscriptionNotificationTemplateData($this->subscription))->getShortCodes(),
            $additionalData
        );

        $rendered = $template->render($data);

        return $template->fill([
            'subject' => $rendered['subject'],
            'content' => $rendered['content'],
        ]);
    }
}
