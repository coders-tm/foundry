# Payment Processing Domain

## Overview

The Payment Processing domain orchestrates payment collection across 20+ processors (Stripe, PayPal, Razorpay, Xendit, GoCardless, MercadoPago, etc.) with support for both SDK-based and redirect flows, webhooks, refunds, and idempotency.

## Core Abstractions

### Payment Processors

- **`AbstractPaymentProcessor`** (`src/Payment/AbstractPaymentProcessor.php`) — Base class for all processor implementations.
- **12+ Processor Classes** in `src/Payment/Processors/`:
  - `StripeProcessor`, `PayPalProcessor`, `RazorpayProcessor`, `XenditProcessor`, `GoCardlessProcessor`, `MercadoPagoProcessor`, `FlutterWaveProcessor`, `CoinbaseProcessor`, `SrmkliveProcessor`, `TwilioProcessor`, `NetsProcessor`, etc.
  - Each extends `AbstractPaymentProcessor` and implements processor-specific logic.

### Processor Factory & Selection

- **`Processor` factory** (`src/Payment/Processor.php`) — Returns the correct processor instance for a given gateway key.
- Selection: `Processor::for('stripe')` returns `StripeProcessor` instance.

### Payment Result Objects

- **`PaymentResult`** — Encapsulates response from a payment attempt (success, failure, pending, redirect URL).
- **`CallbackResult`** — Parsed webhook callback data from processor (transaction ID, status, amount).
- **`RefundResult`** — Response from a refund operation (success, failure, refund ID).

### Contracts

- **`PayableInterface`** — Models that can be paid (Order, Subscription).
  - Methods: `getGrandTotal()`, `getReferenceId()`, `getSource()`, `isOrder()`, and other payable metadata accessors.
- **`PaymentInterface`** — Describes a payment within the system.
- **`PaymentProcessorInterface`** — Contract for all processors.

## Payment Flows

### SDK Flow (Direct)

1. Client-side collects card/account details and sends to processor API directly.
2. Processor returns token/payment method ID.
3. Server receives token and calls `Processor::for('stripe')->charge($payable, $token)`.
4. Result is a `PaymentResult` with status.
5. Store in `Payment` model with reference to payable.

**Supported by**: Stripe, Razorpay, Xendit, GoCardless, and others.

### Redirect Flow

1. Server calls `Processor::for('paypal')->getAuthorizationUrl($payable)`.
2. Returns `PaymentResult` with `redirect_url`.
3. User redirected to processor.
4. Processor redirects back to callback URL with authorization code.
5. Server exchanges code for payment via webhook or polling.

**Supported by**: PayPal, MercadoPago, Coinbase, and others.

## Webhook Handling

- All processors emit webhooks on transaction status changes (charge, refund, chargeback).
- **Idempotency**: Always check if webhook was already processed (via `Payment.external_id` + processor).
- Entry point: `src/Http/Controllers/Webhook/PaymentWebhookController.php`.
- Event dispatched: `PaymentWebhookReceived` or processor-specific events.

## Refunds

1. Call `Processor::for('stripe')->refund($payment, $amount)`.
2. Returns `RefundResult` with refund ID.
3. Store in `Refund` model linked to original `Payment`.
4. Update order/subscription status if full refund.

## Key Models

- **`Payment`** (`src/Models/Payment.php`) — Tracks all payment attempts, processor, reference ID, status.
- **`PaymentMethod`** (`src/Models/PaymentMethod.php`) — Stored cards, accounts, or payment method tokens.
- **`Refund`** (`src/Models/Refund.php`) — Records refund operations with amounts and statuses.

## Best Practices

- **Always check `PaymentResult->isSuccessful()`** before marking payable as paid.
- **Idempotency**: Use processor reference ID + processor name as composite key to avoid duplicate charges.
- **Webhook validation**: Verify webhook signature/token before processing.
- **Error handling**: Distinguish transient failures (retry) from permanent rejections (user action needed).
- **PCI compliance**: Never store raw card data; use tokens or saved payment methods.
- **Testing**: Use processor-provided test keys and mock cards.

## Common Tasks

### Charge a Payment

```php
$processor = Processor::for('stripe');
$result = $processor->charge($order, $paymentMethodToken);
if ($result->isSuccessful()) {
    Payment::create(['order_id' => $order->id, 'processor' => 'stripe', 'reference' => $result->transactionId]);
    $order->markAsPaid();
}
```

### Issue a Refund

```php
$processor = Processor::for('stripe');
$result = $processor->refund($payment, $amount);
if ($result->isSuccessful()) {
    Refund::create(['payment_id' => $payment->id, 'amount' => $amount, 'reference' => $result->refundId]);
}
```

### Handle Webhook

```php
// In PaymentWebhookController or listener
$callbackResult = Processor::for($processor)->parseCallback($request);
$payment = Payment::where('reference', $callbackResult->transactionId)->first();
if ($payment && !$payment->webhook_processed) {
    $payment->update(['status' => $callbackResult->status, 'webhook_processed' => true]);
    // Dispatch event or update payable
}
```
