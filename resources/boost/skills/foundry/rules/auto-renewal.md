# Auto-Renewal Management Rules

The `Foundry\AutoRenewal` modular package provides a unified way to handle automated payments for subscriptions across different providers (Stripe, GoCardless) without depending on Laravel Cashier.

## Core Components
- **Manager**: `Foundry\AutoRenewal\AutoRenewalManager` — Main entry point for `setup()`, `remove()`, and `charge()`.
- **Logic**: Encapsulated within the `Foundry\AutoRenewal` namespace in `src/AutoRenewal`.
- **Models**:
  - `Foundry\AutoRenewal\Models\Customer`: Stores payment provider customer IDs.
  - `Foundry\AutoRenewal\Models\PaymentMethod`: Stores user payment method tokens and references.

## Usage
- **Enable Auto-Renewal**:
  ```php
  use Foundry\AutoRenewal\AutoRenewalManager;
  
  $manager = new AutoRenewalManager($subscription);
  $manager->setProvider('stripe')
          ->setPaymentMethod($token)
          ->setup();
  ```
- **Charge Subscription**:
  The system automatically listens for the `Foundry\Events\SubscriptionRenewed` event via `Foundry\AutoRenewal\Listeners\ChargeRenewalPayment`.
  ```php
  (new AutoRenewalManager($subscription))->charge();
  ```

## Webhooks
- **Stripe**: Handled by `Foundry\AutoRenewal\Listeners\StripeWebhookListener` which listens to `Foundry\Events\Stripe\WebhookReceived`.
- **Events Support**: Supports `payment_intent.requires_action`, `setup_intent.succeeded`, and `invoice.payment_succeeded`.

## Best Practices
- Always check `$subscription->auto_renewal_enabled` before attempting manual charges.
- Use `AutoRenewalManager` to ensure consistent logging across different providers.
- Handle `PaymentIncomplete` and `PaymentException` when calling `charge()` manually to manage 3DS and failed cards.
