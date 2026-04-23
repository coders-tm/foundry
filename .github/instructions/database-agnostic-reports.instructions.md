# Database-Agnostic Report Development Guide

## Overview

All reports in `src/Services/Reports/` must support multiple database drivers:

-   **SQLite** (for testing)
-   **MySQL/MariaDB** (for production)
-   **PostgreSQL** (for production)
-   **SQL Server** (for enterprise)

The `DatabaseAgnostic` trait provides helper methods to write cross-database compatible SQL.

---

## Using the DatabaseAgnostic Trait

All reports extend `AbstractReport`, which uses the `DatabaseAgnostic` trait. You have access to all trait methods automatically.

### Quick Reference

```php
namespace Foundry\Services\Reports\YourCategory;

use Foundry\Services\Reports\AbstractReport;
use Illuminate\Support\Facades\DB;

class YourReport extends AbstractReport
{
    public function query(array $filters)
    {
        // ✅ Use trait methods for database-agnostic SQL
        $dateDiff = $this->dbDateDiff('end_date', 'start_date');
        $concat = $this->dbConcat(['first_name', '" "', 'last_name']);
        $groupConcat = $this->dbGroupConcat('user_id', ',');

        return DB::table('subscriptions')
            ->selectRaw("
                id,
                {$dateDiff} as duration_days,
                {$concat} as full_name
            ");
    }
}
```

---

## Common Patterns

### 1. String Concatenation

**Problem:** SQLite uses `||`, MySQL uses `CONCAT()`, PostgreSQL uses `||`

**Solution:** Use `dbConcat()`

```php
// ❌ DON'T - MySQL-specific
$sql = "CONCAT(first_name, ' ', last_name)";

// ✅ DO - Database-agnostic
$sql = $this->dbConcat(['first_name', '" "', 'last_name']);
// SQLite: first_name || " " || last_name
// MySQL: CONCAT(first_name, " ", last_name)
// PostgreSQL: first_name || ' ' || last_name
```

### 2. Date Difference in Days

**Problem:** Different functions across databases

**Solution:** Use `dbDateDiff()`

```php
// ❌ DON'T - MySQL-specific
$sql = "DATEDIFF(trial_ends_at, created_at)";

// ✅ DO - Database-agnostic
$sql = $this->dbDateDiff('trial_ends_at', 'created_at');
// SQLite: JULIANDAY(trial_ends_at) - JULIANDAY(created_at)
// MySQL: DATEDIFF(trial_ends_at, created_at)
// PostgreSQL: EXTRACT(DAY FROM (trial_ends_at - created_at))
```

### 3. Date Difference in Months

**Problem:** Month calculations vary by database

**Solution:** Use `dbDateDiffMonths()`

```php
// ❌ DON'T - MySQL-specific
$sql = "TIMESTAMPDIFF(MONTH, start_date, end_date)";

// ✅ DO - Database-agnostic
$sql = $this->dbDateDiffMonths("'{$now}'", 'MIN(orders.created_at)');
// SQLite: (JULIANDAY(now) - JULIANDAY(MIN(orders.created_at))) / 30
// MySQL: TIMESTAMPDIFF(MONTH, MIN(orders.created_at), now)
// PostgreSQL: EXTRACT(YEAR FROM AGE(...)) * 12 + EXTRACT(MONTH FROM AGE(...))
```

### 4. Group Concatenation

**Problem:** `GROUP_CONCAT` syntax differs, SQLite doesn't support `DISTINCT` with separator

**Solution:** Use `dbGroupConcat()` or `dbGroupConcatDistinct()`

```php
// ❌ DON'T - MySQL-specific
$sql = "GROUP_CONCAT(DISTINCT user_id SEPARATOR ',')";

// ✅ DO - Database-agnostic (basic)
$sql = $this->dbGroupConcat('user_id', ',', false);
// SQLite: group_concat(user_id, ',')
// MySQL: GROUP_CONCAT(user_id SEPARATOR ',')
// PostgreSQL: string_agg(user_id::text, ',')

// ✅ DO - Database-agnostic (with DISTINCT handling)
$result = $this->dbGroupConcatDistinct('user_id', ',');
$sql = $result['sql'];
$usePhpUnique = $result['use_php_unique']; // true for SQLite

// If SQLite, apply array_unique() after fetching:
if ($usePhpUnique && $row->user_ids) {
    $userIds = array_unique(explode(',', $row->user_ids));
}
```

### 5. Current Timestamp

**Problem:** `NOW()` not available in SQLite

**Solution:** Use `dbNow()` or parameterized dates

