<?php

namespace Foundry\Traits;

use Foundry\Models\WalletBalance;
use Foundry\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasWallet
{
    /**
     * Get user's wallet balance.
     */
    public function walletBalance(): HasOne
    {
        return $this->hasOne(WalletBalance::class, 'user_id');
    }

    /**
     * Get user's wallet transactions.
     */
    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'user_id');
    }

    /**
     * Get or create wallet balance.
     */
    public function getOrCreateWallet(): WalletBalance
    {
        return $this->walletBalance()->firstOrCreate(
            ['user_id' => $this->id],
            ['balance' => 0.00]
        );
    }

    /**
     * Get wallet balance amount.
     */
    public function getWalletBalance(): float
    {
        return (float) $this->getOrCreateWallet()->balance;
    }

    /**
     * Add credit to wallet.
     */
    public function creditWallet(
        float $amount,
        string $source,
        ?string $description = null,
        $transactionable = null,
        array $metadata = []
    ): WalletTransaction {
        $wallet = $this->getOrCreateWallet();

        return $wallet->credit($amount, $source, $description, $transactionable, $metadata);
    }

    /**
     * Deduct from wallet.
     */
    public function debitWallet(
        float $amount,
        string $source,
        ?string $description = null,
        $transactionable = null,
        array $metadata = []
    ): WalletTransaction {
        $wallet = $this->getOrCreateWallet();

        return $wallet->debit($amount, $source, $description, $transactionable, $metadata);
    }

    /**
     * Check if user has sufficient wallet balance.
     */
    public function hasWalletBalance(float $amount): bool
    {
        return $this->getWalletBalance() >= $amount;
    }

    /**
     * Get formatted wallet balance.
     */
    public function getFormattedWalletBalanceAttribute(): string
    {
        $wallet = $this->getOrCreateWallet();

        return format_amount($wallet->balance, config('app.currency', 'USD'));
    }
}
