---
name: foundry
description: >
  Activate when creating or modifying SaaS features using the coderstm/foundry
  package (formerly laravel-core). Triggers on: Subscription, Plan, Feature, Coupon, Order,
  Gateway, Payment, Refund, WalletBalance, Blog, Admin, Currency, Setting, PaymentMethod,
  SupportTicket, ManagesSubscriptions, Billable, HasWallet, HasFeature, HasPermission,
  Foundry facade, auth guards, artisan commands (check:*, subscriptions:*, reset:*),
  config keys (foundry.*), or any route prefixed with /auth/, /subscriptions/, /admin/,
  /user/, or /webhooks/.
---

# Foundry Development (Where SaaS applications are forged)

Foundry is an enterprise-grade Laravel package for building SaaS applications. It provides
multi-guard authentication, subscription billing, 20+ payment gateways, MVP-optimized reporting,
RBAC, support tickets, and a notification system.

## Documentation

Use `search-docs` for detailed Foundry patterns and documentation.

## Specialist Rules (Modular)

This skill includes specialist rules for key domains. Reference these when working on specific features:

- **Subscriptions**: [subscriptions.md](./rules/subscriptions.md)
- **Orders**: [orders.md](./rules/orders.md)
- **Payments**: [payments.md](./rules/payments.md)
- **Reports & Metrics**: [reports.md](./rules/reports.md)
- **Notifications**: [notifications.md](./rules/notifications.md)
- **RBAC**: [rbac.md](./rules/rbac.md)
- **Auto-Renewal**: [auto-renewal.md](./rules/auto-renewal.md)

## Architecture

- **Entry point**: `Foundry\Foundry` — facade for model binding and global settings.
- **Facades**: `Blog`, `Currency` — request-scoped service accessors.
- **Reporting**: `ReportService` centralizes all reporting logic (13 MVP reports).
- **Billing**: `SubscriptionService` + 20+ `AbstractPaymentProcessor` implementations.
- **Models**: Under `Foundry\Models`; use `Foundry::useUserModel()` to bind custom models.
- **Traits**: 39+ traits including `Billable`, `ManagesSubscriptions`, `HasWallet`, `HasPermission`.

## Key Workflows

### 1. Subscription & Billing
- Ensure `Billable` and `ManagesSubscriptions` traits are on your User model.
- Manage features via the `Feature` model and check via `$user->hasFeature()`.
- Run `subscriptions:renew` and `reset:subscriptions-usages` on schedule.

### 2. Order Management
- Use the `Order` model for one-time payments and subscription billing records.
- Line items are tracked via `LineItem`, with support for `TaxLine` and `DiscountLine`.

### 3. Reporting & Analytics
- 13 MVP reports available across 6 categories (Revenue, Retention, Economics, Acquisition, Orders, Exports).
- Use `ReportService` to fetch data or trigger exports.

### 4. Notifications
- System uses database-driven templates.
- Stubs are in `stubs/database/templates`.
- Render via `NotificationTemplateRenderer`.

## Best Practices

- **Strict Typing**: Always use `declare(strict_types=1)` and explicit return types.
- **Decoupling**: Never reference package model classes directly in your app; use `Foundry::useXModel()` bindings.
- **Helpers**: Use package helpers (`user()`, `guard()`, `format_currency()`) to ensure multi-guard awareness.
- **Events**: Hook into lifecycle changes via events (`SubscriptionCreated`, etc.) instead of overriding methods.
