# Orders Domain

## Overview

The Orders domain manages e-commerce order workflows including line items, discounts, taxes, refunds, and order-to-customer relationships.

## Core Models

- **`Order`** (`src/Models/Order.php`) — Main order model with status tracking, timestamps, and customer association.
- **`Order/LineItem`** — Line items in an order with quantity, unit price, and product metadata.
- **`Order/DiscountLine`** — Discount applications to orders (flat or percentage).
- **`Order/TaxLine`** — Tax calculations applied to order lines.
- **`Order/Contact`** — Customer contact information embedded in orders.
- **`Order/Customer`** — Customer details and relationship.
- **`HasRefunds` trait** — Attached to models that support refunds; provides `refunds()` relation and refund status checks.
- **`OrderStatus` enum** (`src/Enum/OrderStatus.php`) — Defines valid order states (pending, processing, completed, canceled, etc.).

## Key Workflows

### Creating an Order

1. Create `Order` instance with customer details via `Order/Contact` and `Order/Customer`.
2. Add line items via `Order/LineItem`.
3. Apply discounts via `Order/DiscountLine`.
4. Calculate and add taxes via `Order/TaxLine`.
5. Set status via `OrderStatus` enum (typically `pending` initially).

### Order-to-Payment Linkage

- Orders are payable entities via `PayableInterface`.
- After payment processing, update order status to `processing` or `completed`.
- Access order balance and payment attempts via Payment model relations.

### Refunds

- Models with `HasRefunds` trait can issue partial or full refunds.
- Refund operations are recorded in `Refund` model with original payment reference.
- Always check `can_refund()` before initiating refund (status, timing, processor support).

### Tax & Discount Calculations

- **Discounts** are applied per line or at order level and tracked via `DiscountLine`.
- **Taxes** are calculated per line (regional, item type) and stored in `TaxLine`.
- Total order amount = sum of line totals + taxes - discounts.

## Database Relations

- `Order` → `Order/LineItem` (hasMany)
- `Order` → `Order/DiscountLine` (hasMany)
- `Order` → `Order/TaxLine` (hasMany)
- `Order` → `Order/Contact` (morphOne or embedded)
- `Order` → `Refund` (hasMany via `HasRefunds`)

## Best Practices

- Always validate `OrderStatus` before state transitions.
- Use `HasRefunds` trait methods to check refund eligibility before processing.
- Store order snapshots for audit trails (line items, prices, customer, discounts, taxes).
- Never modify finalized order details directly; create amendments via service methods.
- Link orders to `Payment` and `WalletTransaction` records for complete audit trail.
