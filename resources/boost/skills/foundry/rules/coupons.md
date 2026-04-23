# Coupons Domain

## Overview

The Coupons domain manages discount codes and promotional logic for subscriptions and orders, including validation, redemption tracking, and coupon constraints.

## Core Models

- **`Coupon`** (`src/Models/Coupon.php`) — Coupon definition with code, discount type (fixed/percentage), amount, duration, usage limits, and validity window.
- **`Redeem`** (`src/Models/Redeem.php`) — Tracks coupon redemption instances (which user applied which coupon, when, how many times).
- **`CouponDuration` enum** (`src/Enum/CouponDuration.php`) — Defines coupon applicability (once, repeating, lifetime).
- **`CouponResource`** (`src/Http/Resources/CouponResource.php`) — API serialization for coupon data.

## Key Workflows

### Creating a Coupon

1. Create `Coupon` with:
   - `code` (unique, e.g., `SAVE20`)
   - `type` (`fixed` or `percentage`)
   - `amount` (e.g., 20 for 20% or 2000 for $20)
   - `duration` (from `CouponDuration` enum: `once`, `repeating`, `lifetime`)
   - `usage_limit` (max uses across all users)
   - `usage_limit_per_user` (max per individual)
   - `valid_from` / `valid_until` (time window)

### Validating a Coupon

Before redeeming, validate:
- Coupon exists and is not expired (`valid_from <= now <= valid_until`).
- Usage limit not exceeded (`usage_count < usage_limit`).
- User hasn't exceeded per-user limit (`user_redeem_count < usage_limit_per_user`).
- Coupon eligibility rules (e.g., minimum order amount, applies to specific plans).

### Applying to Orders

1. Call validation logic (above).
2. Calculate discount: If `type = 'percentage'`, discount = `order->total * (amount / 100)`. If `type = 'fixed'`, discount = `amount`.
3. Deduct discount from order total.
4. Record `Redeem` entry linking coupon, user, and order.

### Applying to Subscriptions

1. Validate coupon (as above).
2. Apply discount to subscription startup cost or first invoice.
3. If `duration = 'repeating'` or `'lifetime'`, apply discount to recurring invoices.
4. Record `Redeem` entry linking coupon, subscription, and user.

## Database Relations

- `Coupon` → `Redeem` (hasMany) — all redemptions of this coupon
- `User` → `Redeem` (hasMany) — all coupons redeemed by this user
- `Order` → `Redeem` (morphMany) — coupons applied to this order
- `Subscription` → `Redeem` (morphMany) — coupons applied to this subscription

## Validation Rules

- **Uniqueness**: Coupon codes should be unique and case-insensitive.
- **Expiry**: Coupons outside `valid_from` ... `valid_until` window are invalid.
- **Usage Limits**: Check both global and per-user limits before allowing redemption.
- **Eligibility**: Implement business rules (e.g., "not applicable to renewal invoices" or "only for new subscriptions").
- **Amount Validation**: Discount should not exceed order/subscription total (handle gracefully).

## Best Practices

- **Atomic operations**: Wrap coupon validation and redemption in a database transaction to prevent race conditions.
- **Audit trail**: `Redeem` model stores user, coupon, payable (order/subscription), and timestamp for dispute resolution.
- **Prevent over-application**: Enforce `usage_limit_per_user` to prevent one user from exhausting a coupon.
- **Graceful degradation**: If coupon expires during checkout, notify user and allow proceeding without coupon.
- **Reporting**: Track coupon performance (redemptions, revenue impact) for marketing analysis.

## Common Tasks

### Validate and Apply Coupon to Order

```php
$coupon = Coupon::where('code', $code)->first();

// Validation
if (!$coupon || $coupon->isExpired()) {
    throw new InvalidCouponException('Coupon not found or expired');
}

if ($coupon->isUsageLimitExceeded()) {
    throw new CouponExhaustedException('Coupon usage limit reached');
}

if ($coupon->isUsageLimitPerUserExceeded($user)) {
    throw new CouponLimitPerUserExceededException('You have already used this coupon');
}

// Calculate discount
$discount = $coupon->type === 'percentage' 
    ? $order->total * ($coupon->amount / 100)
    : $coupon->amount;
$discount = min($discount, $order->total); // Can't discount more than order total

// Apply and record
$order->update(['coupon_discount' => $discount]);
Redeem::create([
    'coupon_id' => $coupon->id,
    'user_id' => $user->id,
    'redeemable_type' => Order::class,
    'redeemable_id' => $order->id,
    'discount_applied' => $discount,
]);

$coupon->increment('usage_count');
```

### Check if Coupon Applies to Subscription Renewals

```php
// Implement business logic
if ($coupon->duration === CouponDuration::ONCE) {
    // Apply only to first invoice
    return $isFirstInvoice;
}
if ($coupon->duration === CouponDuration::LIFETIME) {
    // Apply to all invoices
    return true;
}
// etc.
```
