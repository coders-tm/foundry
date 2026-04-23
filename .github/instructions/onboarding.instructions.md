---
applyTo: **/*.md
---

# Onboarding and Contribution Instructions

## Quick Start for New Contributors

### Prerequisites

-   PHP 8.2 or higher
-   Composer installed
-   Basic understanding of Laravel
-   Familiarity with package development (helpful but not required)

### Initial Setup (5 minutes)

```bash
# 1. Clone the repository
git clone https://github.com/coders-tm/foundry.git
cd foundry

# 2. Install dependencies
composer install

# 3. Build the workbench testing environment
vendor/bin/testbench workbench:build

# 4. Verify setup by running tests
composer test

# 5. (Optional) Start local development server
composer run serve
# Access at http://localhost:8000
```

### Understanding the Project Structure

```
foundry/
├── src/                    # Package source code
│   ├── Models/            # Domain models (organized by subdomain)
│   ├── Services/          # Business logic and integrations
│   ├── Http/
│   │   ├── Controllers/   # HTTP request handlers (thin!)
│   │   └── Resources/     # API response transformers
│   ├── Events/            # Event classes
│   ├── Listeners/         # Event handlers
│   ├── Commands/          # Artisan console commands
│   └── Providers/         # Service providers
├── tests/                 # Test suite
│   ├── Feature/          # Integration tests
│   └── Unit/             # Unit tests
├── database/
│   ├── migrations/       # Database schema migrations
│   └── factories/        # Model factories for testing
├── workbench/            # Full Laravel app for local testing
│   ├── app/             # Test application code
│   └── database/        # Test database and seeders
├── config/               # Package configuration
└── .github/
    ├── copilot-instructions.md    # Main Copilot guidance
    └── instructions/              # Context-specific instructions
```

## Common Contribution Workflows

### Workflow 1: Fixing a Bug

1. **Reproduce the bug with a test**

    ```bash
    # Create or modify test in tests/Feature/ or tests/Unit/
    # Run the test to verify it fails
    vendor/bin/phpunit tests/Feature/YourTest.php
    ```

2. **Fix the bug**

    - Locate the issue (usually in `src/Services/` or `src/Models/`)
    - Make the minimal change needed
    - Follow existing code patterns

3. **Verify the fix**

    ```bash
    # Run the specific test
    vendor/bin/phpunit tests/Feature/YourTest.php

    # Run all related tests
    composer test

    # Check code quality
    composer lint
    ```

4. **Submit PR**
    - Create a branch: `git checkout -b fix/description-of-bug`
    - Commit changes: `git commit -m "Fix: description of bug"`
    - Push and create PR

### Workflow 2: Adding a New Feature

1. **Plan the feature**

    - Review existing patterns in similar features
    - Check `.github/instructions/` for relevant guidance
    - Outline the components needed:
        - Model (if needed)
        - Migration (if needed)
        - Service class (for business logic)
        - Controller (for HTTP handling)
        - Resource (for API responses)
        - Tests (required!)

2. **Write tests first (TDD)**

    ```php
    // tests/Feature/YourFeatureTest.php
    public function test_user_can_perform_new_action()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'user')
            ->postJson('/api/user/your-endpoint', [
                'field' => 'value',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'field']]);
    }
    ```

3. **Implement incrementally**

    - Add migration (if database changes needed)
    - Create model (if new entity)
    - Add factory for testing
    - Implement service class (business logic)
    - Add controller method (HTTP handling)
    - Create API resource (response formatting)
    - Test after each step!

4. **Verify and refine**

    ```bash
    # Run your tests
    vendor/bin/phpunit tests/Feature/YourFeatureTest.php

    # Run all tests
    composer test

    # Check static analysis
    composer lint

    # Manual testing
    composer run serve
    # Test with Postman, curl, or browser
    ```

### Workflow 3: Modifying Database Schema

1. **Create migration**

    ```bash
    # Migrations are in database/migrations/
    # Follow naming: YYYY_MM_DD_HHMMSS_descriptive_name.php
    ```

2. **Write migration code**

    ```php
    public function up(): void
    {
        Schema::table('table_name', function (Blueprint $table) {
            $table->string('new_column')->nullable()->after('existing_column');
            $table->index('new_column');
        });
    }

    public function down(): void
    {
        Schema::table('table_name', function (Blueprint $table) {
            $table->dropColumn('new_column');
        });
    }
    ```

3. **Update model**

    ```php
    // Add to $fillable array
    protected $fillable = [
        'existing_field',
        'new_column',
    ];

    // Add to $casts if needed
    protected $casts = [
        'new_column' => 'string',
    ];
    ```

4. **Update factory**

    ```php
    public function definition(): array
    {
        return [
            'existing_field' => $this->faker->word,
            'new_column' => $this->faker->sentence,
        ];
    }
    ```

5. **Test migration**

    ```bash
    # Rebuild workbench (includes running migrations)
    vendor/bin/testbench workbench:build

    # Or run migrations manually in workbench
    php artisan migrate
    ```

## Understanding Package Patterns

### Service-Oriented Architecture

Business logic lives in service classes, not controllers:

```php
// ✅ Correct: Controller delegates to service
class SubscriptionController extends Controller
{
    public function __construct(protected SubscriptionService $service) {}

    public function cancel(Subscription $subscription)
    {
        $this->authorize('cancel', $subscription);

        $this->service->cancel($subscription);

        return response()->json(['message' => 'Subscription canceled']);
    }
}

// Service contains the business logic
class SubscriptionService
{
    public function cancel(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $subscription->update([
                'canceled_at' => now(),
                'status' => 'canceled',
            ]);

            event(new SubscriptionCanceled($subscription));

            // Notify user, update metrics, etc.
        });
    }
}
```

### Event-Driven Architecture

Use events for cross-cutting concerns:

```php
// When something happens, fire an event
event(new SubscriptionCreated($subscription));

// Multiple listeners can respond
class SendSubscriptionNotification {
    public function handle(SubscriptionCreated $event): void {
        $event->subscription->user->notify(
            new SubscriptionCreatedNotification($event->subscription)
        );
    }
}

class CreateInitialInvoice {
    public function handle(SubscriptionCreated $event): void {
        // Create invoice logic
    }
}
```

### Multi-Guard Authentication

The package has **separate User and Admin models** with different authentication guards:

**Model Architecture:**

-   `Foundry\Models\Admin` - Base admin model
    -   Extends Laravel's `Authenticatable`
    -   Uses `admins` guard
    -   Core authentication and permission features
-   `Foundry\Models\User` - User model
    -   Extends `Admin` (inherits all admin functionality)
    -   Uses `users` guard
    -   Adds subscription/billing capabilities via `Billable` trait

**Guard Detection:**

```php
// Check current guard
if (guard('users')) {
    // User-specific logic (customer/subscriber features)
}

if (guard('admins')) {
    // Admin-specific logic (management features)
}

// Or use helper shortcuts
if (is_user()) {
    // User logic
}

if (is_admin()) {
    // Admin logic
}

// Get current guard name
$guardName = guard(); // Returns 'users', 'admins', or null
```

**Route Organization:**

```php
// Routes are prefixed by guard
// /api/user/* - User endpoints (customers, subscriptions)
// /api/* - Admin endpoints (management, reports)
```

### Model Configuration System

Models can be swapped at runtime for flexibility:

```php
// In service provider boot method
Foundry::useUserModel(CustomUser::class);
Foundry::useAdminModel(CustomAdmin::class);
Foundry::useOrderModel(CustomOrder::class);

// In code, always reference via Foundry static properties
$userModel = Foundry::$userModel;
$user = new $userModel();

// In relationships, use Foundry static properties
public function user()
{
    return $this->belongsTo(Foundry::$userModel);
}

public function orders()
{
    return $this->hasMany(Foundry::$orderModel);
}
```

**Important**: User extends Admin, so:

-   Users have all admin capabilities plus subscription features
-   Don't confuse the inheritance with permissions - guards control access
-   Always use the appropriate guard when authenticating

## Testing Best Practices

### Test Structure

```php
public function test_descriptive_name_of_what_is_tested()
{
    // Arrange - Set up test data
    $user = User::factory()->create();
    $plan = Plan::factory()->create();

    // Act - Perform the action
    $subscription = $user->subscribe($plan);

    // Assert - Verify the outcome
    $this->assertTrue($subscription->active());
    $this->assertEquals($plan->id, $subscription->plan_id);
}
```

### Use Factories

```php
// ✅ Good - Use factories
$user = User::factory()->create();
$subscription = Subscription::factory()->active()->create();

// ❌ Bad - Manual creation
$user = new User();
$user->name = 'Test User';
$user->email = 'test@example.com';
$user->save();
```

### Test Both Success and Failure

```php
public function test_user_can_subscribe_to_plan()
{
    // Test happy path
    $user = User::factory()->create();
    $plan = Plan::factory()->create();

    $subscription = $user->subscribe($plan);

    $this->assertInstanceOf(Subscription::class, $subscription);
}

public function test_user_cannot_subscribe_to_inactive_plan()
{
    // Test error case
    $user = User::factory()->create();
    $plan = Plan::factory()->inactive()->create();

    $this->expectException(PlanInactiveException::class);

    $user->subscribe($plan);
}
```

## Getting Unstuck

### Common Issues and Solutions

**Issue**: Tests fail after pulling latest changes

```bash
# Solution: Rebuild workbench
vendor/bin/testbench workbench:build
composer test
```

**Issue**: "Class not found" errors

```bash
# Solution: Regenerate autoload files
composer dump-autoload
```

**Issue**: Migration already exists error

```bash
# Solution: Workbench handles migrations automatically
vendor/bin/testbench workbench:build
```

**Issue**: Stripe API errors in tests

```bash
# Solution: Add Stripe test key to phpunit.xml
cp phpunit.xml.dist phpunit.xml
# Add: <env name="STRIPE_SECRET" value="sk_test_..."/>
```

**Issue**: PHPStan errors

```bash
# Solution: Check specific errors
vendor/bin/phpstan analyse --verbose
# Review phpstan.neon.dist for rules
```

### Where to Look for Help

1. **Similar code**: Find similar features and follow their patterns
2. **Tests**: Look at existing tests to understand usage
3. **Documentation**: https://foundry.netlify.com
4. **Issues**: Search GitHub issues for similar problems
5. **Context instructions**: Check `.github/instructions/` for area-specific guidance

## Next Steps

Once you're comfortable with the basics:

1. **Explore advanced features**:

    - Payment gateway integrations (`src/Services/Gateways/`)
    - Theme system (`src/Services/Theme.php`)
    - Permission system (`src/Providers/FoundryPermissionsServiceProvider.php`)

2. **Review context-specific instructions**:

    - [Service classes](.github/instructions/services.instructions.md)
    - [Controllers](.github/instructions/controllers.instructions.md)
    - [Models](.github/instructions/models.instructions.md)
    - [Testing](.github/instructions/tests.instructions.md)

3. **Contribute back**:
    - Fix bugs you find
    - Improve documentation
    - Add missing tests
    - Share your knowledge

Welcome to the foundry community! 🚀
