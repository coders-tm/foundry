---
applyTo: database/migrations/**/*.php
---

# Database Migrations Instructions

## 🚨 CRITICAL RULE: Two-Migration Strategy

**ALWAYS create TWO migrations when modifying existing schema:**

1. **Update original migration** in `database/migrations/` ← For fresh installs
2. **Create update migration** in `workbench/database/migrations/` ← For upgrades

**Example:**

```bash
# 1. Edit: database/migrations/2022_07_24_092101_create_plans_table.php
#    Add: $table->boolean('allow_freeze')->default(false);

# 2. Create: workbench/database/migrations/2025_12_08_000001_add_freeze_to_plans_table.php
#    Add: if (!Schema::hasColumn('plans', 'allow_freeze')) { ... }
```

**Why?** Original migrations = fresh schema. Workbench migrations = upgrade path.

---

## Purpose

Migrations in `database/migrations/` define the package's database schema. They should be:

-   **Reversible**: Always include `down()` method for rollback
-   **Idempotent**: Safe to run multiple times
-   **Forward-compatible**: Don't break existing data
-   **Well-documented**: Complex changes should have comments

## Migration Patterns

### ⚠️ CRITICAL: Two-Migration Strategy

**When modifying original migrations in `database/migrations/`, you MUST create an update migration in `workbench/database/migrations/`.**

This ensures:

1. **Fresh installs** get the correct schema from original migrations
2. **Existing installations** can upgrade via workbench migrations
3. **No data loss** during upgrades

#### Example Workflow:

**Step 1: Update Original Migration**

```php
// database/migrations/2022_07_24_092101_create_plans_table.php
Schema::create('plans', function (Blueprint $table) {
    // ... existing fields ...

    // NEW: Add freeze support
    $table->boolean('allow_freeze')->default(false);
    $table->decimal('freeze_fee', 8, 2)->nullable();
});
```

**Step 2: Create Workbench Update Migration**

```php
// workbench/database/migrations/2025_12_08_000001_add_freeze_support_to_plans_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Safe for upgrades - check if columns don't exist
        Schema::table('plans', function (Blueprint $table) {
            if (!Schema::hasColumn('plans', 'allow_freeze')) {
                $table->boolean('allow_freeze')->default(false)->after('is_popular');
            }
            if (!Schema::hasColumn('plans', 'freeze_fee')) {
                $table->decimal('freeze_fee', 8, 2)->nullable()->after('allow_freeze');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (Schema::hasColumn('plans', 'allow_freeze')) {
                $table->dropColumn('allow_freeze');
            }
            if (Schema::hasColumn('plans', 'freeze_fee')) {
                $table->dropColumn('freeze_fee');
            }
        });
    }
};
```

**Step 3: Removing Fields Requires Update Migration**

```php
// workbench/database/migrations/2025_12_08_000002_remove_unused_freeze_fields.php
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Remove columns that shouldn't be stored
            if (Schema::hasColumn('subscriptions', 'freeze_reason')) {
                $table->dropColumn('freeze_reason');
            }
            if (Schema::hasColumn('subscriptions', 'freeze_fee')) {
                $table->dropColumn('freeze_fee');
            }
            if (Schema::hasColumn('subscriptions', 'freeze_days_used')) {
                $table->dropColumn('freeze_days_used');
            }
        });

        Schema::table('plans', function (Blueprint $table) {
            if (Schema::hasColumn('plans', 'max_freeze_days_per_year')) {
                $table->dropColumn('max_freeze_days_per_year');
            }
        });
    }

    public function down(): void
    {
        // Re-add removed columns for rollback
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'freeze_reason')) {
                $table->text('freeze_reason')->nullable();
            }
            if (!Schema::hasColumn('subscriptions', 'freeze_fee')) {
                $table->decimal('freeze_fee', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('subscriptions', 'freeze_days_used')) {
                $table->integer('freeze_days_used')->default(0);
            }
        });

        Schema::table('plans', function (Blueprint $table) {
            if (!Schema::hasColumn('plans', 'max_freeze_days_per_year')) {
                $table->integer('max_freeze_days_per_year')->nullable();
            }
        });
    }
};
```

#### When to Use This Pattern:

-   ✅ Adding new columns to existing tables
-   ✅ Removing columns from existing tables
-   ✅ Changing column types or constraints
-   ✅ Adding/removing indexes
-   ✅ Renaming columns
-   ❌ Creating entirely new tables (use original migrations only)

#### Why Use `Schema::hasColumn()`?

```php
// GOOD - Safe for both fresh installs and upgrades
if (!Schema::hasColumn('plans', 'allow_freeze')) {
    $table->boolean('allow_freeze')->default(false);
}

// BAD - Will fail on fresh installs (column already exists from original migration)
$table->boolean('allow_freeze')->default(false);
```

### Standard Table Creation

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('status')->default('active');
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
```

### Adding Columns to Existing Table

```php
public function up(): void
{
    Schema::table('subscriptions', function (Blueprint $table) {
        $table->foreignId('next_plan')->nullable()->after('plan_id');
        $table->boolean('is_downgrade')->default(false)->after('is_active');
    });
}

