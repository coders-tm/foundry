<?php

namespace Foundry\Models;

use Foundry\Database\Factories\WalletTransactionFactory;
use Foundry\Foundry;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletTransaction extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'wallet_balance_id',
        'user_id',
        'type',
        'source',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'transactionable_type',
        'transactionable_id',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return WalletTransactionFactory::new();
    }

    /**
     * Get the wallet balance this transaction belongs to.
     */
    public function walletBalance(): BelongsTo
    {
        return $this->belongsTo(WalletBalance::class);
    }

    /**
     * Get the user that owns the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Foundry::$userModel);
    }

    /**
     * Get the parent transactionable model (Order, Subscription, etc.).
     */
    public function transactionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope for credit transactions.
     */
    public function scopeCredits($query)
    {
        return $query->where('type', 'credit');
    }

    /**
     * Scope for debit transactions.
     */
    public function scopeDebits($query)
    {
        return $query->where('type', 'debit');
    }

    /**
     * Scope for specific source.
     */
    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Get formatted amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        $prefix = $this->type === 'credit' ? '+' : '-';

        return $prefix.format_amount($this->amount, config('app.currency', 'USD'));
    }
}
