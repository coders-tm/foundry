# Payment Processor Feature Tests

This directory contains comprehensive feature tests for payment processors integration.

## Test Files

### FlutterwaveProcessorTest.php

Comprehensive test suite for Flutterwave payment gateway integration.

**Test Coverage:**

-   ✅ Payment method configuration and credential management
-   ✅ Laravel config synchronization from database
-   ✅ Payment method lookup and retrieval
-   ✅ Processor factory integration
-   ✅ Payment intent setup with checkout
-   ✅ Success/cancel callback handling
-   ✅ Token validation for payment operations
-   ✅ Public payment methods API
-   ✅ Payment method enable/disable functionality
-   ✅ Dynamic cache updates on credential changes

**Total Tests:** 18 tests, 56 assertions

### GocardlessIntegrationTest.php

Comprehensive test suite for GoCardless Direct Debit payment integration.

**Test Coverage:**

-   ✅ Payment method configuration with sandbox token
-   ✅ Laravel config synchronization (environment, access token, schemes)
-   ✅ Multi-country payment scheme support (BACS, SEPA, ACH, BECS, etc.)
-   ✅ Sandbox/Live environment configuration
-   ✅ GoCardless client initialization
-   ✅ Subscription gateway integration
-   ✅ Webhook configuration
-   ✅ Payment method enable/disable functionality
-   ✅ Dynamic cache updates on credential changes
-   ✅ Access token validation

**Total Tests:** 20 tests, 65 assertions

### RazorpayProcessorTest.php

Comprehensive test suite for Razorpay payment gateway integration.

**Test Coverage:**

-   ✅ Payment method configuration and credential management
-   ✅ Laravel config synchronization from database
-   ✅ Test API key format validation
-   ✅ Payment method lookup and retrieval
-   ✅ Processor factory integration
-   ✅ Razorpay SDK client initialization
-   ✅ Payment order setup with checkout
-   ✅ Success/cancel callback handling
-   ✅ Token validation for payment operations
-   ✅ Public payment methods API
-   ✅ Payment methods support (card, upi, netbanking, wallet, emi)
-   ✅ Payment method enable/disable functionality
-   ✅ Dynamic cache updates on credential changes

**Total Tests:** 23 tests, 67 assertions

### PaypalProcessorTest.php

Comprehensive test suite for PayPal payment gateway integration.

**Test Coverage:**

-   ✅ Payment method configuration and credential management
-   ✅ Laravel config synchronization from database
-   ✅ Sandbox/Live mode configuration
-   ✅ Payment method lookup and retrieval
-   ✅ Processor factory integration
-   ✅ PayPal SDK client initialization
-   ✅ Payment order setup with checkout
-   ✅ Success/cancel callback handling
-   ✅ Token validation for payment operations
-   ✅ Public payment methods API
-   ✅ Payment methods support (paypal, card, credit)
-   ✅ Webhook URL configuration
-   ✅ Credential format validation
-   ✅ Payment method enable/disable functionality
-   ✅ Dynamic cache updates on credential changes

**Total Tests:** 27 tests, 67 assertions

### KlarnaProcessorTest.php

Comprehensive test suite for Klarna payment gateway integration.

**Test Coverage:**

-   ✅ Payment method configuration and credential management
-   ✅ Laravel config synchronization from database
-   ✅ API key format validation (UUID)
-   ✅ API secret format validation (klarna*test_api* prefix)
-   ✅ Payment method lookup and retrieval
-   ✅ Processor factory integration
-   ✅ Klarna custom client initialization
-   ✅ Payment session setup with checkout
-   ✅ Success/cancel callback handling
-   ✅ Token validation for payment operations
-   ✅ Public payment methods API (both credentials private)
-   ✅ Payment methods support (pay_later, pay_in_3, pay_now, slice_it)
-   ✅ Test/Production mode configuration
-   ✅ Webhook URL configuration
-   ✅ Payment method enable/disable functionality
-   ✅ Dynamic cache updates on credential changes

