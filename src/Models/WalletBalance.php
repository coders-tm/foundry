<?php

namespace Foundry\Models;

use Foundry\Database\Factories\WalletBalanceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class WalletBalance extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return WalletBalanceFactory::new();
    }

    /**
     * Get the user that owns the wallet.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('foundry.models.user'));
    }

    /**
     * Get all transactions for this wallet.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Add credit to wallet.
     */
    public function credit(
        float $amount,
        string $source,
        ?string $description = null,
        $transactionable = null,
        array $metadata = []
    ): WalletTransaction {
        return DB::transaction(function () use ($amount, $source, $description, $transactionable, $metadata) {
            $balanceBefore = $this->balance;
            $this->increment('balance', $amount);
            $balanceAfter = $this->fresh()->balance;

            return $this->transactions()->create([
                'user_id' => $this->user_id,
                'type' => 'credit',
                'source' => $source,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'transactionable_type' => $transactionable ? get_class($transactionable) : null,
                'transactionable_id' => $transactionable?->id,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Deduct from wallet.
     */
    public function debit(
        float $amount,
        string $source,
        ?string $description = null,
        $transactionable = null,
        array $metadata = []
    ): WalletTransaction {
        return DB::transaction(function () use ($amount, $source, $description, $transactionable, $metadata) {
            // Pessimistic lock — prevents concurrent over-spend (TOCTOU)
            $wallet = WalletBalance::where('id', $this->id)->lockForUpdate()->first();

            if ($wallet->balance < $amount) {
                throw new \Exception('Insufficient wallet balance. Available: '.format_amount($wallet->balance).', Required: '.format_amount($amount));
            }

            $balanceBefore = $wallet->balance;
            $wallet->decrement('balance', $amount);
            $balanceAfter = $wallet->fresh()->balance;

            return $wallet->transactions()->create([
                'user_id' => $wallet->user_id,
                'type' => 'debit',
                'source' => $source,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'transactionable_type' => $transactionable ? get_class($transactionable) : null,
                'transactionable_id' => $transactionable?->id,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Check if wallet has sufficient balance.
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }

    /**
     * Get formatted balance.
     */
    public function getFormattedBalanceAttribute(): string
    {
        return format_amount($this->balance, config('app.currency', 'USD'));
    }
}