public function down(): void
{
    Schema::table('subscriptions', function (Blueprint $table) {
        $table->dropColumn(['next_plan', 'is_downgrade']);
    });
}
```

## Best Practices

### 1. Column Types and Constraints

```php
// Use appropriate column types
$table->string('email')->unique();
$table->text('description')->nullable();
$table->decimal('price', 10, 2); // total digits, decimal places
$table->json('metadata')->nullable();
$table->enum('status', ['active', 'inactive', 'canceled']);

// Add constraints
$table->string('code')->unique();
$table->decimal('amount', 10, 2)->unsigned();
$table->integer('quantity')->default(1);
```

### 2. Foreign Keys

```php
// Standard foreign key
$table->foreignId('user_id')
    ->constrained()
    ->cascadeOnDelete();

// Custom table reference
$table->foreignId('owner_id')
    ->constrained('users')
    ->cascadeOnDelete();

// Nullable foreign key
$table->foreignId('parent_id')
    ->nullable()
    ->constrained('categories')
    ->nullOnDelete();

// No action on delete
$table->foreignId('plan_id')
    ->constrained()
    ->restrictOnDelete();
```

### 3. Indexes

```php
// Single column index
$table->index('email');
$table->unique('username');

// Composite index
$table->index(['user_id', 'status']);

// Named index
$table->index('expires_at', 'idx_subscriptions_expires');

// Full-text index (MySQL)
$table->fullText(['title', 'content']);
```

### 4. Timestamps and Soft Deletes

```php
// Standard timestamps (created_at, updated_at)
$table->timestamps();

// Soft deletes (deleted_at)
$table->softDeletes();

// Custom timestamp columns
$table->timestamp('starts_at')->nullable();
$table->timestamp('expires_at')->nullable();
$table->timestamp('canceled_at')->nullable();
```

### 5. Default Values

```php
// String defaults
$table->string('status')->default('pending');

// Numeric defaults
$table->integer('quantity')->default(0);
$table->decimal('price', 10, 2)->default(0.00);

// Boolean defaults
$table->boolean('is_active')->default(true);

// Timestamp defaults
$table->timestamp('created_at')->useCurrent();
$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
```

### 6. Nullable Columns

```php
// Make columns nullable when appropriate
$table->string('description')->nullable();
$table->foreignId('parent_id')->nullable();
$table->timestamp('canceled_at')->nullable();

// JSON should typically be nullable
$table->json('metadata')->nullable();
```

## Subscription-Specific Patterns

### Subscription Tables

```php
Schema::create('subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('plan_id')->constrained();

    // Status fields
    $table->string('status')->default('active');
    $table->boolean('is_active')->default(true);
    $table->boolean('is_downgrade')->default(false);

    // Plan management
    $table->foreignId('next_plan')->nullable();

    // Timestamps
    $table->timestamp('starts_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->timestamp('canceled_at')->nullable();
    $table->timestamp('trial_ends_at')->nullable();

    // Gateway integration
    $table->string('gateway')->nullable();
    $table->string('gateway_id')->nullable();
    $table->string('gateway_status')->nullable();

    $table->timestamps();
    $table->softDeletes();

    // Indexes for common queries
    $table->index(['user_id', 'status']);
    $table->index('expires_at');
    $table->index('is_active');
});
```

### Plan Tables

```php
Schema::create('plans', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description')->nullable();

    // Pricing
    $table->decimal('price', 10, 2);
    $table->string('currency', 3)->default('USD');
    $table->string('interval')->default('month'); // day, week, month, year
    $table->integer('interval_count')->default(1);

    // Trial and intro pricing
    $table->boolean('has_trial')->default(false);
    $table->integer('trial_period')->nullable(); // in days
    $table->boolean('has_intro_pricing')->default(false);
    $table->decimal('intro_price', 10, 2)->nullable();
    $table->integer('intro_period')->nullable(); // in days

    // Status
    $table->boolean('is_active')->default(true);
    $table->boolean('is_popular')->default(false);

    $table->timestamps();
    $table->softDeletes();

    $table->index('is_active');
});
```

### Invoice Tables

```php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
    $table->string('invoice_number')->unique();

    // Amounts
    $table->decimal('subtotal', 10, 2);
    $table->decimal('tax', 10, 2)->default(0);
    $table->decimal('discount', 10, 2)->default(0);
    $table->decimal('total', 10, 2);

    // Status
    $table->string('status')->default('pending'); // pending, paid, failed, refunded

    // Dates
    $table->timestamp('due_at')->nullable();
    $table->timestamp('paid_at')->nullable();

    // Gateway
    $table->string('gateway')->nullable();
    $table->string('gateway_id')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->index('invoice_number');
    $table->index(['subscription_id', 'status']);
});
```

## Data Migrations

### Modifying Existing Data

```php
public function up(): void
{
    // Add new column
    Schema::table('subscriptions', function (Blueprint $table) {
        $table->timestamp('expires_at')->nullable()->after('ends_at');
    });

    // Migrate data from old column to new
    DB::table('subscriptions')
        ->whereNotNull('ends_at')
        ->update([
            'expires_at' => DB::raw('ends_at'),
        ]);

    // Optionally drop old column
    // Schema::table('subscriptions', function (Blueprint $table) {
    //     $table->dropColumn('ends_at');
    // });
}

