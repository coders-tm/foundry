---
applyTo: tests/**/*.php
---

# Testing Instructions for foundry

## Test Structure

This package uses Orchestra Testbench for Laravel package testing. Tests are organized into:

-   **Feature tests**: `tests/Feature/` - Integration tests for complete workflows
-   **Unit tests**: `tests/Unit/` - Tests for individual components

## Test Setup

### Base Test Classes

-   Extend `Foundry\Tests\TestCase` for tests that need database and full Laravel setup
-   Use `Foundry\Tests\BaseTestCase` for simpler tests without database
-   Both classes are configured to work with the workbench setup

### Running Tests

```bash
# Run all tests
vendor/bin/testbench package:test

# Run specific test file
vendor/bin/testbench package:test tests/Feature/SubscriptionTest.php

# Run specific test method
vendor/bin/testbench package:test --filter=test_subscription_renewal

# Build workbench before tests (if needed)
vendor/bin/testbench workbench:build
```

## Test Patterns

### Database Setup

-   Tests use `RefreshDatabase` trait to reset database between tests
-   Database seeder runs automatically in `TestCase::setUp()`
-   Use factories from `database/factories/` for test data

### Authentication

This package uses **Laravel Fortify** for authentication (not Sanctum). Guard names are **singular**:

-   `user` guard — regular users (`Foundry\Models\User`)
-   `admin` guard — admin users (`Foundry\Models\Admin`)

Guard resolution is handled by `Foundry\Services\GuardManager` based on the request URL prefix. In tests, authenticate with `$this->actingAs()`. **Do not use `Sanctum::actingAs()`**.

```php
// Authenticate as a user
$this->actingAs($user);          // uses default 'user' guard
$this->actingAs($user, 'user');  // explicit

// Authenticate as an admin
$this->actingAs($admin, 'admin');
```

### Model Configuration

The package has **separate User and Admin models**:

-   `Foundry\Models\Admin` - Base admin model with `admin` guard
-   `Foundry\Models\User` - User model with `user` guard and billing capabilities

In tests, create users and admins separately:

```php
$user = User::factory()->create();
$admin = Admin::factory()->create();
```

### Testing Subscriptions

-   Create test subscriptions with proper plan and gateway setup
-   Mock payment gateway responses when testing payment flows
-   Test both immediate and scheduled subscription changes
-   Verify events are dispatched correctly

### Testing API Endpoints

-   Use `actingAs()` to authenticate test users
-   Test both successful responses and error cases
-   Verify proper HTTP status codes and response structure
-   Test guard middleware with different user types

### Testing Events and Listeners

-   Use `Event::fake()` to assert events are dispatched
-   Verify event data contains expected properties
-   Test listener logic independently

## Best Practices

1. **Descriptive Test Names**: Use `test_` prefix or PHPUnit annotations with clear descriptions
2. **One Assertion Per Concept**: Each test should verify one specific behavior
3. **Arrange-Act-Assert**: Structure tests with clear setup, execution, and verification
4. **Use Factories**: Always use factories instead of creating models manually
5. **Clean Up**: Database is automatically reset, but clean up any files or external resources
6. **Test Edge Cases**: Don't just test happy paths - test validation, errors, and edge cases
7. **Mock External Services**: Mock payment gateways, notifications, and API calls

## Common Test Scenarios

### Testing Subscription Lifecycle

```php
public function test_subscription_can_be_created()
{
    $user = User::factory()->create();
    $plan = Plan::factory()->create();

    $subscription = $user->subscribe($plan);

    $this->assertTrue($subscription->active());
    $this->assertEquals($plan->id, $subscription->plan_id);
}
```

### Testing Middleware

```php
public function test_guard_middleware_redirects_unauthenticated()
{
    $response = $this->get('/api/admin/dashboard');

    $response->assertStatus(401);
}
```

### Testing Service Classes

```php
public function test_subscription_service_renews_correctly()
{
    $subscription = Subscription::factory()->expiring()->create();

    $service = new SubscriptionService();
    $service->renew($subscription);

    $this->assertTrue($subscription->fresh()->active());
}
```

## Environment Configuration

Tests run with SQLite in-memory database by default. Configuration is in `phpunit.xml.dist`:

-   `DB_CONNECTION=testing`
-   `DB_DATABASE=:memory:`
-   `QUEUE_CONNECTION=sync`
-   `CACHE_DRIVER=array`

## Workbench Integration

The `workbench/` directory contains a full Laravel application for testing:

-   Models in `workbench/app/Models/`
-   Migrations in `workbench/database/migrations/`
-   Seeders in `workbench/database/seeders/`

Workbench is built automatically via `testbench.yaml` configuration.
