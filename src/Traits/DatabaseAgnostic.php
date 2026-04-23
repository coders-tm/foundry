<?php

namespace Foundry\Traits;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Database-Agnostic Query Builder Trait
 *
 * Provides helper methods for building SQL queries that work across
 * different database drivers (SQLite, MySQL, PostgreSQL, SQL Server).
 *
 * @example
 * ```php
 * // String concatenation
 * $sql = $this->dbConcat(['users.first_name', '" "', 'users.last_name']);
 *
 * // Date difference in days
 * $sql = $this->dbDateDiff('end_date', 'start_date');
 *
 * // Group concatenation
 * $sql = $this->dbGroupConcat('user_id', ',', true);
 * ```
 */
trait DatabaseAgnostic
{
    /**
     * Get the current database driver name.
     *
     * @return string 'sqlite'|'mysql'|'pgsql'|'sqlsrv'
     */
    protected function getDriver(): string
    {
        return DB::connection()->getDriverName();
    }

    /**
     * Check if using SQLite.
     */
    protected function isSQLite(): bool
    {
        return $this->getDriver() === 'sqlite';
    }

    /**
     * Check if using MySQL/MariaDB.
     */
    protected function isMySQL(): bool
    {
        return in_array($this->getDriver(), ['mysql', 'mariadb']);
    }

    /**
     * Check if using PostgreSQL.
     */
    protected function isPostgreSQL(): bool
    {
        return $this->getDriver() === 'pgsql';
    }

    /**
     * Check if using SQL Server.
     */
    protected function isSQLServer(): bool
    {
        return $this->getDriver() === 'sqlsrv';
    }

    /**
     * Database-agnostic string concatenation.
     *
     * @param  array  $parts  Array of column names or string literals
     * @return string SQL expression for concatenation
     *
     * @example
     * ```php
     * // Concatenate first and last name with space
     * $sql = $this->dbConcat(['users.first_name', '" "', 'users.last_name']);
     *
     * // SQLite: users.first_name || " " || users.last_name
     * // MySQL: CONCAT(users.first_name, " ", users.last_name)
     * // PostgreSQL: users.first_name || ' ' || users.last_name
     * ```
     */
    protected function dbConcat(array $parts): string
    {
        return match ($this->getDriver()) {
            'sqlite', 'pgsql' => implode(' || ', $parts),
            'mysql', 'mariadb' => 'CONCAT('.implode(', ', $parts).')',
            'sqlsrv' => implode(' + ', $parts),
            default => 'CONCAT('.implode(', ', $parts).')',
        };
    }

    /**
     * Database-agnostic group concatenation.
     *
     * @param  string  $expression  Column or expression to concatenate
     * @param  string  $separator  Separator between values
     * @param  bool  $distinct  Whether to use DISTINCT (not supported in SQLite with separator)
     * @return string SQL expression for group concatenation
     *
     * @example
     * ```php
     * // Basic group concat
     * $sql = $this->dbGroupConcat('user_id', ',');
     *
     * // With DISTINCT (handled differently for SQLite)
     * $sql = $this->dbGroupConcat('user_id', ',', true);
     * // Note: For SQLite, use array_unique() in PHP after query
     * ```
     */
    protected function dbGroupConcat(string $expression, string $separator = ',', bool $distinct = false): string
    {
        return match ($this->getDriver()) {
            'sqlite' => $distinct
                ? "group_concat(DISTINCT {$expression}, '{$separator}')"
                : "group_concat({$expression}, '{$separator}')",
            'mysql', 'mariadb' => $distinct
                ? "GROUP_CONCAT(DISTINCT {$expression} SEPARATOR '{$separator}')"
                : "GROUP_CONCAT({$expression} SEPARATOR '{$separator}')",
            'pgsql' => $distinct
                ? "string_agg(DISTINCT {$expression}::text, '{$separator}')"
                : "string_agg({$expression}::text, '{$separator}')",
            'sqlsrv' => "STRING_AGG({$expression}, '{$separator}')",
            default => "GROUP_CONCAT({$expression} SEPARATOR '{$separator}')",
        };
    }

