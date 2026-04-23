---
description: How to add a new payment method with support for SDK or Redirect flows
---

# Adding a New Payment Method

This workflow outlines the steps to integrate a new payment method into the Foundry payment system.

## 1. Define the Provider

Add the provider constant to the `PaymentMethod` model.

**File**: [`src/Models/PaymentMethod.php`](file:///Volumes/Portable/foundry-source/src/Models/PaymentMethod.php)

```php
const NEW_PROVIDER = 'new_provider';
```

## 2. Update Seed Data

Add the new payment method to the default seeder data.

**File**: [`stubs/database/data/payment-methods.json`](file:///Volumes/Portable/foundry-source/stubs/database/data/payment-methods.json)

```json
{
    "name": "New Provider",
    "provider": "new_provider",
    "active": true,
    "credentials": [
        { "key": "PUBLIC_KEY", "value": "", "label": "Public Key", "publish": true },
        { "key": "SECRET_KEY", "value": "", "label": "Secret Key", "publish": false }
    ]
}
```

## 3. Create the Payment Mapper

The mapper handles converting provider-specific responses to the internal `Payment` model and provides a human-readable display string.

**File**: `src/Payment/Mappers/NewProviderPayment.php`

```php
namespace Foundry\Payment\Mappers;

use Foundry\Contracts\PaymentInterface;
use Foundry\Models\PaymentMethod;

class NewProviderPayment implements PaymentInterface
{
    protected array $metadata = [];

    public function __construct(protected $response, protected ?PaymentMethod $paymentMethod = null)
    {
        $this->metadata = $this->extractMetadata($response);
    }

    public function getTransactionId(): string { return $this->response['id']; }
    public function getAmount(): float { return $this->response['amount']; }
    public function getStatus(): string { return 'completed'; }
    public function getMetadata(): array { return $this->metadata; }

    public function toArray(): array
    {
        return [
            'transaction_id' => $this->getTransactionId(),
            'amount' => $this->getAmount(),
            'status' => $this->getStatus(),
            'metadata' => $this->getMetadata(),
        ];
    }

    public function toString(): string
    {
        return $this->metadata['payment_method'] ?? 'New Provider';
    }

    protected function extractMetadata($response): array
    {
        $normalized = [
            'raw_status' => $response['status'] ?? null,
            // ... add other relevant fields
        ];

        // Build human-readable display string
        $normalized['payment_method'] = $this->buildDisplayString($normalized);

        return array_filter($normalized);
    }

    private function buildDisplayString(array $metadata): string
    {
        // Logic to return a friendly name like "Credit Card (Visa)"
        return 'New Provider';
    }
}
```

## 4. Create the Payment Processor

Extend `AbstractPaymentProcessor` and implement the necessary methods.

**File**: `src/Payment/Processors/NewProviderProcessor.php`

### Choose Your Flow:

#### A. SDK-Based (e.g., Stripe, Razorpay)
Best for methods where the frontend uses an SDK and the backend confirms the payment intent.

```php
public function setupPaymentIntent(Request $request, Payable $payable): array
{
    // Create intent with provider and return secrets
    return [
        'client_secret' => $clientSecret,
        'payment_intent_id' => $id,
    ];
}

public function confirmPayment(Request $request, Payable $payable): PaymentResult
{
    // Verify payment status with provider
    $response = $provider->verify($request->payment_intent_id);
    return PaymentResult::success(new NewProviderPayment($response, $this->paymentMethod));
}
```

#### B. Redirect-Based (e.g., Alipay, Xendit)
Best for methods that redirect the user to a checkout page.

```php
public function setupPaymentIntent(Request $request, Payable $payable): array
{
    // 1. Create a pending payment record to get a UUID
    $payment = Payment::create([
        'paymentable_type' => $payable->isOrder() ? Foundry::$orderModel : get_class($payable->getSource()),
        'paymentable_id' => $payable->getSourceId(),
        'payment_method_id' => $this->getPaymentMethodId(),
        'amount' => $payable->getGrandTotal(),
        'status' => 'pending',
    ]);

    // 2. Return redirect URL with state=uuid
    return [
        'redirect_url' => $this->getSuccessUrl(['state' => $payment->uuid]),
        'payment_intent_id' => $payable->getReferenceId(),
        'state_id' => $payment->uuid,
    ];
}

public function handleSuccessCallback(Request $request): CallbackResult
{
    $stateId = $request->query('state');
    $payment = Payment::where('uuid', $stateId)->firstOrFail();
    
    // Verify and update
    $response = $provider->verify();
    $payment->update((new NewProviderPayment($response))->toArray());
    
    return CallbackResult::success(payment: $payment->fresh());
}
```

## 5. Register the Processor

### Update the Factory
**File**: [`src/Payment/Processor.php`](file:///Volumes/Portable/foundry-source/src/Payment/Processor.php)

```php
public static function make(string $provider): PaymentProcessorInterface
{
    return match ($provider) {
        'new_provider' => new NewProviderProcessor(),
        // ...
    };
}
```

### Update Credential Mapping
**File**: [`src/Models/PaymentMethod.php`](file:///Volumes/Portable/foundry-source/src/Models/PaymentMethod.php)

Update `getProviderConfig()` to map database credentials to application config.

```php
case self::NEW_PROVIDER:
    return [
        'new_provider.key' => $paymentMethod->configs['PUBLIC_KEY'],
        'new_provider.secret' => $paymentMethod->configs['SECRET_KEY'],
        'new_provider.enabled' => $paymentMethod->active,
    ];
```

## 6. Create Feature Test

**File**: `tests/Feature/Payment/NewProviderProcessorTest.php`

Refer to [`AlipayProcessorTest.php`](file:///Volumes/Portable/foundry-source/tests/Feature/Payment/AlipayProcessorTest.php) for a modern test example using real database records for payment tracking.
