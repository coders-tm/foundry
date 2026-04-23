<?php

namespace Foundry\Services\Metrics;

use Foundry\Contracts\Metrics\MetricInterface;
use InvalidArgumentException;

class MetricsService extends MetricsCalculator
{
    /**
     * Map of metric keys to their implementation classes.
     *
     * @var array<string, class-string<MetricInterface>>
     */
    protected static array $map = [
        'mrr' => Instances\MrrMetric::class,
        'net_revenue' => Instances\NetRevenueMetric::class,
        'arpu' => Instances\ArpuMetric::class,
        'ltv' => Instances\LtvMetric::class,
        'churn' => Instances\ChurnMetric::class,
        'revenue_churn' => Instances\RevenueChurnMetric::class,
        'active_users' => Instances\ActiveUsersMetric::class,
        'new_customers' => Instances\NewCustomersMetric::class,
        'new_subscriptions' => Instances\NewSubscriptionsMetric::class,
        'order_count' => Instances\OrderCountMetric::class,

        // Dashboard specific metrics
        'active_subscriptions' => Instances\ActiveSubscriptionsMetric::class,
        'trial_subscriptions' => Instances\TrialSubscriptionsMetric::class,
        'grace_period_subscriptions' => Instances\GracePeriodSubscriptionsMetric::class,
        'cancelled_subscriptions' => Instances\CancelledSubscriptionsMetric::class,
        'trial_conversion_rate' => Instances\TrialConversionRateMetric::class,
        'arr' => Instances\ArrMetric::class,
        'aov' => Instances\AovMetric::class,
        'growth_rate' => Instances\GrowthRateMetric::class,
        'segments' => Instances\CustomerSegmentsMetric::class,
        'total_users' => Instances\TotalUsersMetric::class,
        'total_revenue' => Instances\TotalRevenueMetric::class,
    ];

    protected string $cachePrefix = 'kpi_metrics';

    /**
     * Register a custom metric implementation.
     *
     * @param  string  $key  The metric unique identifier
     * @param  class-string<MetricInterface>  $class  The metric implementation class
     */
    public static function register(string $key, string $class): void
    {
        static::$map[$key] = $class;
    }

    /**
     * Unregister a metric implementation.
     */
    public static function unregister(string $key): void
    {
        unset(static::$map[$key]);
    }

    /**
     * Resolve and instantiate the metric class for a given key.
     *
     * @throws InvalidArgumentException If the metric key is not supported
     */
    public function resolve(string $key): MetricInterface
    {
        if (! static::has($key)) {
            throw new InvalidArgumentException("Unknown metric type: {$key}");
        }

        $class = static::$map[$key];

        return new $class;
    }

    /**
     * Check if a metric type is supported.
     */
    public static function has(string $key): bool
    {
        return isset(static::$map[$key]);
    }

    /**
     * Get all supported metric keys.
     *
     * @return array<int, string>
     */
    public static function allKeys(): array
    {
        return array_keys(static::$map);
    }

    /**
     * Get all metrics or a subset based on the 'keys' parameter.
     */
    public function get(array $keys = []): array
    {
        $keys = ! empty($keys) ? $keys : static::allKeys();
        $payload = [];
        $comparisons = [];

        foreach ($keys as $key) {
            if (! static::has($key)) {
                continue;
            }

            $metric = $this->resolve($key);

            $comparisons[$key] = [
                'calculator' => fn ($start, $end) => $this->remember("{$key}:calculation:{$start->timestamp}-{$end->timestamp}", fn () => $metric->calculate($start, $end)),
                'type' => $metric->type(),
                'description' => fn ($periods) => $metric->label($periods['current'], $periods['previous']),
                'additional' => fn ($periods) => $metric->extra($periods['current']['start'], $periods['current']['end']),
            ];

            // We only need placeholders for the initial payload, withComparisons will fill them.
            $payload[$key] = null;
        }

        // Ensure comparison is enabled for the payload formatting
        $this->filters['compare'] = true;

        $result = $this->withComparisons($payload, $comparisons);

        if (isset($result['comparisons'])) {
            $comparisons = $result['comparisons'];
            unset($result['comparisons']);

            return array_merge($result, $comparisons);
        }

        return $result;
    }

    /**
     * Proxy to get subset of metrics.
     */
    public function only(array $keys): array
    {
        return $this->get($keys);
    }
}
