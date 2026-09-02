<?php

use Carbon\Carbon;
use Foundry\Contracts\Metrics\MetricInterface;
use Foundry\Services\Metrics\AbstractMetric;
use Foundry\Services\Metrics\Instances\MrrMetric;
use Foundry\Services\Metrics\MetricsService;
use Foundry\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-04-13 12:00:00'));
});

it('can all keys returns registered metrics', function () {
    $keys = MetricsService::allKeys();

    expect($keys)->toContain('mrr');
    expect($keys)->toContain('net_revenue');
    expect($keys)->toContain('active_users');
    expect($keys)->toHaveCount(21);
});

it('can check if metric exists', function () {
    expect(MetricsService::has('mrr'))->toBeTrue();
    expect(MetricsService::has('unknown_metric'))->toBeFalse();
});

it('can resolve metric instance', function () {
    $service = new MetricsService;
    $metric = $service->resolve('mrr');

    expect($metric)->toBeInstanceOf(MetricInterface::class);
    expect($metric)->toBeInstanceOf(MrrMetric::class);
});

it('throws exception on unknown metric resolution', function () {
    expect(fn () => (new MetricsService)->resolve('unknown_metric'))->toThrow(InvalidArgumentException::class, 'Unknown metric type: unknown_metric');
});

it('can register and unregister custom metric', function () {
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
    expect(MetricsService::has('custom_test'))->toBeTrue();

    $service = new MetricsService;
    $result = $service->only(['custom_test']);
    expect($result['custom_test']['raw_current'])->toEqual(123);

    MetricsService::unregister('custom_test');
    expect(MetricsService::has('custom_test'))->toBeFalse();
});

it('only returns subset of metrics', function () {
    $service = new MetricsService;
    $result = $service->only(['mrr', 'active_users']);

    expect($result)->toHaveKey('mrr');
    expect($result)->toHaveKey('active_users');
    expect($result)->not->toHaveKey('net_revenue');
});

it('get returns all metrics by default', function () {
    $service = new MetricsService;
    $result = $service->get();

    expect(array_filter($result, fn ($k) => MetricsService::has($k), ARRAY_FILTER_USE_KEY))->toHaveCount(21);
});

it('metrics include comparison data', function () {
    $service = new MetricsService(['compare' => true]);
    $result = $service->only(['mrr']);

    expect($result)->toHaveKey('mrr');
    expect($result['mrr'])->toHaveKey('current');
    expect($result['mrr'])->toHaveKey('previous');
    expect($result['mrr'])->toHaveKey('delta');
    expect($result['mrr'])->toHaveKey('trend');
});
