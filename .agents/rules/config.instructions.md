---
applyTo: config/**/*.php
---

# Configuration Files Instructions

## Purpose

Configuration files define the package's behavior and integration points. The main config file is `config/foundry.php`.

## Configuration Structure

### Package Configuration (`config/foundry.php`)

The config file is organized into logical sections:

1. **Models** - Configurable model bindings
2. **Subscription** - Subscription behavior settings
3. **Shop** - E-commerce settings
4. **Settings Override** - Database-driven config mapping

## Best Practices

### 1. Environment Variables

Always provide environment variable fallbacks:

```php
'subscription' => [
    'anchor_from_invoice' => env('SUBSCRIPTION_ANCHOR_FROM_INVOICE', true),
    'downgrade_timing' => env('SUBSCRIPTION_DOWNGRADE_TIMING', 'next_renewal'),
],
```

### 2. Sensible Defaults

Provide safe, production-ready defaults:

```php
'shop' => [
    'abandoned_cart_hours' => env('ABANDONED_CART_HOURS', 2),
    'currency' => env('SHOP_CURRENCY', 'USD'),
    'tax_rate' => env('SHOP_TAX_RATE', 0),
],
```

### 3. Documentation

Add inline comments explaining options:

```php
'subscription' => [
    // Controls when plan downgrades take effect:
    // 'immediate' - Apply downgrade immediately upon request
    // 'next_renewal' - Schedule downgrade for next billing cycle (default)
    'downgrade_timing' => env('SUBSCRIPTION_DOWNGRADE_TIMING', 'next_renewal'),

    // When true, sets subscription start date from invoice date
    // Useful for handling late payments and maintaining billing cycles
    'anchor_from_invoice' => env('SUBSCRIPTION_ANCHOR_FROM_INVOICE', true),
],
```

### 4. Model Bindings

Configure model bindings via `Foundry` static properties for package extensibility:

```php
// In service provider boot method
Foundry::useUserModel(\App\Models\User::class);
Foundry::useAdminModel(\App\Models\Admin::class);
Foundry::useSubscriptionModel(\Foundry\Models\Subscription::class);
Foundry::useOrderModel(\Foundry\Models\Order::class);
Foundry::usePlanModel(\Foundry\Models\Subscription\Plan::class);
Foundry::useCouponModel(\Foundry\Models\Coupon::class);
```

### 5. Settings Override System

Map database settings to Laravel config:

```php
'settings_override' => [
    'app.name' => 'app_name',
    'app.url' => 'app_url',
    'mail.from.address' => 'mail_from_address',
    'mail.from.name' => 'mail_from_name',
    'services.stripe.key' => 'stripe_key',
    'services.stripe.secret' => 'stripe_secret',
],
```

This allows database-driven configuration via `Setting` model:

```php
// Update config at runtime
Setting::updateValue('app_name', 'My Application');

// Config is automatically synced
config('app.name'); // Returns 'My Application'
```

## Configuration Patterns

### Subscription Configuration

```php
'subscription' => [
    // Billing cycle behavior
    'anchor_from_invoice' => env('SUBSCRIPTION_ANCHOR_FROM_INVOICE', true),
    'downgrade_timing' => env('SUBSCRIPTION_DOWNGRADE_TIMING', 'next_renewal'),

    // Grace period settings
    'grace_period_days' => env('SUBSCRIPTION_GRACE_PERIOD', 7),

    // Trial defaults
    'default_trial_days' => env('SUBSCRIPTION_DEFAULT_TRIAL', 0),

    // Payment retry configuration
    'max_payment_retries' => env('SUBSCRIPTION_MAX_RETRIES', 3),
    'retry_interval_hours' => env('SUBSCRIPTION_RETRY_INTERVAL', 24),
],
```

### Payment Gateway Configuration

```php
'gateways' => [
    'stripe' => [
        'enabled' => env('STRIPE_ENABLED', true),
        'mode' => env('STRIPE_MODE', 'test'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
    'paypal' => [
        'enabled' => env('PAYPAL_ENABLED', false),
        'mode' => env('PAYPAL_MODE', 'sandbox'),
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
    ],
],
```

### Feature Flags

```php
'features' => [
    'enable_trials' => env('ENABLE_TRIALS', true),
    'enable_intro_pricing' => env('ENABLE_INTRO_PRICING', true),
    'enable_coupons' => env('ENABLE_COUPONS', true),
    'enable_gift_cards' => env('ENABLE_GIFT_CARDS', false),
],
```

