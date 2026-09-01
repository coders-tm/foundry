<?php

uses(\Foundry\Tests\TestCase::class);

beforeEach(function () {
    \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-04-13 12:00:00'));
});

it('can all keys returns registered metrics', function () {
    $keys = \Foundry\Services\Metrics\MetricsService::allKeys();
    
    $this->assertContains('mrr', $keys);
    $this->assertContains('net_revenue', $keys);
    $this->assertContains('active_users', $keys);
    $this->assertCount(21, $keys);
});

it('can check if metric exists', function () {
    $this->assertTrue(\Foundry\Services\Metrics\MetricsService::has('mrr'));
    $this->assertFalse(\Foundry\Services\Metrics\MetricsService::has('unknown_metric'));
});

it('can resolve metric instance', function () {
    $service = new \Foundry\Services\Metrics\MetricsService;
    $metric = $service->resolve('mrr');
    
    $this->assertInstanceOf(\Foundry\Contracts\Metrics\MetricInterface::class, $metric);
    $this->assertInstanceOf(\Foundry\Services\Metrics\Instances\MrrMetric::class, $metric);
});

it('throws exception on unknown metric resolution', function () {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown metric type: unknown_metric');
    
    $service = new \Foundry\Services\Metrics\MetricsService;
    $service->resolve('unknown_metric');
});

it('can register and unregister custom metric', function () {
    $customMetricClass = new class extends \Foundry\Services\Metrics\AbstractMetric
    {
    public function calculate(\Carbon\Carbon $start, \Carbon\Carbon $end): mixed
    {
    return 123;
    }
    
    protected function defaultLabel(): string
    {
    return 'Custom';
    }
    };
    
    \Foundry\Services\Metrics\MetricsService::register('custom_test', get_class($customMetricClass));
    $this->assertTrue(\Foundry\Services\Metrics\MetricsService::has('custom_test'));
    
    $service = new \Foundry\Services\Metrics\MetricsService;
    $result = $service->only(['custom_test']);
    $this->assertEquals(123, $result['custom_test']['raw_current']);
    
    \Foundry\Services\Metrics\MetricsService::unregister('custom_test');
    $this->assertFalse(\Foundry\Services\Metrics\MetricsService::has('custom_test'));
});

it('only returns subset of metrics', function () {
    $service = new \Foundry\Services\Metrics\MetricsService;
    $result = $service->only(['mrr', 'active_users']);
    
    $this->assertArrayHasKey('mrr', $result);
    $this->assertArrayHasKey('active_users', $result);
    $this->assertArrayNotHasKey('net_revenue', $result);
});

it('get returns all metrics by default', function () {
    $service = new \Foundry\Services\Metrics\MetricsService;
    $result = $service->get();
    
    $this->assertCount(21, array_filter($result, fn ($k) => \Foundry\Services\Metrics\MetricsService::has($k), ARRAY_FILTER_USE_KEY));
});

it('metrics include comparison data', function () {
    $service = new \Foundry\Services\Metrics\MetricsService(['compare' => true]);
    $result = $service->only(['mrr']);
    
    $this->assertArrayHasKey('mrr', $result);
    $this->assertArrayHasKey('current', $result['mrr']);
    $this->assertArrayHasKey('previous', $result['mrr']);
    $this->assertArrayHasKey('delta', $result['mrr']);
    $this->assertArrayHasKey('trend', $result['mrr']);
});
