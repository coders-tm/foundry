# Coupon and Wallet

## Summary
Expertise in credit management and promotional logic. Foundry provides a secure, transaction-based wallet and a flexible coupon system.

## Coupon System
- **`Coupon`**: Defines rules (percent vs fixed, duration, limits).
- **Validation**: Strict checks for expiry, usage limits, and product eligibility.
- **Redemption**: Atomic `applyWithLock()` pattern to prevent race conditions during redemption.

## Wallet System
- **`Wallet` trait**: Added to users to provide a unified balance.
- **`Transaction`**: Immutable audit log for every credit/debit.
- **Renewals**: Support for "Wallet-first" billing where renewals debit the balance before charging cards.

## When to Activate
- Applying coupons to orders or subscriptions
- Validating coupon eligibility
- Crediting or debiting user wallet
- Tracking wallet transactions
- Managing coupon redemption history
- Implementing discount workflows

## Reference
See `resources/boost/skills/foundry/rules/coupons.md` and `wallet.md` for detailed patterns.