```php
// ❌ DON'T - Not available in SQLite
$sql = "WHERE created_at <= NOW()";

// ✅ DO - Use parameterized date (PREFERRED)
$now = now()->toDateTimeString();
$sql = "WHERE created_at <= ?";
// Then pass $now as parameter

// ✅ DO - Use trait method
$sql = "WHERE created_at <= {$this->dbNow()}";
// SQLite: datetime('now')
// MySQL: NOW()
```

### 6. Date Formatting

**Problem:** Different format functions across databases

**Solution:** Use `dbDateFormat()`

```php
// ❌ DON'T - MySQL-specific
$sql = "DATE_FORMAT(created_at, '%Y-%m')";

// ✅ DO - Database-agnostic
$sql = $this->dbDateFormat('created_at', 'Y-m');
// SQLite: strftime('%Y-%m', created_at)
// MySQL: DATE_FORMAT(created_at, '%Y-%m')
// PostgreSQL: TO_CHAR(created_at, 'YYYY-MM')
```

---

## Complete Trait Method Reference

### Driver Detection Methods

```php
$this->getDriver()      // Returns: 'sqlite', 'mysql', 'pgsql', 'sqlsrv'
$this->isSQLite()       // Returns: bool
$this->isMySQL()        // Returns: bool
$this->isPostgreSQL()   // Returns: bool
$this->isSQLServer()    // Returns: bool
```

### SQL Generation Methods

| Method                                  | Purpose                    | Example                                 |
| --------------------------------------- | -------------------------- | --------------------------------------- |
| `dbConcat(array $parts)`                | String concatenation       | `dbConcat(['a', 'b'])`                  |
| `dbGroupConcat($expr, $sep, $distinct)` | Group concatenation        | `dbGroupConcat('id', ',')`              |
| `dbGroupConcatDistinct($expr, $sep)`    | Group concat with DISTINCT | `dbGroupConcatDistinct('id', ',')`      |
| `dbDateDiff($end, $start)`              | Date diff in days          | `dbDateDiff('end', 'start')`            |
| `dbDateDiffMonths($end, $start)`        | Date diff in months        | `dbDateDiffMonths('end', 'start')`      |
| `dbNow()`                               | Current timestamp          | `dbNow()`                               |
| `dbDateFormat($col, $format)`           | Format date                | `dbDateFormat('date', 'Y-m')`           |
| `dbCoalesce(array $exprs)`              | COALESCE function          | `dbCoalesce(['a', 'b', '0'])`           |
| `dbIfNull($expr, $default)`             | IFNULL/NVL                 | `dbIfNull('amount', '0')`               |
| `dbCast($expr, $type)`                  | CAST function              | `dbCast('id', 'TEXT')`                  |
| `dbBoolean(bool $value)`                | Boolean literal            | `dbBoolean(true)`                       |
| `dbCase(array $conditions, $else)`      | CASE statement             | `dbCase(['status = "active"' => 1], 0)` |

---

## Best Practices

### 1. Always Test Against SQLite

```bash
# Run tests with SQLite (default)
vendor/bin/phpunit

# Reports must work in both SQLite and MySQL
```

### 2. Use Parameterized Dates

```php
// ✅ PREFERRED - Works everywhere
$now = now()->toDateTimeString();
$query->whereRaw('created_at <= ?', [$now]);

// ⚠️ AVOID - Database-specific
$query->whereRaw('created_at <= NOW()');
```

### 3. Handle DISTINCT in GROUP_CONCAT

```php
// For SQLite compatibility, handle DISTINCT in PHP
$result = $this->dbGroupConcatDistinct('subscription.id', '|');

$query->selectRaw("
    {$result['sql']} as subscription_ids
");

// In stream() or toRow()
if ($result['use_php_unique'] && $row->subscription_ids) {
    $ids = array_unique(explode('|', $row->subscription_ids));
} else {
    $ids = explode('|', $row->subscription_ids ?? '');
}
```

### 4. Complex Expressions

For complex database-specific logic, use conditional building:

```php
if ($this->isSQLite()) {
    $expression = "strftime('%Y-%m', created_at)";
} elseif ($this->isMySQL()) {
    $expression = "DATE_FORMAT(created_at, '%Y-%m')";
} elseif ($this->isPostgreSQL()) {
    $expression = "TO_CHAR(created_at, 'YYYY-MM')";
}

// Or use the trait method
$expression = $this->dbDateFormat('created_at', 'Y-m');
```

### 5. Documentation

Document database-specific handling in your report:

```php
/**
 * Build the base query.
 *
 * Database Compatibility:
 * - Uses dbDateDiff() for cross-database date calculations
 * - Uses dbGroupConcatDistinct() with PHP unique for SQLite
 *
 * {@inheritdoc}
 */
public function query(array $filters)
{
    // ...
}
```

---