    /**
     * Database-agnostic date difference in days.
     *
     * @param  string  $endDate  End date column or expression
     * @param  string  $startDate  Start date column or expression
     * @return string SQL expression for date difference in days
     *
     * @example
     * ```php
     * // Calculate trial duration in days
     * $sql = $this->dbDateDiff('trial_ends_at', 'created_at');
     *
     * // SQLite: JULIANDAY(trial_ends_at) - JULIANDAY(created_at)
     * // MySQL: DATEDIFF(trial_ends_at, created_at)
     * // PostgreSQL: EXTRACT(DAY FROM (trial_ends_at - created_at))
     * ```
     */
    protected function dbDateDiff(string $endDate, string $startDate): string
    {
        return match ($this->getDriver()) {
            'sqlite' => "JULIANDAY({$endDate}) - JULIANDAY({$startDate})",
            'mysql', 'mariadb' => "DATEDIFF({$endDate}, {$startDate})",
            'pgsql' => "EXTRACT(DAY FROM ({$endDate} - {$startDate}))",
            'sqlsrv' => "DATEDIFF(day, {$startDate}, {$endDate})",
            default => "DATEDIFF({$endDate}, {$startDate})",
        };
    }

    /**
     * Database-agnostic date difference in months.
     *
     * @param  string  $endDate  End date column or expression
     * @param  string  $startDate  Start date column or expression
     * @return string SQL expression for date difference in months
     *
     * @example
     * ```php
     * // Calculate subscription age in months
     * $sql = $this->dbDateDiffMonths("'{$now}'", 'MIN(orders.created_at)');
     * ```
     */
    protected function dbDateDiffMonths(string $endDate, string $startDate): string
    {
        return match ($this->getDriver()) {
            'sqlite' => "(JULIANDAY({$endDate}) - JULIANDAY({$startDate})) / 30",
            'mysql', 'mariadb' => "TIMESTAMPDIFF(MONTH, {$startDate}, {$endDate})",
            'pgsql' => "EXTRACT(YEAR FROM AGE({$endDate}, {$startDate})) * 12 + EXTRACT(MONTH FROM AGE({$endDate}, {$startDate}))",
            'sqlsrv' => "DATEDIFF(month, {$startDate}, {$endDate})",
            default => "TIMESTAMPDIFF(MONTH, {$startDate}, {$endDate})",
        };
    }

    /**
     * Database-agnostic NOW() function.
     *
     * @return string SQL expression for current timestamp
     */
    protected function dbNow(): string
    {
        return match ($this->getDriver()) {
            'sqlite' => "datetime('now')",
            'mysql', 'mariadb' => 'NOW()',
            'pgsql' => 'NOW()',
            'sqlsrv' => 'GETDATE()',
            default => 'NOW()',
        };
    }

    /**
     * Database-agnostic date formatting.
     *
     * @param  string  $column  Column name or expression
     * @param  string  $format  Format string (using standard format tokens)
     * @return string SQL expression for formatted date
     *
     * @example
     * ```php
     * // Format as YYYY-MM
     * $sql = $this->dbDateFormat('created_at', 'Y-m');
     * ```
     */
    protected function dbDateFormat(string $column, string $format = 'Y-m-d'): string
    {
        $sqliteFormat = $this->convertToSQLiteDateFormat($format);
        $mysqlFormat = $this->convertToMySQLDateFormat($format);
        $pgsqlFormat = $this->convertToPostgreSQLDateFormat($format);

        return match ($this->getDriver()) {
            'sqlite' => "strftime('{$sqliteFormat}', {$column})",
            'mysql', 'mariadb' => "DATE_FORMAT({$column}, '{$mysqlFormat}')",
            'pgsql' => "TO_CHAR({$column}, '{$pgsqlFormat}')",
            'sqlsrv' => "FORMAT({$column}, '{$format}')",
            default => "DATE_FORMAT({$column}, '{$mysqlFormat}')",
        };
    }

    /**
     * Database-agnostic COALESCE function.
     *
     * @param  array  $expressions  Array of expressions to check
     * @return string SQL COALESCE expression
     */
    protected function dbCoalesce(array $expressions): string
    {
        return 'COALESCE('.implode(', ', $expressions).')';
    }

