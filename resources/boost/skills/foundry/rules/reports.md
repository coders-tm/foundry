# Reporting & Metrics Rules

The reporting system provide standard SaaS metrics (MVP) and streamlined data exports.

## Report Registry
- **Service**: `Foundry\Services\Reports\ReportService`
- All reports are registered in the `$map` static array.
- Categories are defined in `$grouped`.

## Core Categories
1. **Revenue**: `mrr-by-plan`, `mrr-movement`.
2. **Retention**: `customer-churn`, `mrr-churn`.
3. **Economics**: `arpu`, `clv`.
4. **Acquisition**: `trial-conversion`, `new-signups`.
5. **Orders**: `sales-summary`, `payment-performance`, `tax-summary`.
6. **Exports**: `users`, `subscriptions`, `orders`, `payments`.

## Charts
- Charts extend `Foundry\Services\Charts\AbstractChart`.
- Standard chart types: `revenue`, `subscriptions`, `customers`, `orders`, `mrr`, `churn`, `arpu`, `plan-distribution`.
- **PostgreSQL Compatibility**: Always use single quotes `'paid'` for string literals in `DB::raw()` queries. Avoid double quotes as they are interpreted as column identifiers.

## KPI Metrics
- **Service**: `Foundry\Services\Metrics\MetricsService`
- Standard metrics: `mrr`, `arr`, `active_users`, `churn_rate`, `ltv`.

## Best Practices
- Maintain the single-responsibility pattern (one class per report type).
- Use cursor-based streaming for large data exports.
- Always include the correct category mapping in `ReportService`.
