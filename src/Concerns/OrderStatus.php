<?php

namespace Foundry\Concerns;

trait OrderStatus
{
    const STATUS_OPEN = 'open';

    const STATUS_DRAFT = 'draft';

    const STATUS_PENDING = 'pending';

    const STATUS_COMPLETED = 'completed';

    const STATUS_CANCELLED = 'cancelled';

    const STATUS_DECLINED = 'declined';

    const STATUS_DISPUTED = 'disputed';

    const STATUS_ARCHIVED = 'archived';

    const STATUS_PENDING_PAYMENT = 'pending_payment';

    const STATUS_PROCESSING = 'processing';

    const STATUS_PAYMENT_PENDING = 'payment_pending';

    const STATUS_PAYMENT_FAILED = 'payment_failed';

    const STATUS_PAYMENT_SUCCESS = 'payment_success';

    const STATUS_PARTIALLY_PAID = 'partially_paid';

    const STATUS_PAID = 'paid';

    const STATUS_REFUNDED = 'refunded';

    const STATUS_MANUAL_VERIFICATION_REQUIRED = 'manual_verification_required';

    /**
     * Mark order as open
     */
    public function markAsOpen()
    {
        $this->fill([
            'status' => static::STATUS_PENDING_PAYMENT,
            'payment_status' => static::STATUS_PAYMENT_PENDING,
        ])->save();

        return $this;
    }

    /**
     * Mark order as pending
     */
    public function markAsPending()
    {
        $this->fill([
            'status' => static::STATUS_PROCESSING,
        ])->save();

        return $this;
    }

    /**
     * Mark order as completed
     */
    public function markAsCompleted()
    {
        $this->fill([
            'status' => static::STATUS_COMPLETED,
        ])->save();

        return $this;
    }

    /**
     * Mark order as cancelled
     */
    public function markAsCancelled($reason = null)
    {
        $this->fill([
            'status' => static::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ])->save();

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

        // Fallback to the provided reason (format it nicely)
        return ucfirst(str_replace('_', ' ', strtolower($reason)));
    }

    /**
     * Mark order as partially paid
     */
    public function markAsPartiallyPaid()
    {
        $this->fill([
            'payment_status' => static::STATUS_PARTIALLY_PAID,
        ])->save();

        return $this;
    }

    /**
     * Mark order as refunded
     */
    public function markAsRefunded()
    {
        $this->fill([
            'payment_status' => static::STATUS_REFUNDED,
            'status' => static::STATUS_REFUNDED,
        ]);

        return $this;
    }

    /**
     * Sync current status based on payment totals
     */
    public function syncCurrentStatus()
    {
        $this->refresh();

        if ($this->refund_total == $this->paid_total && $this->paid_total > 0) {
            $this->markAsRefunded();
        } else {
            // Remove refund status if no refunds or not fully refunded
            if ($this->payment_status === static::STATUS_REFUNDED) {
                $this->fill(['payment_status' => static::STATUS_PAID])->save();
            }
        }

        return $this;
    }
}
