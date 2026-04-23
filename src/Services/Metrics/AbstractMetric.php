<?php

namespace Foundry\Services\Metrics;

use Carbon\Carbon;
use Foundry\Contracts\Metrics\MetricInterface;

abstract class AbstractMetric implements MetricInterface
{
    use HandlesSubscriptionMetrics;

    /**
     * Get the human-readable label for the metric.
     */
    public function label(array $current, array $previous): string
    {
        return $this->getComparisonDescription($current, $previous, $this->defaultLabel());
    }

    /**
     * Get the value type (currency, percentage, count).
     */
    public function type(): string
    {
        return 'number';
    }

    /**
     * Get additional metadata for the metric (e.g., breakdown by plan).
     */
    public function extra(Carbon $start, Carbon $end): array
    {
        return [];
    }

    /**
     * Get the default label for the metric.
     */
    abstract protected function defaultLabel(): string;
}