**Total Tests:** 29 tests, 76 assertions

## Test Configuration

### Flutterwave Test Credentials

The tests use placeholder Flutterwave test credentials:

```php
'CLIENT_ID' => '<FLUTTERWAVE_CLIENT_ID>'
'CLIENT_SECRET' => '<FLUTTERWAVE_CLIENT_SECRET>'
'ENCRYPTION_KEY' => '<FLUTTERWAVE_ENCRYPTION_KEY>'
```

These credentials are configured via the `PaymentMethod` model and automatically synced to Laravel config.

### GoCardless Test Credentials

The tests use placeholder GoCardless sandbox token:

```php
'ACCESS_TOKEN' => '<GOCARDLESS_ACCESS_TOKEN>'
'WEBHOOK_SECRET' => '<GOCARDLESS_WEBHOOK_SECRET>'
```

GoCardless uses Direct Debit for recurring subscription billing across multiple countries.

### Razorpay Test Credentials

The tests use placeholder Razorpay test credentials:

```php
'API_KEY' => '<RAZORPAY_API_KEY>'
'API_SECRET' => '<RAZORPAY_API_SECRET>'
```

Razorpay provides payment processing for India with support for cards, UPI, netbanking, wallets, and EMI.

### PayPal Test Credentials

The tests use placeholder PayPal sandbox credentials:

```php
'CLIENT_ID' => '<PAYPAL_CLIENT_ID>'
'CLIENT_SECRET' => '<PAYPAL_CLIENT_SECRET>'
```

PayPal provides global payment processing with support for PayPal balance, credit/debit cards, and PayPal Credit.

### Klarna Test Credentials

The tests use placeholder Klarna test credentials:

```php
'API_USERNAME' => '<KLARNA_API_USERNAME>'
'API_PASSWORD' => '<KLARNA_API_PASSWORD>'
```

Klarna provides buy-now-pay-later payment solutions with flexible payment methods including pay later, pay in installments, and direct payments.

### Configuration Mapping

The `PaymentMethod` model maps credentials to Laravel config as follows:

**Flutterwave:**

```php
'flutterwave.id' => $paymentMethod->id
'flutterwave.public_key' => $paymentMethod->configs['CLIENT_ID']
'flutterwave.secret_key' => $paymentMethod->configs['CLIENT_SECRET']
'flutterwave.encryption_key' => $paymentMethod->configs['ENCRYPTION_KEY']
'flutterwave.environment' => 'sandbox' (test_mode) or 'live'
'flutterwave.webhook_url' => $paymentMethod->webhook
'flutterwave.enabled' => $paymentMethod->active
```

**GoCardless:**

```php
'gocardless.id' => $paymentMethod->id
'gocardless.environment' => 'sandbox' (test_mode) or 'live'
'gocardless.access_token' => $paymentMethod->configs['ACCESS_TOKEN']
'gocardless.webhook_secret' => $paymentMethod->configs['WEBHOOK_SECRET']
'gocardless.webhook_url' => $paymentMethod->webhook
'gocardless.schemes' => [
    'GB' => 'bacs',      // UK
    'DE' => 'sepa_core', // Germany
    'FR' => 'sepa_core', // France
    'ES' => 'sepa_core', // Spain
    'IT' => 'sepa_core', // Italy
    'NL' => 'sepa_core', // Netherlands
    'BE' => 'sepa_core', // Belgium
    'AU' => 'becs',      // Australia
    'NZ' => 'becs_nz',   // New Zealand
    'US' => 'ach',       // USA
    'CA' => 'pad',       // Canada
    'SE' => 'autogiro',  // Sweden
]
'gocardless.enabled' => $paymentMethod->active
```

**Razorpay:**

```php
'razorpay.id' => $paymentMethod->id
'razorpay.key_id' => $paymentMethod->configs['API_KEY']
'razorpay.key_secret' => $paymentMethod->configs['API_SECRET']
'razorpay.enabled' => $paymentMethod->active
```

**PayPal:**

