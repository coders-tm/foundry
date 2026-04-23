<?php

namespace Foundry\Traits\Subscription;

use Carbon\CarbonInterface;
use Foundry\Actions\Subscription\CancelSubscription;
use Foundry\Actions\Subscription\CancelSubscriptionDowngrade;
use Foundry\Actions\Subscription\ExtendSubscriptionTrial;
use Foundry\Actions\Subscription\FreezeSubscription;
use Foundry\Actions\Subscription\GenerateSubscriptionInvoice;
use Foundry\Actions\Subscription\ProcessSubscriptionPayment;
use Foundry\Actions\Subscription\RenewSubscription;
use Foundry\Actions\Subscription\ResumeSubscription;
use Foundry\Actions\Subscription\SwapSubscriptionPlan;
use Foundry\Actions\Subscription\UnfreezeSubscription;
use Foundry\Events\SubscriptionRenewed;
use Foundry\Foundry;
use Foundry\Models\Notification;
use Foundry\Services\Subscription\SubscriptionNotificationRenderer;
use Foundry\Services\Subscription\SubscriptionNotificationTemplateData;
use Foundry\Services\Subscription\SubscriptionStatusManager;

/**
 * This trait provides a convenience layer to access Action/Service-based
 * architecture directly from the Subscription model.
 */
trait ForwardsSubscriptionActions
{
    /**
     * Get short codes for notifications.
     */
    public function getShortCodes(): array
    {
        return (new SubscriptionNotificationTemplateData($this))->getShortCodes();
    }

    /**
     * Send renewal notification.
     */
    public function sendRenewNotification(): void
    {
        if ($this->expired()) {
            event(new SubscriptionRenewed($this));
        }
    }

    /**
     * Render notification for the given type.
     */
    public function renderNotification($type, $additionalData = []): ?Notification
    {
        return (new SubscriptionNotificationRenderer($this))->render($type, $additionalData);
    }

    /**
     * Swap the subscription to a new plan.
     */
    public function swap($planId, bool $invoiceNow = true): self
    {
        return app(SwapSubscriptionPlan::class)->execute($this, $planId, $invoiceNow);
    }

    /**
     * Force swap the subscription to a new plan.
     */
    public function forceSwap($plan)
    {
        return app(SwapSubscriptionPlan::class)->execute($this, $plan, true, true);
    }

    /**
     * Renew the subscription.
     */
    public function renew(): self
    {
        return app(RenewSubscription::class)->execute($this);
    }

    /**
     * Cancel the subscription downgrade.
     */
    public function cancelDowngrade(): self
    {
        return app(CancelSubscriptionDowngrade::class)->execute($this);
    }

    /**
     * Freeze the subscription.
     */
    public function freeze($releaseAt = null, $reason = null, $fee = null)
    {
        return app(FreezeSubscription::class)->execute($this, $releaseAt, $reason, $fee);
    }

    /**
     * Unfreeze the subscription.
     */
    public function unfreeze()
    {
        return app(UnfreezeSubscription::class)->execute($this);
    }

    /**
     * Cancel the subscription.
     */
    public function cancel(): self
    {
        return app(CancelSubscription::class)->execute($this);
    }

    /**
     * Cancel the subscription at a specific date.
     */
    public function cancelAt(?\DateTimeInterface $endsAt)
    {
        return app(CancelSubscription::class)->cancelAt($this, $endsAt);
    }

    /**
     * Cancel the subscription immediately.
     */
    public function cancelNow(): self
    {
        return app(CancelSubscription::class)->cancelNow($this);
    }

    /**
     * Resume the canceled subscription.
     */
    public function resume(): self
    {
        return app(ResumeSubscription::class)->execute($this);
    }

    /**
     * Extend the trial until a specific date.
     */
    public function extendTrial(CarbonInterface $date)
    {
        return app(ExtendSubscriptionTrial::class)->extendTrial($this, $date);
    }

    /**
     * Set the trial days for the subscription.
     */
    public function trialDays(int $trialDays): self
    {
        return app(ExtendSubscriptionTrial::class)->trialDays($this, $trialDays);
    }

    /**
     * Set the trial end date for the subscription.
     */
    public function trialUntil($trialUntil): self
    {
        return app(ExtendSubscriptionTrial::class)->trialUntil($this, $trialUntil);
    }

    /**
     * End the subscription trial.
     */
    public function endTrial(): self
    {
        return app(ExtendSubscriptionTrial::class)->endTrial($this);
    }

    /**
     * Skip the subscription trial.
     */
    public function skipTrial(): self
    {
        return app(ExtendSubscriptionTrial::class)->endTrial($this);
    }

    /**
     * Generate an invoice for the subscription.
     */
    public function generateInvoice($start = false, $force = false)
    {
        return app(GenerateSubscriptionInvoice::class)->execute($this, $start, $force);
    }

    /**
     * Pay the subscription.
     */
    public function pay($paymentMethod, array $options = []): self
    {
        return app(ProcessSubscriptionPayment::class)->pay($this, $paymentMethod, $options);
    }

    /**
     * Handle payment confirmation.
     */
    public function paymentConfirmation($order = null): self
    {
        return app(ProcessSubscriptionPayment::class)->paymentConfirmation($this, $order);
    }

    /**
     * Handle payment failure.
     */
    public function paymentFailed($order = null): self
    {
        return app(ProcessSubscriptionPayment::class)->paymentFailed($this, $order);
    }

    /**
     * Cancel all open invoices for the subscription.
     */
    public function cancelOpenInvoices(): self
    {
        $openInvoices = $this->invoices()->where('status', Foundry::$orderModel::STATUS_OPEN);
        foreach ($openInvoices->cursor() as $order) {
            $order->markAsCancelled();
        }

        return $this;
    }

    /**
     * Convert the subscription status to a response array.
     */
    public function toResponse(array $extends = []): array
    {
        return (new SubscriptionStatusManager($this))->toResponse($extends);
    }
}
