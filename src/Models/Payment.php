<?php

namespace Foundry\Models;

use Foundry\Concerns\Core;
use Foundry\Database\Factories\PaymentFactory;
use Foundry\Enum\PaymentStatus;
use Foundry\Foundry;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Payment extends Model
{
    use Core, HasFactory;

    protected $fillable = [
        'uuid',
        'paymentable_type',
        'paymentable_id',
        'payment_method_id',
        'transaction_id',
        'amount',
        'capturable',
        'status',
        'note',
        'metadata',
        'fees',
        'net_amount',
        'processed_at',
        'refund_amount',
    ];

    protected $hidden = [
        'paymentable_type',
        'paymentable_id',
    ];

    protected $casts = [
        'capturable' => 'boolean',
        'amount' => 'decimal:2',
        'fees' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'metadata' => 'array',
        'status' => PaymentStatus::class,
    ];

    protected $with = [
        'paymentMethod',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return PaymentFactory::new();
    }

    // Payment status constants
    const STATUS_PENDING = 'pending';

    const STATUS_PROCESSING = 'processing';

    const STATUS_COMPLETED = 'completed';

    const STATUS_FAILED = 'failed';

    const STATUS_CANCELLED = 'cancelled';

    const STATUS_REFUNDED = 'refunded';

    const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (empty($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }
        });

        // Update order paid_total when payment is created or updated
        static::created(function (Payment $payment) {
            $payment->updateOrderPaidTotal();
        });

        static::updated(function (Payment $payment) {
            if ($payment->wasChanged(['amount', 'status'])) {
                $payment->updateOrderPaidTotal();
            }
        });

        static::deleted(function (Payment $payment) {
            $payment->updateOrderPaidTotal();
        });
    }

    public function paymentable()
    {
        return $this->morphTo();
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * Scope to get successful payments
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', PaymentStatus::COMPLETED);
    }

    /**
     * Scope to get pending payments
     */
    public function scopePending($query)
    {
        return $query->where('status', PaymentStatus::PENDING);
    }

    /**
     * Scope to get failed payments
     */
    public function scopeFailed($query)
    {
        return $query->where('status', PaymentStatus::FAILED);
    }

    /**
     * Check if payment is successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === PaymentStatus::COMPLETED;
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    /**
     * Check if payment is failed
     */
    public function isFailed(): bool
    {
        return in_array($this->status, [PaymentStatus::FAILED, PaymentStatus::CANCELLED]);
    }

    /**
     * Check if payment is refunded
     */
    public function isRefunded(): bool
    {
        return in_array($this->status, [PaymentStatus::REFUNDED, PaymentStatus::PARTIALLY_REFUNDED]);
    }

    /**
     * Calculate refundable amount
     */
    public function getRefundableAmountAttribute(): float
    {
        // Only completed payments can be refunded (no partial refunds anymore)
        if ($this->status !== PaymentStatus::COMPLETED) {
            return 0;
        }

        return $this->amount;
    }

    /**
     * Mark payment as completed
     */
    public function markAsCompleted(): bool
    {
        return $this->update([
            'status' => PaymentStatus::COMPLETED,
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed($reason = null): bool
    {
        return $this->update([
            'status' => PaymentStatus::FAILED,
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Process refund
     */
    public function processRefund($reason = null): bool
    {
        // Enforce full refund only
        $refundAmount = $this->amount;
        $status = PaymentStatus::REFUNDED;

        $updated = $this->update([
            'status' => $status,
            'refund_amount' => $refundAmount,
            'refunded_at' => now(),
        ]);

        if ($updated && $this->paymentable_type === app(Foundry::$orderModel)->getMorphClass()) {
            $order = $this->paymentable;

            // Status updated
        }

        return $updated;
    }

    /**
     * Update order's paid_total when payment changes
     */
    public function updateOrderPaidTotal(): void
    {
        if ($this->paymentable_type === app(Foundry::$orderModel)->getMorphClass() && $this->paymentable) {
            $paidTotal = $this->paymentable->payments()->sum('amount');
            $this->paymentable->updateQuietly(['paid_total' => $paidTotal]);
        }
    }

    /**
     * Create payment for order
     *
     * Uses transaction_id as the idempotency anchor when present. Without a
     * transaction_id (manual / offline payments) we fall back to matching on
     * payment_method_id only, so that a second offline payment for the same
     * order / method still creates a separate record rather than silently
     * overwriting the first one.
     */
    public static function createForOrder($order, array $attributes = [])
    {
        $transactionId = $attributes['transaction_id'] ?? null;

        if ($transactionId) {
            // Gateway payment: use transaction_id as idempotency key
            return static::updateOrCreate([
                'paymentable_type' => app(Foundry::$orderModel)->getMorphClass(),
                'paymentable_id' => $order->id,
                'transaction_id' => $transactionId,
            ], $attributes);
        }

        // Manual / offline payment: always create a new record
        return static::create(array_merge([
            'paymentable_type' => app(Foundry::$orderModel)->getMorphClass(),
            'paymentable_id' => $order->id,
        ], $attributes));
    }

    protected function gatewayPaymentMethod(): Attribute
    {
        return Attribute::make(get: function () {
            return $this->metadata['payment_method'] ?? $this->paymentMethod?->name ?? 'Unknown';
        });
    }

    /**
     * Structured data for notification templates (Blade variables supported).
     * Only include safe, formatted values intended for templates.
     */
    public function getShortCodes(): array
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,

            // Payment method details (grouped)
            'payment_method' => [
                'name' => $this->gateway_payment_method, // "Visa •••• 4242" or PaymentMethod name
                'provider' => $this->paymentMethod?->provider ?? 'Unknown',
                'provider_name' => $this->paymentMethod?->name ?? 'Unknown',
                'type' => $this->metadata['payment_method_type'] ?? null,
                'card_brand' => $this->metadata['card_brand'] ?? null,
                'last_four' => $this->metadata['last_four'] ?? null,
                'bank_name' => $this->metadata['bank_name'] ?? null,
                'wallet_type' => $this->metadata['wallet_type'] ?? null,
                'upi_id' => $this->metadata['upi_id'] ?? null,
            ],

            // Amount information
            'amount' => format_amount($this->amount ?? 0),
            'gateway_amount' => isset($this->metadata['gateway_amount']) ? format_amount($this->metadata['gateway_amount'], $this->metadata['gateway_currency']) : null,
            'fees' => format_amount($this->fees ?? 0),
            'net_amount' => format_amount($this->net_amount ?? 0),
            'refund_amount' => format_amount($this->refund_amount ?? 0),
            'refundable_amount' => format_amount($this->refundable_amount ?? 0),
            'raw_amount' => $this->amount ?? 0,

            // Status information
            'status' => ucfirst($this->status?->value ?? 'pending'),
            'is_successful' => $this->isSuccessful(),
            'is_pending' => $this->isPending(),
            'is_failed' => $this->isFailed(),
            'is_refunded' => $this->isRefunded(),

            // Dates
            'created_at' => optional($this->created_at)->format('M d, Y h:i A'),
            'processed_at' => optional($this->processed_at)->format('M d, Y h:i A'),
            'date' => optional($this->created_at)->format('M d, Y'),

            // Currency
            'currency' => $this->currency ?? config('stripe.currency', 'USD'),

            // Additional information
            'note' => $this->note,
            'capturable' => (bool) $this->capturable,

            // Status flags for convenience
            'can_refund' => $this->refundable_amount > 0,
        ];
    }
}