public function down(): void
{
    // Reverse the migration
    // Schema::table('subscriptions', function (Blueprint $table) {
    //     $table->timestamp('ends_at')->nullable()->after('expires_at');
    // });

    // DB::table('subscriptions')
    //     ->whereNotNull('expires_at')
    //     ->update([
    //         'ends_at' => DB::raw('expires_at'),
    //     ]);

    Schema::table('subscriptions', function (Blueprint $table) {
        $table->dropColumn('expires_at');
    });
}
```

### Batch Updates for Large Tables

```php
public function up(): void
{
    // Process in chunks to avoid memory issues
    DB::table('subscriptions')
        ->whereNull('gateway')
        ->orderBy('id')
        ->chunk(1000, function ($subscriptions) {
            foreach ($subscriptions as $subscription) {
                DB::table('subscriptions')
                    ->where('id', $subscription->id)
                    ->update(['gateway' => 'stripe']);
            }
        });
}
```

## Migration Testing

### Testbench Migrations

Migrations are automatically run during tests via `testbench.yaml`:

```yaml
migrations:
    - workbench/database/migrations
```

### Test Migration Rollback

```php
public function test_migration_rollback_works()
{
    // Migration should have run
    $this->assertTrue(Schema::hasTable('subscriptions'));

    // Rollback
    $this->artisan('migrate:rollback');

    // Table should be gone
    $this->assertFalse(Schema::hasTable('subscriptions'));
}
```

## Common Pitfalls

### ❌ Don't: Modify Existing Migrations

Once a migration has been run in production, never modify it. Create a new migration instead.

### ❌ Don't: Forget Down Method

Always implement `down()` method for rollback support.

### ❌ Don't: Use Model Classes

Migrations should use raw queries or schema builder, not Eloquent models (models may change over time).

### ✅ Do: Use Raw Queries for Data

```php
// Good - uses raw SQL
DB::table('subscriptions')->update(['status' => 'active']);

// Bad - uses model (might break if model changes)
Subscription::query()->update(['status' => 'active']);
```

### ✅ Do: Add Indexes for Query Performance

```php
// Index columns used in WHERE clauses
$table->index('status');
$table->index(['user_id', 'status']);

// Index foreign keys
$table->index('user_id');
$table->index('plan_id');
```

## Migration Naming Convention

Follow Laravel conventions:

-   `create_{table}_table.php` - Create new table
-   `add_{column}_to_{table}_table.php` - Add columns
-   `update_{table}_{description}_table.php` - Modify existing
-   `drop_{column}_from_{table}_table.php` - Remove columns

### Original Migrations (`database/migrations/`)

Use standard Laravel naming:

-   `2022_07_24_092101_create_plans_table.php`
-   `2022_07_24_092102_create_subscriptions_table.php`
-   `2023_05_06_190730_update_subscriptions_table.php`

### Workbench Update Migrations (`workbench/database/migrations/`)

**IMPORTANT:** Use dates that align with package version releases:

-   `2025_12_08_000001_add_freeze_support_to_plans_table.php` - Version 2.x feature
-   `2025_12_08_000002_remove_unused_freeze_fields.php` - Version 2.x cleanup
-   `2026_01_15_000001_add_contract_tracking_to_subscriptions.php` - Version 3.x feature

**Naming Pattern:**

```
{YEAR}_{MONTH}_{DAY}_{SEQUENCE}_{description}.php
```

Where:

-   Date = Expected release date or PR merge date
-   Sequence = 000001, 000002, etc. (multiple migrations same day)
-   Description = Clear, descriptive name of the change

Examples:

```php
// Adding new feature fields
2025_12_08_000001_add_freeze_support_to_plans_table.php

// Cleanup/removal migrations
2025_12_08_000002_remove_unused_freeze_fields.php

// Major feature migrations
2025_10_16_000000_add_contract_tracking_to_subscriptions_table.php
2025_10_16_010000_add_billing_interval_to_plans_table.php

// Enhancement migrations
2025_12_01_000001_add_wallet_support_to_refunds_table.php
2025_12_01_000002_add_paid_and_refund_totals_to_orders_table.php
```

### Migration Checklist

Before creating an update migration, verify:

-   [ ] Original migration in `database/migrations/` is updated
-   [ ] Update migration in `workbench/database/migrations/` is created
-   [ ] Update migration uses `Schema::hasColumn()` checks
-   [ ] Both `up()` and `down()` methods are implemented
-   [ ] Migration date aligns with version/release
-   [ ] Migration is added to RELEASE_NOTES.md
-   [ ] Tests verify migration works on fresh installs
-   [ ] Tests verify migration works on existing databases
