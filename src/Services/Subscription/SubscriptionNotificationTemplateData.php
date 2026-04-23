<?php

namespace Foundry\Services\Subscription;

use Foundry\Models\Subscription;

class SubscriptionNotificationTemplateData
{
    public function __construct(protected Subscription $subscription) {}

    /**
     * Get variables available for notification templates.
     */
    public function getShortCodes(): array
    {
        return [
            'user' => $this->subscription->user?->toArray(),
            'plan' => [
                'label' => $this->subscription->plan?->label,
                'price' => $this->subscription->plan?->formatPrice(),
            ],
            'billing_page' => user_route('/billing'),
            'subscription_status' => is_string($this->subscription->status)
                ? $this->subscription->status
                : ($this->subscription->status->value ?? ''),
            'billing_cycle' => $this->subscription->formatBillingInterval(),
            'next_billing_date' => $this->subscription->expires_at ? $this->subscription->expires_at->format('d M, Y') : '',
            'ends_at' => $this->subscription->expires_at ? $this->subscription->expires_at->format('d M, Y') : '',
            'starts_at' => $this->subscription->starts_at ? $this->subscription->starts_at->format('d M, Y') : '',
            'expires_at' => $this->subscription->expires_at ? $this->subscription->expires_at->format('d M, Y') : '',
            // We use the new Action classes, but for now we fallback to the model's method
            // Once we build Invoice Generator Action, we can update this.
            'upcoming_invoice' => $this->subscription->upcomingInvoice(),
        ];
    }
}
