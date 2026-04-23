# Order Management

## Summary
Expertise in managing orders including line items, taxes, discounts, customer information, refunds, and status lifecycle for e-commerce transactions. Foundry uses a robust `Order` model that integrates with the billing system.

## Key Models & Components
- **`Order`**: Root entity representing a transaction or invoice.
- **`LineItem`**: Individual items within an order (Subscriptions, Setup fees, one-time items).
- **`TaxLine`**: Tracks tax amounts per order/item.
- **`DiscountLine`**: Tracks coupon applications and manual discounts.
- **`OrderService`**: Orchestrates order creation and state changes.

## Logic Patterns
1. **Creation**: Orders are typically created from a `Cart` or `Resource`.
2. **Synchronization**: Use `saveFromResource()` to sync items and relations atomically.
3. **Totals**: Calculated dynamically using `LineItem` subtotals + `TaxLine` - `DiscountLine`.
4. **Status Lifecycle**: `Pending` → `Processing` → `Completed` (or `Failed`/`Canceled`).

## When to Activate
- Creating, updating, or querying Order models
- Processing order discounts or taxes
- Handling order refunds
- Managing order-to-customer relationships
- Calculating order totals with line items, taxes, and discounts

## Reference
See `resources/boost/skills/foundry/rules/orders.md` for detailed patterns.