    /**
     * Database-agnostic IFNULL/NVL function.
     *
     * @param  string  $expression  Expression to check
     * @param  mixed  $defaultValue  Default value if null
     * @return string SQL expression
     */
    protected function dbIfNull(string $expression, $defaultValue): string
    {
        return match ($this->getDriver()) {
            'sqlite', 'mysql', 'mariadb' => "IFNULL({$expression}, {$defaultValue})",
            'pgsql' => "COALESCE({$expression}, {$defaultValue})",
            'sqlsrv' => "ISNULL({$expression}, {$defaultValue})",
            default => "IFNULL({$expression}, {$defaultValue})",
        };
    }

    /**
     * Database-agnostic CAST function.
     *
     * @param  string  $expression  Expression to cast
     * @param  string  $type  Target data type
     * @return string SQL CAST expression
     */
    protected function dbCast(string $expression, string $type): string
    {
        return "CAST({$expression} AS {$type})";
    }

    /**
     * Database-agnostic boolean value.
     *
     * @param  bool  $value  Boolean value
     * @return string SQL boolean representation
     */
    protected function dbBoolean(bool $value): string
    {
        return match ($this->getDriver()) {
            'sqlite' => $value ? '1' : '0',
            'mysql', 'mariadb' => $value ? 'TRUE' : 'FALSE',
            'pgsql' => $value ? 'TRUE' : 'FALSE',
            'sqlsrv' => $value ? '1' : '0',
            default => $value ? '1' : '0',
        };
    }

    /**
     * Database-agnostic DISTINCT within GROUP_CONCAT/string_agg.
     *
     * For SQLite, DISTINCT with separator is not supported in group_concat,
     * so you'll need to use array_unique() in PHP after fetching results.
     *
     * @param  string  $expression  Expression to concatenate
     * @param  string  $separator  Separator between values
     * @return array ['sql' => string, 'use_php_unique' => bool]
     */
    protected function dbGroupConcatDistinct(string $expression, string $separator = ','): array
    {
        $isSQLite = $this->isSQLite();

        $sql = match ($this->getDriver()) {
            'sqlite' => "group_concat({$expression}, '{$separator}')",
            'mysql', 'mariadb' => "GROUP_CONCAT(DISTINCT {$expression} SEPARATOR '{$separator}')",
            'pgsql' => "string_agg(DISTINCT {$expression}::text, '{$separator}')",
            'sqlsrv' => "STRING_AGG({$expression}, '{$separator}')",
            default => "GROUP_CONCAT(DISTINCT {$expression} SEPARATOR '{$separator}')",
        };

        return [
            'sql' => $sql,
            'use_php_unique' => $isSQLite, // Need to apply array_unique() in PHP for SQLite
        ];
    }

    /**
     * Convert PHP date format to SQLite strftime format.
     */
    private function convertToSQLiteDateFormat(string $format): string
    {
        $map = [
            'Y' => '%Y',
            'y' => '%y',
            'm' => '%m',
            'd' => '%d',
            'H' => '%H',
            'i' => '%M',
            's' => '%S',
            'M' => '%m',
            'D' => '%d',
        ];

        return strtr($format, $map);
    }

    /**
     * Convert PHP date format to MySQL DATE_FORMAT format.
     */
    private function convertToMySQLDateFormat(string $format): string
    {
        $map = [
            'Y' => '%Y',
            'y' => '%y',
            'm' => '%m',
            'd' => '%d',
            'H' => '%H',
            'i' => '%i',
            's' => '%s',
            'M' => '%b',
            'D' => '%d',
        ];

        return strtr($format, $map);
    }

    /**
     * Convert PHP date format to PostgreSQL TO_CHAR format.
     */
    private function convertToPostgreSQLDateFormat(string $format): string
    {
        $map = [
            'Y' => 'YYYY',
            'y' => 'YY',
            'm' => 'MM',
            'd' => 'DD',
            'H' => 'HH24',
            'i' => 'MI',
            's' => 'SS',
            'M' => 'Mon',
            'D' => 'DD',
        ];

        return strtr($format, $map);
    }

