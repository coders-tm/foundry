<?php

namespace Foundry\Services\Reports;

use Foundry\Services\Reports\Acquisition\NewSignupsReport;
use Foundry\Services\Reports\Acquisition\TrialConversionReport;
use Foundry\Services\Reports\Economics\ArpuReport;
use Foundry\Services\Reports\Economics\ClvReport;
use Foundry\Services\Reports\Exports\OrdersExportReport;
use Foundry\Services\Reports\Exports\PaymentsExportReport;
use Foundry\Services\Reports\Exports\SubscriptionsExportReport;
use Foundry\Services\Reports\Exports\UsersExportReport;
use Foundry\Services\Reports\Orders\PaymentPerformanceReport;
use Foundry\Services\Reports\Orders\SalesSummaryReport;
use Foundry\Services\Reports\Orders\TaxSummaryReport;
use Foundry\Services\Reports\Retention\CustomerChurnReport;
use Foundry\Services\Reports\Retention\MrrChurnReport;
use Foundry\Services\Reports\Revenue\MrrByPlanReport;
use Foundry\Services\Reports\Revenue\MrrMovementReport;
use InvalidArgumentException;

/**
 * Maps report types to their corresponding service classes.
 *
 * This class centralizes the mapping logic and provides methods
 * to resolve, validate, and query available report types.
 *
 * Each report type maps to a single-responsibility report class
 * that extends AbstractReport for consistent behavior and
 * memory-efficient cursor-based streaming.
 */
class ReportService
{
    /**
     * Map of report types to their service classes.
     *
     * Each report type maps to ONE class (single-responsibility pattern).
     *
     * @var array<string, class-string<ReportInterface>>
     */
    protected static array $map = [
        // Revenue
        'mrr-by-plan' => MrrByPlanReport::class,
        'mrr-movement' => MrrMovementReport::class,

        // Retention & Churn
        'customer-churn' => CustomerChurnReport::class,
        'mrr-churn' => MrrChurnReport::class,

        // Economics & Unit Metrics
        'arpu' => ArpuReport::class,
        'clv' => ClvReport::class,

        // Acquisition & Conversion
        'trial-conversion' => TrialConversionReport::class,
        'new-signups' => NewSignupsReport::class,

        // Order & Sales
        'sales-summary' => SalesSummaryReport::class,
        'payment-performance' => PaymentPerformanceReport::class,
        'tax-summary' => TaxSummaryReport::class,

        // Data Exports (raw data)
        'users' => UsersExportReport::class,
        'subscriptions' => SubscriptionsExportReport::class,
        'orders' => OrdersExportReport::class,
        'payments' => PaymentsExportReport::class,
    ];

    /**
     * Grouping of report types by category.
     *
     * @var array<string, array<int, string>>
     */
    protected static array $grouped = [
        'revenue' => [
            'mrr-by-plan',
            'mrr-movement',
        ],
        'retention' => [
            'customer-churn',
            'mrr-churn',
        ],
        'economics' => [
            'arpu',
            'clv',
        ],
        'acquisition' => [
            'trial-conversion',
            'new-signups',
        ],
        'orders' => [
            'sales-summary',
            'payment-performance',
            'tax-summary',
        ],
        'exports' => [
            'users',
            'subscriptions',
            'orders',
            'payments',
        ],
    ];

    /**
     * Map of report types to their human-readable labels.
     *
     * @var array<string, string>
     */
    protected static array $labels = [
        // Revenue
        'mrr-by-plan' => 'MRR by Plan',
        'mrr-movement' => 'MRR Movement',

        // Retention & Churn
        'customer-churn' => 'Customer Churn Rate',
        'mrr-churn' => 'MRR Churn Rate',

        // Economics & Unit Metrics
        'arpu' => 'Average Revenue Per User (ARPU)',
        'clv' => 'Customer Lifetime Value (CLV)',

        // Acquisition & Conversion
        'trial-conversion' => 'Trial Conversion Rate',
        'new-signups' => 'New Signups',

        // Order & Sales
        'sales-summary' => 'Sales Summary',
        'payment-performance' => 'Payment Performance',
        'tax-summary' => 'Tax Summary',

        // Data Exports
        'users' => 'Users Export',
        'subscriptions' => 'Subscriptions Export',
        'orders' => 'Orders Export',
        'payments' => 'Payments Export',
    ];

