<?php

namespace Foundry\Tests\Feature\Reports;

use Carbon\Carbon;
use Foundry\Contracts\Metrics\MetricInterface;
use Foundry\Services\Metrics\AbstractMetric;
use Foundry\Services\Metrics\Instances\MrrMetric;
use Foundry\Services\Metrics\MetricsService;
use Foundry\Tests\TestCase;
use InvalidArgumentException;

class MetricsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-04-13 12:00:00'));
    }

    public function test_can_all_keys_returns_registered_metrics()
    {
        $keys = MetricsService::allKeys();

        $this->assertContains('mrr', $keys);
        $this->assertContains('net_revenue', $keys);
        $this->assertContains('active_users', $keys);
        $this->assertCount(21, $keys);
    }

    public function test_can_check_if_metric_exists()
    {
        $this->assertTrue(MetricsService::has('mrr'));
        $this->assertFalse(MetricsService::has('unknown_metric'));
    }

    public function test_can_resolve_metric_instance()
    {
        $service = new MetricsService;
        $metric = $service->resolve('mrr');

        $this->assertInstanceOf(MetricInterface::class, $metric);
        $this->assertInstanceOf(MrrMetric::class, $metric);
    }

    public function test_throws_exception_on_unknown_metric_resolution()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown metric type: unknown_metric');

        $service = new MetricsService;
        $service->resolve('unknown_metric');
    }

    public function test_can_register_and_unregister_custom_metric()
    {
        $customMetricClass = new class extends AbstractMetric
        {
            public function calculate(Carbon $start, Carbon $end): mixed
            {
                return 123;
            }

            protected function defaultLabel(): string
            {
                return 'Custom';
            }
        };

        MetricsService::register('custom_test', get_class($customMetricClass));
        $this->assertTrue(MetricsService::has('custom_test'));

        $service = new MetricsService;
        $result = $service->only(['custom_test']);
        $this->assertEquals(123, $result['custom_test']['raw_current']);

        MetricsService::unregister('custom_test');
        $this->assertFalse(MetricsService::has('custom_test'));
    }

    public function test_only_returns_subset_of_metrics()
    {
        $service = new MetricsService;
        $result = $service->only(['mrr', 'active_users']);

        $this->assertArrayHasKey('mrr', $result);
        $this->assertArrayHasKey('active_users', $result);
        $this->assertArrayNotHasKey('net_revenue', $result);
    }

    public function test_get_returns_all_metrics_by_default()
    {
        $service = new MetricsService;
        $result = $service->get();

        $this->assertCount(21, array_filter($result, fn ($k) => MetricsService::has($k), ARRAY_FILTER_USE_KEY));
    }

    public function test_metrics_include_comparison_data()
    {
        $service = new MetricsService(['compare' => true]);
        $result = $service->only(['mrr']);

        $this->assertArrayHasKey('mrr', $result);
        $this->assertArrayHasKey('current', $result['mrr']);
        $this->assertArrayHasKey('previous', $result['mrr']);
        $this->assertArrayHasKey('delta', $result['mrr']);
        $this->assertArrayHasKey('trend', $result['mrr']);
    }
}