```php
'paypal.id' => $paymentMethod->id
'paypal.mode' => 'sandbox' (test_mode) or 'live'
'paypal.sandbox.client_id' => $paymentMethod->configs['CLIENT_ID'] (for sandbox)
'paypal.sandbox.client_secret' => $paymentMethod->configs['CLIENT_SECRET'] (for sandbox)
'paypal.live.client_id' => $paymentMethod->configs['CLIENT_ID'] (for live)
'paypal.live.client_secret' => $paymentMethod->configs['CLIENT_SECRET'] (for live)
'paypal.notify_url' => $paymentMethod->webhook
'paypal.enabled' => $paymentMethod->active
```

**Klarna:**

```php
'klarna.id' => $paymentMethod->id
'klarna.api_key' => $paymentMethod->configs['API_USERNAME']
'klarna.api_secret' => $paymentMethod->configs['API_PASSWORD']
'klarna.test_mode' => $paymentMethod->test_mode
'klarna.webhook_url' => $paymentMethod->webhook
'klarna.enabled' => $paymentMethod->active
```

## Running Tests

### Run All Payment Tests

```bash
vendor/bin/phpunit tests/Feature/Payment/
```

### Run Specific Test File

```bash
vendor/bin/phpunit tests/Feature/Payment/FlutterwaveProcessorTest.php
```

### Run with Detailed Output

```bash
vendor/bin/phpunit tests/Feature/Payment/ --testdox
```

### Run Specific Test

```bash
vendor/bin/phpunit tests/Feature/Payment/FlutterwaveProcessorTest.php --filter=it_syncs_flutterwave_configuration_to_laravel_config
```

## Test Patterns

### 1. Payment Method Setup

Tests create payment methods using the `PaymentMethod::create()` method with full credentials:

```php
$paymentMethod = PaymentMethod::create([
    'name' => 'Flutterwave',
    'provider' => PaymentMethod::FLUTTERWAVE,
    'active' => true,
    'test_mode' => true,
    'credentials' => collect([
        ['key' => 'CLIENT_ID', 'value' => '<FLUTTERWAVE_CLIENT_ID>', 'publish' => true],
        ['key' => 'CLIENT_SECRET', 'value' => '<FLUTTERWAVE_CLIENT_SECRET>', 'publish' => false],
        ['key' => 'ENCRYPTION_KEY', 'value' => '<FLUTTERWAVE_ENCRYPTION_KEY>', 'publish' => false],
    ]),
]);

// Update cache to apply configuration
PaymentMethod::updateProviderCache(PaymentMethod::FLUTTERWAVE);
```

### 2. Using Factory States

For simpler tests, use factory states:

```php
// Flutterwave
$paymentMethod = PaymentMethod::factory()
    ->flutterwave()
    ->active()
    ->create();

// GoCardless
$paymentMethod = PaymentMethod::factory()
    ->gocardless()
    ->active()
    ->create();

// Razorpay
$paymentMethod = PaymentMethod::factory()
    ->razorpay()
    ->active()
    ->create();

// PayPal
$paymentMethod = PaymentMethod::factory()
    ->paypal()
    ->active()
    ->create();
```

### 3. Processor Integration

Tests verify the processor can be created and used:

```php
$processor = Processor::make('flutterwave');
$this->assertEquals('flutterwave', $processor->getProvider());
```

### 4. Checkout Flow Testing

Tests create full checkout flows:

```php
// 1. Add items to cart
$cartController->add($request);

// 2. Create checkout
$checkout = $checkoutController->index($request);

// 3. Add customer information
$checkoutController->update($request, $checkoutToken);

// 4. Setup payment intent
$shopController->setupPaymentIntent($request, 'flutterwave');
```

## Key Test Scenarios

### Configuration Management

-   ✅ Payment method credentials are stored correctly
-   ✅ Credentials sync to Laravel config automatically
-   ✅ Config includes environment (sandbox/live) based on test_mode
-   ✅ Cache updates when credentials change

### Provider Discovery

