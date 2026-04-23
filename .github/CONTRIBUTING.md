# Contributing to Laravel Core

Thank you for your interest in contributing to `coderstm/foundry`! This document provides guidelines and instructions for contributing.

## Table of Contents

1. [Code of Conduct](#code-of-conduct)
2. [Getting Started](#getting-started)
3. [Development Setup](#development-setup)
4. [Making Changes](#making-changes)
5. [Testing](#testing)
6. [Submitting Changes](#submitting-changes)
7. [Code Standards](#code-standards)
8. [Commit Guidelines](#commit-guidelines)

---

## Code of Conduct

We are committed to providing a welcoming and inspiring community for all. Please read and adhere to our [Code of Conduct](CODE_OF_CONDUCT.md).

**Respectful Collaboration** — Treat all contributors with respect. We value diverse opinions and welcome constructive feedback.

**Inclusive Environment** — We embrace contributors of all backgrounds and skill levels.

**No Harassment** — Harassment, discrimination, or hostile behavior is not tolerated.

---

## Getting Started

### Prerequisites

- **PHP 8.2+** (required)
- **Composer** (for dependency management)
- **Git** (for version control)
- **Docker** (optional, for database testing)

### Fork & Clone

1. Fork the repository on GitHub
2. Clone your fork locally:
   ```bash
   git clone https://github.com/YOUR_USERNAME/foundry.git
   cd foundry
   ```
3. Add upstream remote:
   ```bash
   git remote add upstream https://github.com/coders-tm/foundry.git
   ```

---

## Development Setup

### 1. Install Dependencies

```bash
composer install
```

### 2. Build Workbench (Test Application)

The workbench is a local Laravel application used for development and testing:

```bash
vendor/bin/testbench workbench:build
```

### 3. Verify Setup

Run tests to ensure your environment is configured correctly:

```bash
composer test
```

Expected output: All tests should pass ✅

### 4. Start Development Server (Optional)

To manually test features in a browser:

```bash
composer run serve
```

Access the workbench app at `http://localhost:8000`

---

## Making Changes

### 1. Create a Feature Branch

Always create a new branch for your work:

```bash
git checkout -b feature/your-feature-name
# or
git checkout -b fix/issue-123-brief-description
```

Branch naming conventions:
- `feature/` — New features
- `fix/` — Bug fixes
- `refactor/` — Code refactoring
- `docs/` — Documentation updates
- `chore/` — Build, CI, dependencies

### 2. Understand the Project Structure

```
src/
├── Models/                 # Eloquent models (Order, Payment, Subscription, etc.)
├── Services/               # Business logic (PaymentService, SubscriptionService)
├── Http/Controllers/       # HTTP endpoints
├── Payment/                # Payment processors
├── Traits/                 # Reusable model behaviors
├── Events/                 # Event classes
├── Listeners/              # Event handlers
├── Jobs/                   # Queued jobs
├── Commands/               # Artisan commands
├── Contracts/              # Interfaces & abstractions
└── Providers/              # Service providers

resources/
├── views/                  # Blade templates
├── lang/                   # Translation files
├── boost/                  # AI agent documentation
└── migrations/             # Database migrations (symlinked)

tests/
├── Feature/                # Feature tests
└── Unit/                   # Unit tests

database/
├── migrations/             # Database migration files
├── factories/              # Model factories for testing
└── seeders/                # Database seeders

config/
├── foundry.php            # Package configuration
└── stripe.php              # Payment gateway configs
```

### 3. Write Code Following Standards

- **Strict Types**: Use `declare(strict_types=1);` in all PHP files
- **Type Hints**: Always add return types and parameter types
- **Namespaces**: Follow PSR-4 autoloading
- **Comments**: Document complex logic and public APIs
- **Formatting**: Use 4-space indentation

Example:

```php
<?php

declare(strict_types=1);

namespace Foundry\Services;

use Foundry\Models\Order;

class OrderService
{
    /**
     * Calculate total order amount including taxes and discounts
     */
    public function calculateTotal(Order $order): float
    {
        $subtotal = $order->getSubtotal();
        $taxes = $order->getTaxTotal();
        $discounts = $order->getDiscountTotal();
        
        return $subtotal + $taxes - $discounts;
    }
}
```

### 4. Add Tests for Your Changes

Write tests before (TDD) or after (after-the-fact) your implementation.

**Unit Tests** (fast, isolated):

```php
// tests/Unit/Services/OrderServiceTest.php
class OrderServiceTest extends TestCase
{
    public function test_calculates_order_total_correctly(): void
    {
        $order = Order::factory()->create(['subtotal' => 100]);
        $order->addTaxLine(10); // $10 tax
        $order->addDiscountLine(5); // $5 discount
        
        $service = new OrderService();
        $this->assertEquals(105, $service->calculateTotal($order));
    }
}
```

**Feature Tests** (integration tests):

```php
// tests/Feature/Orders/OrderCreationTest.php
class OrderCreationTest extends TestCase
{
    public function test_can_create_order_via_api(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->post('/api/orders', [
                'items' => [['product_id' => 1, 'quantity' => 2]],
            ]);
        
        $response->assertCreated();
        $this->assertDatabaseHas('orders', ['user_id' => $user->id]);
    }
}
```

### 5. Update Documentation

If your changes affect user-facing features:

1. Update relevant `.md` files in `resources/boost/skills/foundry/rules/`
2. Add comments to complex code sections
3. Update CHANGELOG.md (see [Commit Guidelines](#commit-guidelines))
4. Update API docs if adding/modifying public methods

---

## Testing

### Run All Tests

```bash
composer test
```

### Run Specific Test File

```bash
vendor/bin/phpunit tests/Feature/OrderTest.php
```

### Run Tests Matching a Pattern

```bash
vendor/bin/phpunit --filter "test_calculates_total"
```

### Code Coverage

```bash
composer test -- --coverage
```

Aim for **>80% code coverage** for new features.

### Static Analysis

Check code with PHPStan:

```bash
vendor/bin/phpstan analyse
```

### Formatting & Linting

Check code style:

```bash
composer lint
```

Auto-fix formatting issues:

```bash
composer format
```

### Before Submitting

Always run this before committing:

```bash
composer lint
composer test
```

---

## Submitting Changes

### 1. Commit Your Work

Follow [Commit Guidelines](#commit-guidelines) below.

```bash
git add .
git commit -m "feat(orders): add support for order refunds"
```

### 2. Keep Your Branch Updated

Before pushing, sync with the latest upstream:

```bash
git fetch upstream
git rebase upstream/main
```

Resolve any merge conflicts if they occur.

### 3. Push to Your Fork

```bash
git push origin feature/your-feature-name
```

### 4. Create a Pull Request

Go to GitHub and create a PR with:

- **Title**: Brief description of changes (follow conventional commits)
- **Description**: 
  - What problem does this solve?
  - How does it solve it?
  - Any breaking changes?
  - Related issue(s): `Closes #123`
  
Example PR description:

```markdown
## Description
Adds support for partial refunds on orders.

## Problem
Currently, users can only fully refund orders. Some businesses need the flexibility to issue partial refunds.

## Solution
- Add `Refund::createPartial()` method
- Update `Order::refund()` to accept optional amount parameter
- Add validation to prevent refunding more than order total

## Breaking Changes
None

## Related Issues
Closes #456
```

### 5. Respond to Review Feedback

Maintainers may request changes. Please:
- Address all feedback
- Commit new changes with clear messages
- Re-request review once updated

---

## Code Standards

### PHP Standards

We follow **PSR-12** extended coding style and **PSR-4** autoloading:

```php
<?php

declare(strict_types=1);

namespace Foundry\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'user_id',
        'total',
        'status',
    ];

    /**
     * Get the line items for this order
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(Order\LineItem::class);
    }

    /**
     * Calculate order total with taxes and discounts
     */
    public function getGrandTotal(): float
    {
        $subtotal = $this->lineItems()->sum('total');
        $taxes = $this->getTaxTotal();
        $discounts = $this->getDiscountTotal();
        
        return $subtotal + $taxes - $discounts;
    }
}
```

### Naming Conventions

| Item | Convention | Example |
|------|-----------|---------|
| Classes | PascalCase | `OrderService`, `PaymentProcessor` |
| Methods | camelCase | `calculateTotal()`, `processPayment()` |
| Constants | UPPER_SNAKE_CASE | `MAX_RETRY_ATTEMPTS`, `DEFAULT_CURRENCY` |
| Properties | camelCase | `$processedAt`, `$totalAmount` |
| Database columns | snake_case | `user_id`, `created_at` |
| Database tables | snake_case, plural | `orders`, `payment_methods` |

### Documentation Comments

Use PHPDoc for public APIs:

```php
/**
 * Process a payment for the given order
 *
 * @param Order $order The order to process payment for
 * @param string $paymentMethod The payment method identifier
 * @return PaymentResult The result of the payment attempt
 * 
 * @throws InvalidPaymentMethodException If payment method is not supported
 * @throws InsufficientFundsException If customer has insufficient balance
 */
public function processPayment(Order $order, string $paymentMethod): PaymentResult
{
    // ...
}
```

### Error Handling

Use custom exceptions for domain errors:

```php
// ❌ Wrong
if ($order->status === 'canceled') {
    return response('Cannot refund canceled orders', 400);
}

// ✅ Correct
if ($order->isCanceled()) {
    throw new CannotRefundCanceledOrderException($order);
}
```

### Database Queries

Use Eloquent query builder, avoid raw SQL when possible:

```php
// ✅ Preferred
$orders = Order::where('user_id', $userId)
    ->where('status', 'completed')
    ->get();

// ⚠️ Only use raw queries when necessary
$orders = Order::whereRaw('YEAR(created_at) = ?', [2024])->get();
```

---

## Commit Guidelines

We use **Conventional Commits** for clear, semantic commit messages.

### Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Type

- `feat` — A new feature
- `fix` — A bug fix
- `refactor` — Code refactoring without feature changes
- `perf` — Performance improvements
- `test` — Adding or updating tests
- `docs` — Documentation changes
- `chore` — Build, CI, dependencies
- `style` — Code style changes (formatting)

### Scope

Optional, but helps identify affected area:

- `orders` — Order domain
- `payments` — Payment processing
- `subscriptions` — Subscription billing
- `auth` — Authentication
- `api` — API changes
- `db` — Database/migrations

### Subject

- Imperative mood ("add" not "added")
- No period at the end
- Max 50 characters
- Lowercase

### Body

- Explain **what** and **why**, not how
- Wrap at 72 characters
- Reference related issues: `Fixes #123`
- Separate from subject with blank line

### Footer

Include breaking changes or references:

```
BREAKING CHANGE: `Order::refund()` now requires amount parameter
Refs #789
```

### Examples

```
feat(payments): add support for Stripe refunds

Add automatic refund processing through Stripe API.
Includes webhook handlers for refund status updates.

Closes #345

---

fix(orders): prevent duplicate order creation

Race condition in order creation endpoint allowed
duplicate orders when request was retried quickly.

Use database constraint to enforce uniqueness.

Refs #678

---

docs(readme): add payment processor examples

Add examples for each supported payment processor
in the README for easier onboarding.

---

chore(deps): update Laravel to 12.0

Update to latest Laravel version for security
patches and performance improvements.
```

### Commit Best Practices

- **Atomic commits** — One logical change per commit
- **Testable commits** — Each commit should be testable independently
- **Clear history** — Future developers should understand changes easily
- **No fixup commits** — Squash or amend before pushing

```bash
# Amend last commit
git commit --amend

# Interactive rebase to squash commits
git rebase -i upstream/main
```

---

## Areas for Contribution

### High Priority

- 🐛 Bug fixes (especially security-related)
- 📚 Documentation improvements
- 🧪 Additional test coverage
- ⚡ Performance optimizations

### Welcome Contributions

- New payment processors
- Additional language translations
- Reporting/analytics features
- Admin dashboard components
- Webhook improvements

### Before Starting Large Features

Please open an issue to discuss first! This helps ensure:

- Feature aligns with project goals
- No duplicate effort
- Design feedback from maintainers
- Requirements clarity

---

## Pull Request Checklist

Before submitting, verify:

- ✅ Tests pass: `composer test`
- ✅ Code style: `composer lint`
- ✅ No console errors or warnings
- ✅ Documentation updated (if needed)
- ✅ Commits follow conventional format
- ✅ Branch is rebased on latest upstream
- ✅ No merge conflicts
- ✅ PR description is clear and complete
- ✅ Related issues are referenced
- ✅ No sensitive data in commits (keys, credentials)

---

## Getting Help

### Questions or Discussions

- Open a GitHub Discussion
- Ask in issues (tag with `question` label)
- Join our community [Slack/Discord] (if applicable)

### Development Help

- Review `.github/copilot-instructions.md` for architecture context
- Check `resources/boost/skills/foundry/SKILL.md` for domain patterns
- Explore existing tests for usage examples

### Reporting Issues

- Search existing issues first
- Include PHP version, Laravel version, Laravel Core version
- Provide minimal reproduction case
- Include full error stack trace

---

## Recognition

Contributors are recognized in:

- CHANGELOG.md
- GitHub release notes
- Contributors list in README

Thank you for contributing! 🎉
