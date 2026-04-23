# Payment Processing

## Summary
Expertise in multi-gateway integration supporting 20+ payment providers. Foundry uses an abstraction layer to handle diverse payment flows (SDK, Redirect, Webhook).

## Key Components
- **`AbstractPaymentProcessor`**: Base class for all gateway implementations.
- **`PaymentResult`**: Standardized DTO for transaction outcomes.
- **`StripeController` / `PayPalController`**: Gateway-specific webhook handlers.
- **`PaymentMethod`**: Model for storing tokenized gateway references (Stripe PM IDs, etc.).

## Payment Flows
1. **Direct SDK**: Client-side tokenization (Stripe Elements) → Backend charge.
2. **Redirect**: Backend initiate → Gateway UI → Callback handling.
3. **Idempotency**: Webhooks must be verified and event IDs cached to prevent double-processing.

## When to Activate
- Integrating new payment processors
- Processing payments or refunds
- Handling payment webhooks
- Implementing payment method storage
- Working with PaymentResult or CallbackResult
- Multi-processor payment orchestration

## Reference
See `resources/boost/skills/foundry/rules/payments.md` for detailed patterns.
