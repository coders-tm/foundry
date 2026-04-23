<?php

namespace Foundry\Contracts;

use Carbon\Carbon;
use Foundry\Models\Order;

/**
 * Manages Subscriptions Contract
 *
 * This interface defines the contract for subscription management functionality.
 * It provides a robust structure for implementing subscription lifecycle operations,
 * making the code more maintainable and testable.
 */
interface ManagesSubscriptions
{
    /**
     * Determine if the subscription is valid.
     */
    public function valid(): bool;

    /**
     * Determine if the subscription is incomplete.
     */
    public function incomplete(): bool;

    /**
     * Determine if the subscription is expired.
     */
    public function expired(): bool;

    /**
     * Determine if the subscription is active.
     */
    public function active(): bool;

    /**
     * Determine if the subscription is recurring.
     */
    public function recurring(): bool;

    /**
     * Determine if the subscription is canceled.
     */
    public function canceled(): bool;

    /**
     * Determine if the subscription is on trial.
     */
    public function onTrial(): bool;

    /**
     * Determine if the subscription has a downgrade scheduled.
     */
    public function hasDowngrade(): bool;

    /**
     * Determine if the subscription is on grace period.
     */
    public function onGracePeriod(): bool;

    /**
     * Determine if the subscription has an incomplete payment.
     */
    public function hasIncompletePayment(): bool;

    /**
     * Swap the subscription to a new plan.
     */
    public function swap($planId, bool $invoiceNow = true): self;

    /**
     * Cancel the downgrade plan.
     */
    public function cancelDowngrade(): self;

    /**
     * Renew the subscription period.
     */
    public function renew(): self;

    /**
     * Specify the number of days of the trial.
     */
    public function trialDays(int $trialDays): self;

    /**
     * Specify the ending date of the trial.
     */
    public function trialUntil($trialUntil): self;

    /**
     * Force the trial to end immediately.
     */
    public function skipTrial(): self;

    /**
     * Force the subscription's trial to end immediately.
     */
    public function endTrial(): self;

    /**
     * Cancel the subscription at the end of the billing period.
     */
    public function cancel(): self;

    /**
     * Cancel the subscription immediately.
     */
    public function cancelNow(): self;

    /**
     * Resume a canceled subscription.
     */
    public function resume(): self;

    /**
     * Apply a coupon to the subscription.
     */
    public function withCoupon(?string $coupon): self;

    /**
     * Get the upcoming invoice for the subscription.
     */
    public function upcomingInvoice(bool $start = false, ?Carbon $dateFrom = null);

    /**
     * Process payment for the subscription.
     */
    public function pay($paymentMethod, array $options = []): self;

    /**
     * Cancel all open invoices for this subscription.
     */
    public function cancelOpenInvoices(): self;

    /**
     * Handle payment confirmation callback.
     *
     * @param  Order|null  $order
     */
    public function paymentConfirmation($order = null): self;

    /**
     * Handle payment failure callback.
     *
     * @param  Order|null  $order
     */
    public function paymentFailed($order = null): self;

    /**
     * Save the subscription and generate an invoice.
     */
    public function saveAndInvoice(array $options = [], bool $force = false): self;

    /**
     * Save the subscription without generating an invoice.
     */
    public function saveWithoutInvoice(array $options = []): self;

    /**
     * Send renewal notification to the user.
     */
    public function sendRenewNotification(): void;
}
