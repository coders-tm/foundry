<?php

use Foundry\Services\Reports\Exports\SubscriptionsExportReport;
use Foundry\Services\Reports\ReportInterface;
use Foundry\Services\Reports\ReportService;
use Foundry\Services\Reports\Revenue\MrrByPlanReport;
use Foundry\Tests\TestCase;

uses(TestCase::class);

it('can get all report types', function () {
    $types = ReportService::all();

    expect($types)->toBeArray();
    expect(count($types))->toBeGreaterThan(0);
    expect($types)->toContain('subscriptions');
    expect($types)->toContain('orders');
    expect($types)->toContain('mrr-by-plan');
});

it('can get grouped reports', function () {
    $grouped = ReportService::grouped();

    expect($grouped)->toBeArray();
    expect($grouped)->toHaveKey('revenue');
    expect($grouped)->toHaveKey('retention');
    expect($grouped)->toHaveKey('economics');
    expect($grouped)->toHaveKey('exports');
});

it('can get report category', function () {
    expect(ReportService::getCategory('subscriptions'))->toBe('exports');
    expect(ReportService::getCategory('mrr-by-plan'))->toBe('revenue');
    expect(ReportService::getCategory('customer-churn'))->toBe('retention');
    expect(ReportService::getCategory('non-existent'))->toBeNull();
});

it('can get report label', function () {
    expect(ReportService::getLabel('subscriptions'))->toBe('Subscriptions Export');
    expect(ReportService::getLabel('mrr-by-plan'))->toBe('MRR by Plan');
});

it('can resolve export report', function () {
    $service = ReportService::resolve('subscriptions');
    expect($service)->toBeInstanceOf(ReportInterface::class);
    expect($service)->toBeInstanceOf(SubscriptionsExportReport::class);
});

it('can resolve revenue report', function () {
    $service = ReportService::resolve('mrr-by-plan');
    expect($service)->toBeInstanceOf(ReportInterface::class);
    expect($service)->toBeInstanceOf(MrrByPlanReport::class);
});

it('throws exception for invalid type', function () {
    expect(fn () => ReportService::resolve('invalid-type'))->toThrow(InvalidArgumentException::class, 'Unknown report type: invalid-type');
});

it('can check if type exists', function () {
    expect(ReportService::has('subscriptions'))->toBeTrue();
    expect(ReportService::has('mrr-by-plan'))->toBeTrue();
    expect(ReportService::has('invalid-type'))->toBeFalse();
});

it('can get reports for category', function () {
    $revenue = ReportService::forCategory('revenue');
    expect($revenue)->toContain('mrr-by-plan');

    $exports = ReportService::forCategory('exports');
    expect($exports)->toContain('subscriptions');
    expect($exports)->toContain('orders');
});

it('can get all with labels', function () {
    $allWithLabels = ReportService::allWithLabels();

    expect($allWithLabels)->toBeArray();
    expect($allWithLabels)->toHaveKey('subscriptions');
    expect($allWithLabels['subscriptions'])->toBe('Subscriptions Export');
});

it('can get category labels', function () {
    $labels = ReportService::getCategoryLabels();

    expect($labels)->toBeArray();
    expect($labels['revenue'])->toBe('Revenue');
    expect($labels['exports'])->toBe('Data Exports');
});

it('export report can handle correct type', function () {
    $service = new SubscriptionsExportReport;

    expect($service::canHandle('subscriptions'))->toBeTrue();
    expect($service::canHandle('orders'))->toBeFalse();
    expect($service::canHandle('mrr-by-plan'))->toBeFalse();
});

it('revenue report can handle correct type', function () {
    $service = new MrrByPlanReport;

    expect($service::canHandle('mrr-by-plan'))->toBeTrue();
    expect($service::canHandle('sales-summary'))->toBeFalse();
    expect($service::canHandle('subscriptions'))->toBeFalse();
});

it('can register custom report', function () {
    ReportService::register('custom-report', SubscriptionsExportReport::class);

    expect(ReportService::has('custom-report'))->toBeTrue();
    $service = ReportService::resolve('custom-report');
    expect($service)->toBeInstanceOf(SubscriptionsExportReport::class);

    // Cleanup
    ReportService::unregister('custom-report');
    expect(ReportService::has('custom-report'))->toBeFalse();
});