    /**
     * Build a CASE WHEN statement with database-agnostic syntax.
     *
     * @param  array  $conditions  Array of ['condition' => 'result', ...]
     * @param  mixed  $else  ELSE clause result
     * @return string SQL CASE expression
     *
     * @example
     * ```php
     * $sql = $this->dbCase([
     *     'status = "active"' => 1,
     *     'status = "canceled"' => 0,
     * ], 'NULL');
     * ```
     */
    protected function dbCase(array $conditions, $else = 'NULL'): string
    {
        $cases = [];
        foreach ($conditions as $condition => $result) {
            $cases[] = "WHEN {$condition} THEN {$result}";
        }

        return 'CASE '.implode(' ', $cases)." ELSE {$else} END";
    }

    /**
     * Database-agnostic date arithmetic (add months to a date).
     *
     * @param  string  $dateColumn  Column name or expression (e.g., 'periods.period_start')
     * @param  int  $months  Number of months to add (positive or negative)
     * @return string SQL expression for date arithmetic
     *
     * @example
     * ```php
     * // Add 1 month to period_start
     * $sql = $this->dbAddMonths('periods.period_start', 1);
     *
     * // SQLite: datetime(periods.period_start, '+1 month')
     * // MySQL: DATE_ADD(periods.period_start, INTERVAL 1 MONTH)
     * // PostgreSQL: periods.period_start + INTERVAL '1 month'
     * ```
     */
    protected function dbAddMonths(string $dateColumn, int $months): string
    {
        $months = (int) $months;
        $sign = $months >= 0 ? '+' : '';

        return match ($this->getDriver()) {
            'sqlite' => "datetime({$dateColumn}, '{$sign}{$months} months')",
            'mysql', 'mariadb' => "DATE_ADD({$dateColumn}, INTERVAL {$months} MONTH)",
            'pgsql' => "{$dateColumn} + INTERVAL '{$months} month'",
            'sqlsrv' => "DATEADD(MONTH, {$months}, {$dateColumn})",
            default => "DATE_ADD({$dateColumn}, INTERVAL {$months} MONTH)",
        };
    }

    /**
     * Build a UNION ALL query for period boundaries.
     *
     * Creates a derived table with period_start, period_end, and period_order columns.
     * Uses 3 bindings per period (not 4) to prevent binding corruption.
     *
     * @param  array  $periodBoundaries  Array of ['start' => datetime, 'end' => datetime, 'order' => int]
     * @return Builder|null Query builder with UNION ALL periods
     *
     * @example
     * ```php
     * $periodBoundaries = [
     *     ['start' => '2025-01-01 00:00:00', 'end' => '2025-01-31 23:59:59', 'order' => 0],
     *     ['start' => '2025-02-01 00:00:00', 'end' => '2025-02-28 23:59:59', 'order' => 1],
     * ];
     * $periodQuery = $this->buildPeriodBoundariesQuery($periodBoundaries);
     *
     * // Use in main query:
     * DB::table(DB::raw("({$periodQuery->toSql()}) as periods"))
     *     ->mergeBindings($periodQuery)
     *     ->leftJoin('table', ...)
     * ```
     */
    protected function buildPeriodBoundariesQuery(array $periodBoundaries): ?Builder
    {
        if (empty($periodBoundaries)) {
            return null;
        }

        $periodQuery = null;
        foreach ($periodBoundaries as $period) {
            // Use literal values instead of placeholders to avoid parameter binding issues
            // when the query is embedded as a subquery
            $startQuoted = DB::connection()->getPdo()->quote($period['start']);
            $endQuoted = DB::connection()->getPdo()->quote($period['end']);
            $orderQuoted = (int) $period['order'];

            $union = DB::table(DB::raw('(SELECT 1) as dummy'))
                ->select([
                    DB::raw("{$startQuoted} as period_start"),
                    DB::raw("{$endQuoted} as period_end"),
                    DB::raw("{$orderQuoted} as period_order"),
                ]);

            if ($periodQuery === null) {
                $periodQuery = $union;
            } else {
                $periodQuery->unionAll($union);
            }
        }

        return $periodQuery;
    }

    /**
     * Build an empty query that returns no results.
     *
     * Useful for returning a valid query builder when there are no periods to process.
     *
     *
     * @example
     * ```php
     * if (empty($periods)) {
     *     return $this->emptyQuery();
     * }
     * ```
     */
    protected function emptyQuery(): Builder
    {
        return DB::table(DB::raw('(SELECT 1) as dummy'))->whereRaw('1 = 0');
    }
}