-   ✅ `PaymentMethod::has('flutterwave')` returns true when active
-   ✅ `PaymentMethod::findProvider('flutterwave')` finds the method
-   ✅ `PaymentMethod::flutterwave()` static method works
-   ✅ Processor factory supports Flutterwave

### Payment Operations

-   ✅ Setup payment intent with valid checkout
-   ✅ Validate checkout tokens
-   ✅ Handle success/cancel redirects
-   ✅ Public API excludes sensitive credentials

### Lifecycle Management

-   ✅ Enable/disable payment method
-   ✅ Delete payment method (removes from cache)
-   ✅ Update credentials (cache auto-updates)
-   ✅ Processor requires active payment method

## Important Notes

### Route Dependencies

Some tests may skip or adapt behavior if routes are not defined in the test environment:

-   `shop.checkout.success` - Success redirect route
-   `shop.checkout.cancel` - Cancel redirect route
-   `webhooks.flutterwave` - Webhook handler route

Tests handle missing routes gracefully and verify error structures.

### SDK Integration

The Flutterwave processor uses the official Flutterwave PHP SDK v3. Tests verify:

-   SDK receives correct configuration
-   Constants are defined (FLW_SECRET_KEY, FLW_PUBLIC_KEY, etc.)
-   Environment is set correctly (sandbox/live)

### Cache Management

Tests rely on the `PaymentMethod` model's automatic cache management:

-   `PaymentMethod::updateProviderCache($provider)` - Updates specific provider
-   `PaymentMethod::syncConfig()` - Syncs all providers
-   `PaymentMethod::applyProviderConfig($provider)` - Applies cached config

The model's `saved` and `deleted` observers automatically update the cache.

## Test Database

Tests use Laravel's RefreshDatabase trait, ensuring a clean database state for each test.

The base test class (`FeatureTestCase`) handles:

-   Database migrations
-   Model bindings (User, Admin models)
-   Session management
-   Factory setup

## Assertions Used

Common assertions in payment tests:

```php
// Configuration
$this->assertEquals($expected, config('flutterwave.public_key'));
$this->assertTrue(config('flutterwave.enabled'));

// Payment Method
$this->assertNotNull(PaymentMethod::flutterwave());
$this->assertTrue($paymentMethod->payable());
$this->assertIsArray($paymentMethod->methods);

// Processor
$this->assertInstanceOf(FlutterwaveProcessor::class, $processor);
$this->assertTrue(Processor::isSupported('flutterwave'));

// Responses
$this->assertEquals(200, $response->getStatusCode());
$this->assertArrayHasKey('payment_url', $data);
$this->assertTrue($response->isRedirect());
```

## Contributing

When adding new payment processor tests:

1. Create a new test file: `{Provider}ProcessorTest.php`
2. Extend `FeatureTestCase`
3. Set up payment method in `setUp()`
4. Follow the existing test patterns
5. Test all critical scenarios:
    - Configuration sync
    - Provider discovery
    - Payment intent setup
    - Callback handling
    - Cache management
6. Add factory state to `PaymentMethodFactory`
7. Update this README with new test file information

### Factory States

All payment processors have factory states for quick test setup:

**Flutterwave:**

```php
$paymentMethod = PaymentMethod::factory()->flutterwave()->create();
```

**GoCardless:**

```php
$paymentMethod = PaymentMethod::factory()->gocardless()->create();
```

**Razorpay:**

```php
$paymentMethod = PaymentMethod::factory()->razorpay()->create();
```

**PayPal:**

```php
$paymentMethod = PaymentMethod::factory()->paypal()->create();
```

**Klarna:**

```php
$paymentMethod = PaymentMethod::factory()->klarna()->create();
```

## Related Documentation

-   [Payment Processor Architecture](../../../src/Payment/README.md)
-   [PaymentMethod Model](../../../src/Models/PaymentMethod.php)
-   [Flutterwave Processor](../../../src/Payment/Processors/FlutterwaveProcessor.php)
-   [Unit Tests](../../Unit/Payment/README.md)
