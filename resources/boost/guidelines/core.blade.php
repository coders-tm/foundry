# Foundry (coderstm/foundry)

- Enterprise SaaS Laravel package providing subscription billing, order management, 20+ payment gateways,
RBAC, reporting, blog, wallets, and coupons — all via service providers, facades (Blog, Currency), and 39 model traits.
Where SaaS applications are forged.
- IMPORTANT: Always use the `search-docs` tool for detailed Foundry patterns, API usage, payment processor
integration, and subscription lifecycle documentation.
- IMPORTANT: Activate the `foundry` skill when working with Subscription, Plan, Feature, Coupon, Order, Payment,
Refund, WalletBalance, Blog, Admin, Currency, Setting, PaymentMethod, ExchangeRate, SupportTicket, AutoRenewal,
ManagesSubscriptions, Billable, HasWallet, HasFeature, HasPermission, Foundry facade, auth guards, or any artisan
command prefixed with `check:`, `subscriptions:`, `reset:`, `update:`, or `migrate:`.
- IMPORTANT: Use `Foundry::useUserModel()` and related binding methods in the host app's service provider — never
reference package model classes directly in application code.
- IMPORTANT: Use `Currency::format()`, `format_currency()`, `user()`, `is_admin()`, and `app_url()` helpers rather than
raw Laravel equivalents — they handle multi-guard and multi-currency context correctly.
