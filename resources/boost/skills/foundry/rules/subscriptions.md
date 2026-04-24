# Subscription Management Rules

`coderstm/foundry` manages subscriptions using custom feature usage tracking and an integrated **Auto-Renewal** modular package, bypassing Laravel Cashier for automated payments.

## Core Models & Traits
- **Trait**: `Foundry\Concerns\Billable` — Must be used on the User model.
- **Trait**: `Foundry\Concerns\ManagesSubscriptions` — Handles the higher-level subscription logic.
- **Model**: `Foundry\Models\Subscription` — Core subscription model.
- **Model**: `Foundry\Models\Subscription\Plan` — Defines prices and intervals.
- **Modular Package**: `Foundry\AutoRenewal` — Handles automated charging and provider synchronization.

## Feature Usage Tracking
- **Trait**: `Foundry\Concerns\HasFeature` — Check and track usage.
- Use `$user->hasFeature('slug')` to check if a plan includes a feature.
- Use `$user->useFeature('slug', $amount = 1)` to increment usage.
- Use `$user->featureUsage('slug')` to get current usage for the period.

## Status Checks
- `active`: Subscription is within valid paid period.
- `onGracePeriod()`: Canceled but still valid until period end.
- `canceled()`: Fully ended and no longer accessible.

## Automated Logic
- **Renewal**: Handled by `php artisan subscriptions:renew`.
- **Usage Reset**: Handled by `php artisan reset:subscriptions-usages` (resets daily/monthly counters based on plan).

## Best Practices
- Always check `$user->subscribed()` before allowing access to billing features.
- Never hardcode Plan IDs; use slugs or price mapping from config.
- Use the `SubscriptionService` for complex logic like switching plans or upgrading/downgrading.