    /**
     * Map of report categories to their human-readable labels.
     *
     * @var array<string, string>
     */
    protected static array $categoryLabels = [
        'revenue' => 'Revenue',
        'retention' => 'Retention & Churn',
        'economics' => 'Economics & Unit Metrics',
        'acquisition' => 'Acquisition & Conversion',
        'orders' => 'Order & Sales',
        'exports' => 'Data Exports',
    ];

    /**
     * Resolve and instantiate the service for a given report type.
     *
     * @param  string  $type  The report type
     *
     * @throws InvalidArgumentException If the report type is not supported
     */
    public static function resolve(string $type): ReportInterface
    {
        if (! static::has($type)) {
            throw new InvalidArgumentException("Unknown report type: {$type}");
        }

        $serviceClass = static::$map[$type];

        return new $serviceClass;
    }

    /**
     * Get the service class for a given report type.
     *
     * @param  string  $type  The report type
     * @return class-string<ReportInterface>|null
     */
    public static function getServiceClass(string $type): ?string
    {
        return static::$map[$type] ?? null;
    }

    /**
     * Check if a report type is supported.
     *
     * @param  string  $type  The report type
     */
    public static function has(string $type): bool
    {
        return isset(static::$map[$type]);
    }

    /**
     * Get all supported report types.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_keys(static::$map);
    }

    /**
     * Get all report types grouped by category.
     *
     * @return array<string, array<int, string>>
     */
    public static function grouped(): array
    {
        return static::$grouped;
    }

    /**
     * Get the category for a given report type.
     *
     * @param  string  $type  The report type
     */
    public static function getCategory(string $type): ?string
    {
        foreach (static::grouped() as $category => $types) {
            if (in_array($type, $types)) {
                return $category;
            }
        }

        return null;
    }

    /**
     * Get all report types for a specific category.
     *
     * @param  string  $category  The category name
     * @return array<int, string>
     */
    public static function forCategory(string $category): array
    {
        return static::grouped()[$category] ?? [];
    }

    /**
     * Register a custom report type mapping.
     *
     * Useful for modules to add their own custom reports.
     *
     * @param  string  $type  The report type
     * @param  class-string<ReportInterface>  $serviceClass  The service class
     * @param  string|null  $label  The human-readable label
     * @param  string|null  $category  The category key
     */
    public static function register(string $type, string $serviceClass, ?string $label = null, ?string $category = null): void
    {
        static::$map[$type] = $serviceClass;

        if ($label) {
            static::$labels[$type] = $label;
        }

        if ($category) {
            if (! isset(static::$grouped[$category])) {
                static::$grouped[$category] = [];
            }
            if (! in_array($type, static::$grouped[$category])) {
                static::$grouped[$category][] = $type;
            }
        }
    }

    /**
     * Register a new report category.
     *
     * @param  string  $key  The category key
     * @param  string  $label  The human-readable label
     */
    public static function registerCategory(string $key, string $label): void
    {
        static::$categoryLabels[$key] = $label;
        if (! isset(static::$grouped[$key])) {
            static::$grouped[$key] = [];
        }
    }

    /**
     * Unregister a report type mapping.
     *
     * @param  string  $type  The report type
     */
    public static function unregister(string $type): void
    {
        unset(static::$map[$type]);
        unset(static::$labels[$type]);

        foreach (static::$grouped as $category => $types) {
            if (($key = array_search($type, $types)) !== false) {
                unset(static::$grouped[$category][$key]);
                // Re-index array
                static::$grouped[$category] = array_values(static::$grouped[$category]);
            }
        }
    }

    /**
     * Get human-readable label for a report type.
     *
     * @param  string  $type  The report type
     */
    public static function getLabel(string $type): string
    {
        return static::$labels[$type] ?? ucwords(str_replace(['-', '_'], ' ', $type));
    }

    /**
     * Get all report types with their labels.
     *
     * @return array<string, string>
     */
    public static function allWithLabels(): array
    {
        $types = static::all();
        $result = [];

        foreach ($types as $type) {
            $result[$type] = static::getLabel($type);
        }

        return $result;
    }

    /**
     * Get human-readable labels for report categories.
     *
     * @return array<string, string>
     */
    public static function getCategoryLabels(): array
    {
        return static::$categoryLabels;
    }
}
