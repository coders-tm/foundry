<?php

namespace Foundry\Notifications;

use Foundry\Models\Subscription;

class SubscriptionDowngradeNotification extends BaseNotification
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
        // Pass old plan data in structured format
        $additionalData = [
            'old_plan' => optional($subscription->oldPlan)->label, // Scalar for {{OLD_PLAN}} backward compat
            'old_plan_details' => ['label' => optional($subscription->oldPlan)->label], // For new format
        ];

        $template = $subscription->renderNotification('user:subscription-downgrade', $additionalData);

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
