<?php

namespace Foundry\Concerns;

use Foundry\Enum\OrderStatus as OrderStatusEnum;
use Foundry\Enum\PaymentStatus;

trait OrderStatus
{
    const STATUS_OPEN = OrderStatusEnum::OPEN->value;

    const STATUS_DRAFT = OrderStatusEnum::DRAFT->value;

    const STATUS_PENDING = OrderStatusEnum::PENDING->value;

    const STATUS_COMPLETED = OrderStatusEnum::COMPLETED->value;

    const STATUS_CANCELLED = OrderStatusEnum::CANCELLED->value;

    const STATUS_DECLINED = OrderStatusEnum::DECLINED->value;

    const STATUS_DISPUTED = OrderStatusEnum::DISPUTED->value;

    const STATUS_ARCHIVED = OrderStatusEnum::ARCHIVED->value;

    const STATUS_PENDING_PAYMENT = OrderStatusEnum::PENDING_PAYMENT->value;

    const STATUS_PROCESSING = OrderStatusEnum::PROCESSING->value;

    const STATUS_PAYMENT_PENDING = PaymentStatus::PAYMENT_PENDING->value;

    const STATUS_PAYMENT_FAILED = PaymentStatus::PAYMENT_FAILED->value;

    const STATUS_PAYMENT_SUCCESS = PaymentStatus::SUCCESS->value;

    const STATUS_PARTIALLY_PAID = PaymentStatus::PARTIALLY_PAID->value;

    const STATUS_PAID = PaymentStatus::PAID->value;

    const STATUS_REFUNDED = PaymentStatus::REFUNDED->value;

    const STATUS_MANUAL_VERIFICATION_REQUIRED = OrderStatusEnum::MANUAL_VERIFICATION_REQUIRED->value;

    /**
     * Mark order as open
     */
    public function markAsOpen()
    {
        return $this->updateStatus(OrderStatusEnum::PENDING_PAYMENT, PaymentStatus::PAYMENT_PENDING);
    }

    /**
     * Mark order as pending
     */
    public function markAsPending()
    {
        return $this->updateStatus(OrderStatusEnum::PROCESSING);
    }

    /**
     * Mark order as completed
     */
    public function markAsCompleted()
    {
        return $this->updateStatus(OrderStatusEnum::COMPLETED);
    }

    /**
     * Mark order as cancelled
     */
    public function markAsCancelled($reason = null)
    {
        $this->updateStatus(OrderStatusEnum::CANCELLED, null, [
            'cancelled_at' => now(),
        ]);

        $reasonMessage = $this->getCancellationReason($reason);

        $this->logs()->create([
            'type' => 'canceled',
            'message' => 'Order has been canceled. Reason: '.$reasonMessage,
        ]);

        return $this;
    }

    /**
     * Get cancellation reason message with fallback
     */
    protected function getCancellationReason($reason)
    {
        if (empty($reason)) {
            return 'No reason provided';
        }

        return ucfirst(str_replace('_', ' ', strtolower($reason)));
    }

    /**
     * Mark order as partially paid
     */
    public function markAsPartiallyPaid()
    {
        return $this->updateStatus(null, PaymentStatus::PARTIALLY_PAID);
    }

    /**
     * Mark order as refunded
     */
    public function markAsRefunded()
    {
        return $this->updateStatus(OrderStatusEnum::REFUNDED, PaymentStatus::REFUNDED);
    }

    /**
     * Centralized status update handler to enforce SOLID/DRY.
     * Uses the model's update() method which may be overridden for business logic.
     */
    protected function updateStatus(?OrderStatusEnum $status = null, ?PaymentStatus $paymentStatus = null, array $additional = [])
    {
        $attributes = $additional;

        if ($status) {
            $attributes['status'] = $status;
        }

        if ($paymentStatus) {
            $attributes['payment_status'] = $paymentStatus;
        }

        if (! empty($attributes)) {
            $this->update($attributes);
        }

        return $this;
    }

    /**
     * Sync current status based on payment totals
     */
    public function syncCurrentStatus()
    {

        if ($this->refund_total == $this->paid_total && $this->paid_total > 0) {
            $this->markAsRefunded();
        } else {
            // Remove refund status if no refunds or not fully refunded
            if ($this->payment_status === PaymentStatus::REFUNDED) {
                $this->updateStatus(null, PaymentStatus::PAID);
            }
        }

        return $this;
    }
}
