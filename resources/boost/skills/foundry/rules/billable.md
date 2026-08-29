# Billable Management Rules

The `Foundry\Billable` modular package provides a unified way to handle automated payments for subscriptions across different providers (Stripe, GoCardless) without depending on Laravel Cashier.

## Core Components
- **Manager**: `Foundry\Billable\BillableManager` — Main entry point for `setup()`, `remove()`, and `charge()`.
- **Logic**: Encapsulated within the `Foundry\Billable` namespace in `src/Billable`.
- **Models**:
  - `Foundry\Billable\Models\Customer`: Stores payment provider customer IDs.
  - `Foundry\Billable\Models\PaymentMethod`: Stores user payment method tokens and references.

## Usage
- **Enable Auto-Renewal**:
  ```php
  use Foundry\Billable\BillableManager;
  
  $manager = new BillableManager($subscription);
  $manager->setProvider('stripe')
          ->setPaymentMethod($token)
          ->setup();
  ```
- **Charge Subscription**:
  The system automatically listens for the `Foundry\Events\SubscriptionRenewed` event via `Foundry\Billable\Listeners\ChargeRenewalPayment`.
  ```php
  (new BillableManager($subscription))->charge();
  ```

## Webhooks
- **Stripe**: Handled by `Foundry\Billable\Listeners\StripeWebhookListener` which listens to `Foundry\Events\Stripe\WebhookReceived`.
- **Events Support**: Supports `payment_intent.requires_action`, `setup_intent.succeeded`, and `invoice.payment_succeeded`.

## Best Practices
- Always check `$subscription->auto_renewal_enabled` before attempting manual charges.
- Use `BillableManager` to ensure consistent logging across different providers.
- Handle `PaymentIncomplete` and `PaymentException` when calling `charge()` manually to manage 3DS and failed cards.
