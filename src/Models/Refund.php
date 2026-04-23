<?php

namespace Foundry\Models;

use Foundry\Traits\Logable;
use Foundry\Traits\SerializeDate;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasUuids, Logable, SerializeDate;

    protected $fillable = [
        'amount',
        'reason',
        'payment_id',
        'order_id',
        'to_wallet',
        'wallet_transaction_id',
        'metadata',
    ];

    protected $casts = [
        'to_wallet' => 'boolean',
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        // Update order refund_total when refund is created or updated
        static::created(function (Refund $refund) {
            $refund->updateOrderRefundTotal();
        });

        static::updated(function (Refund $refund) {
            if ($refund->wasChanged('amount')) {
                $refund->updateOrderRefundTotal();
            }
        });

        static::deleted(function (Refund $refund) {
            $refund->updateOrderRefundTotal();
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    /**
     * Update order's refund_total when refund changes
     */
    public function updateOrderRefundTotal(): void
    {
        if ($this->order_id && $this->order) {
            $refundTotal = $this->order->refunds()->sum('amount');
            $this->order->updateQuietly(['refund_total' => $refundTotal]);
        }
    }
}
