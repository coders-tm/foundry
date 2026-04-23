<?php

namespace Foundry\Contracts\Metrics;

use Carbon\Carbon;

interface MetricInterface
{
    /**
     * Calculate the metric for the given date range.
     */
    public function calculate(Carbon $start, Carbon $end): mixed;

    /**
     * Get the human-readable label for the metric.
     */
    public function label(array $current, array $previous): string;

    /**
     * Get the value type (currency, percentage, count).
     */
    public function type(): string;

    /**
     * Get additional metadata for the metric (e.g., breakdown by plan).
     */
    public function extra(Carbon $start, Carbon $end): array;
}