## Migration Guide

### Converting Existing Reports

1. **Find database-specific code:**

    ```bash
    grep -r "DB::getDriverName\|DATEDIFF\|CONCAT\|GROUP_CONCAT\|NOW()" src/Services/Reports/
    ```

2. **Replace with trait methods:**

    ```php
    // Before
    $driver = DB::connection()->getDriverName();
    $sql = match ($driver) {
        'sqlite' => 'JULIANDAY(end) - JULIANDAY(start)',
        'mysql' => 'DATEDIFF(end, start)',
        default => 'DATEDIFF(end, start)',
    };

    // After
    $sql = $this->dbDateDiff('end', 'start');
    ```

3. **Test thoroughly:**

    ```bash
    # Test with SQLite
    vendor/bin/phpunit tests/Feature/Reports/YourReportTest.php

    # Test with MySQL (if available)
    DB_CONNECTION=mysql vendor/bin/phpunit tests/Feature/Reports/YourReportTest.php
    ```

---

## Examples

### Example 1: Trial Conversion Report

```php
public function query(array $filters)
{
    // Database-agnostic date diff
    $daysExpression = $this->dbDateDiff(
        'subscriptions.trial_ends_at',
        'subscriptions.created_at'
    );

    return DB::table('subscriptions')
        ->selectRaw("
            COUNT(*) as total_trials,
            AVG({$daysExpression}) as avg_trial_days
        ");
}
```

### Example 2: MRR Movement Report

```php
public function query(array $filters)
{
    // Database-agnostic group concatenation
    $result = $this->dbGroupConcatDistinct(
        $this->isSQLite()
            ? "(id || ':' || plan_id)"
            : 'CONCAT(id, ":", plan_id)',
        '|'
    );

    $query = DB::table('subscriptions')
        ->selectRaw("{$result['sql']} as subscription_data");

    // Store for later use in stream()
    $this->usePhpUnique = $result['use_php_unique'];

    return $query;
}

public function stream(array $filters, callable $consume): void
{
    foreach ($this->query($filters)->cursor() as $row) {
        // Handle SQLite DISTINCT in PHP
        if ($this->usePhpUnique && $row->subscription_data) {
            $data = array_unique(explode('|', $row->subscription_data));
        } else {
            $data = explode('|', $row->subscription_data ?? '');
        }

        // Process $data...
        $consume($this->toRow($row));
    }
}
```

### Example 3: CLV Report

```php
public function query(array $filters)
{
    $now = now()->toDateTimeString();

    // Database-agnostic months diff
    $monthsExpression = $this->dbDateDiffMonths(
        "'{$now}'",
        'MIN(orders.created_at)'
    );

    return DB::table('users')
        ->join('orders', 'orders.user_id', '=', 'users.id')
        ->selectRaw("
            users.id,
            {$monthsExpression} as months_active,
            SUM(orders.total) as total_revenue
        ")
        ->groupBy('users.id');
}
```

---

## Troubleshooting

### "No such function: DATEDIFF"

❌ Using MySQL-specific function in SQLite
✅ Replace with `$this->dbDateDiff()`

### "No such function: NOW"

❌ Using `NOW()` in SQLite
✅ Use `$now = now()->toDateTimeString()` and parameterized queries

### "No such function: CONCAT"

❌ Using `CONCAT()` in SQLite
✅ Replace with `$this->dbConcat()`

### GROUP_CONCAT with DISTINCT not working in SQLite

❌ `group_concat(DISTINCT expr, separator)` not supported
✅ Use `dbGroupConcatDistinct()` and handle duplicates in PHP

### Date format differences

❌ Using `DATE_FORMAT()` in SQLite or `strftime()` in MySQL
✅ Use `$this->dbDateFormat('column', 'Y-m-d')`

---

## Testing Checklist

When creating or modifying a report:

-   [ ] Uses `DatabaseAgnostic` trait methods (inherited from `AbstractReport`)
-   [ ] No hardcoded `NOW()`, `DATEDIFF()`, `CONCAT()`, etc.
-   [ ] Parameterized dates instead of SQL functions where possible
-   [ ] Tests pass with SQLite: `vendor/bin/phpunit`
-   [ ] Documented any database-specific handling
-   [ ] No `DB::getDriverName()` checks (use trait methods instead)
-   [ ] GROUP_CONCAT DISTINCT handled correctly for SQLite

---

## See Also

-   **Trait Source:** `src/Traits/DatabaseAgnostic.php`
-   **Base Class:** `src/Services/Reports/AbstractReport.php`
-   **Interface:** `src/Services/Reports/ReportInterface.php`
-   **Example Reports:** `src/Services/Reports/Revenue/MrrMovementReport.php`