## Accessing Configuration

### In Application Code

```php
// Get config value
$downgradeTiming = config('foundry.subscription.downgrade_timing');

// Get with default
$gracePeriod = config('foundry.subscription.grace_period_days', 7);

// Get model binding
$userModel = Foundry::$userModel;
$adminModel = Foundry::$adminModel;
$orderModel = Foundry::$orderModel;
```

### In Service Providers

```php
public function register()
{
    // Merge package config
    $this->mergeConfigFrom(
        __DIR__.'/../../config/foundry.php',
        'foundry'
    );
}

public function boot()
{
    // Configure model bindings
    Foundry::useUserModel(\App\Models\User::class);
    Foundry::useAdminModel(\App\Models\Admin::class);
    Foundry::useOrderModel(\Foundry\Models\Order::class);
}
```

### Publishing Configuration

Users can publish and customize the config:

```bash
php artisan vendor:publish --tag=foundry-config
```

## Testing Configuration

### Override Config in Tests

```php
public function test_immediate_downgrade_when_configured()
{
    config(['foundry.subscription.downgrade_timing' => 'immediate']);

    $response = $this->post('/subscriptions/downgrade');

    // Assert immediate downgrade behavior
}
```

### Test Environment Variables

```php
public function test_respects_environment_variable()
{
    // Set in phpunit.xml.dist
    $this->assertEquals('next_renewal', env('SUBSCRIPTION_DOWNGRADE_TIMING'));
}
```

## Configuration Validation

### Service Provider Validation

```php
public function boot()
{
    // Validate critical configuration
    if (!Foundry::$userModel) {
        throw new \RuntimeException('User model not configured');
    }

    // Validate enum values
    $downgradeTiming = config('foundry.subscription.downgrade_timing');
    if (!in_array($downgradeTiming, ['immediate', 'next_renewal'])) {
        throw new \InvalidArgumentException(
            "Invalid downgrade_timing: {$downgradeTiming}"
        );
    }
}
```

## Configuration Best Practices

### ✅ Do: Use Type Hints in Comments

```php
// int - Number of days for grace period
'grace_period_days' => env('SUBSCRIPTION_GRACE_PERIOD', 7),

// bool - Enable trial subscriptions
'enable_trials' => env('ENABLE_TRIALS', true),

// string: 'immediate' | 'next_renewal'
'downgrade_timing' => env('SUBSCRIPTION_DOWNGRADE_TIMING', 'next_renewal'),
```

### ✅ Do: Group Related Settings

```php
'subscription' => [
    'anchor_from_invoice' => true,
    'downgrade_timing' => 'next_renewal',
    'grace_period_days' => 7,
],
```

### ✅ Do: Document Breaking Changes

```php
// BREAKING: Changed from 'cancels_at' to 'canceled_at' in v6.0
// Migration required when upgrading
'subscription' => [
    'use_legacy_cancellation' => env('USE_LEGACY_CANCELLATION', false),
],
```

### ❌ Don't: Hardcode Credentials

```php
// Bad - hardcoded secret
'stripe_secret' => 'sk_test_hardcoded_key',

// Good - uses environment variable
'stripe_secret' => env('STRIPE_SECRET'),
```

### ❌ Don't: Use Complex Logic

Configuration should be simple data structures, not business logic:

```php
// Bad - complex logic in config
'price' => $this->calculatePrice(),

// Good - simple value
'price' => env('DEFAULT_PRICE', 9.99),
```

## Environment Configuration

### .env Example

```env
# App Configuration
APP_NAME="Laravel Core"
APP_URL=https://example.com

# Subscription Configuration
SUBSCRIPTION_DOWNGRADE_TIMING=next_renewal
SUBSCRIPTION_ANCHOR_FROM_INVOICE=true
SUBSCRIPTION_GRACE_PERIOD=7

# Shop Configuration
ABANDONED_CART_HOURS=2
SHOP_CURRENCY=USD

# Payment Gateways
STRIPE_ENABLED=true
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...

PAYPAL_ENABLED=false
PAYPAL_MODE=sandbox
```

## Backward Compatibility

When adding new configuration options:

1. **Always provide defaults** that maintain existing behavior
2. **Document changes** in RELEASE_NOTES.md
3. **Mark breaking changes** clearly
4. **Provide migration guides** for config changes

Example:

```php
// New in v6.0 - defaults to 'next_renewal' for backward compatibility
'downgrade_timing' => env('SUBSCRIPTION_DOWNGRADE_TIMING', 'next_renewal'),
```
