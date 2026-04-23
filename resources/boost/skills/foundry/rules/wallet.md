# Wallet Domain

## Overview

The Wallet domain manages user credit/debit balance systems for prepaid credits, gifted funds, promotional balances, and in-app currency.

## Core Models & Services

- **`WalletBalance`** (`src/Models/WalletBalance.php`) — Represents user's current wallet balance and metadata.
- **`WalletTransaction`** (`src/Models/WalletTransaction.php`) — Individual credit/debit transactions with type, amount, reason, and audit trail.
- **`HasWallet` trait** — Attached to User model; provides `wallet()` relation, `credit()`, `debit()`, and `getWalletBalance()` helpers.
- **`WalletProcessor`** (`src/Services/Payment/WalletProcessor.php`) — Service handling credit/debit flows, balance validation, and transaction recording.

## Key Workflows

### Crediting a Wallet

1. User receives promotional credit, gift, or refund to wallet.
2. Call `WalletProcessor::credit($user, $amount, 'promotional', $description)`.
3. Creates `WalletTransaction` with type `credit`, updates `WalletBalance`.
4. Event `WalletCredited` is dispatched.

### Debiting a Wallet

1. User applies wallet balance to order or subscription payment.
2. Call `WalletProcessor::debit($user, $amount, 'order_payment', $orderId)`.
3. Validates sufficient balance first (raise exception if insufficient).
4. Creates `WalletTransaction` with type `debit`, updates `WalletBalance`.
5. Event `WalletDebited` is dispatched.

### Checking Balance

```php
$balance = $user->getWalletBalance(); // Decimal amount
$hasEnough = $balance >= $amount;
```

## Transaction Types

- `credit` — Funds added to wallet (promotional, gift, refund).
- `debit` — Funds removed from wallet (payment, order fulfillment).
- `expired` — Promotional credits expiring (if time-limited).
- `adjusted` — Admin manual adjustment.

## Database Relations

- `User` → `WalletBalance` (hasOne or morphOne)
- `User` → `WalletTransaction` (hasMany or morphMany)
- `WalletTransaction` references related model via `transactionable_type` / `transactionable_id` (polymorphic).

## Best Practices

- **Always validate balance before debiting** — use `WalletProcessor::debit()` which throws on insufficient funds.
- **Atomic transactions** — wrap credit/debit operations in database transactions to ensure consistency.
- **Audit trail** — `WalletTransaction` includes reason, user context, related model. Never modify past transactions directly.
- **Expiry handling** — If wallet has expiry rules, run scheduled job to mark expired credits and prevent use.
- **Multi-currency awareness** — Wallet balance is stored in a single currency; convert if user has multiple currency settings.
- **Link to order/subscription** — Always record the reason (order ID, promo code) in `WalletTransaction` for dispute resolution.

## Common Tasks

### Credit User Wallet with Promotional Balance

```php
WalletProcessor::credit(
    $user, 
    100.00, 
    'promotional', 
    'Black Friday 2024 promo'
);
```

### Apply Wallet Balance to Order Payment

```php
$balanceAvailable = $user->getWalletBalance();
$amountToApply = min($balanceAvailable, $order->total);

WalletProcessor::debit(
    $user, 
    $amountToApply, 
    'order_payment', 
    $order->id
);

$order->update(['wallet_amount' => $amountToApply]);
```

### Handle Promotional Balance Expiry

```php
// Scheduled job
WalletTransaction::where('type', 'credit')
    ->where('reason', 'promotional')
    ->where('expires_at', '<=', now())
    ->update(['status' => 'expired']);
```
