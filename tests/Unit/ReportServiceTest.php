<?php

use Foundry\Services\Reports\Exports\SubscriptionsExportReport;
use Foundry\Services\Reports\ReportInterface;
use Foundry\Services\Reports\ReportService;
use Foundry\Services\Reports\Revenue\MrrByPlanReport;
use Foundry\Tests\TestCase;

uses(TestCase::class);

it('can get all report types', function () {
    $types = ReportService::all();

    $this->assertIsArray($types);
    $this->assertGreaterThan(0, count($types));
    $this->assertContains('subscriptions', $types);
    $this->assertContains('orders', $types);
    $this->assertContains('mrr-by-plan', $types);
});

it('can get grouped reports', function () {
    $grouped = ReportService::grouped();

    $this->assertIsArray($grouped);
    $this->assertArrayHasKey('revenue', $grouped);
    $this->assertArrayHasKey('retention', $grouped);
    $this->assertArrayHasKey('economics', $grouped);
    $this->assertArrayHasKey('exports', $grouped);
});

it('can get report category', function () {
    $this->assertEquals('exports', ReportService::getCategory('subscriptions'));
    $this->assertEquals('revenue', ReportService::getCategory('mrr-by-plan'));
    $this->assertEquals('retention', ReportService::getCategory('customer-churn'));
    $this->assertNull(ReportService::getCategory('non-existent'));
});

it('can get report label', function () {
    $this->assertEquals('Subscriptions Export', ReportService::getLabel('subscriptions'));
    $this->assertEquals('MRR by Plan', ReportService::getLabel('mrr-by-plan'));
});

it('can resolve export report', function () {
    $service = ReportService::resolve('subscriptions');
    $this->assertInstanceOf(ReportInterface::class, $service);
    $this->assertInstanceOf(SubscriptionsExportReport::class, $service);
});

it('can resolve revenue report', function () {
    $service = ReportService::resolve('mrr-by-plan');
    $this->assertInstanceOf(ReportInterface::class, $service);
    $this->assertInstanceOf(MrrByPlanReport::class, $service);
});

it('throws exception for invalid type', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown report type: invalid-type');

    ReportService::resolve('invalid-type');
});

it('can check if type exists', function () {
    $this->assertTrue(ReportService::has('subscriptions'));
    $this->assertTrue(ReportService::has('mrr-by-plan'));
    $this->assertFalse(ReportService::has('invalid-type'));
});

it('can get reports for category', function () {
    $revenue = ReportService::forCategory('revenue');
    $this->assertContains('mrr-by-plan', $revenue);

    $exports = ReportService::forCategory('exports');
    $this->assertContains('subscriptions', $exports);
    $this->assertContains('orders', $exports);
});

it('can get all with labels', function () {
    $allWithLabels = ReportService::allWithLabels();

    $this->assertIsArray($allWithLabels);
    $this->assertArrayHasKey('subscriptions', $allWithLabels);
    $this->assertEquals('Subscriptions Export', $allWithLabels['subscriptions']);
});

it('can get category labels', function () {
    $labels = ReportService::getCategoryLabels();

    $this->assertIsArray($labels);
    $this->assertEquals('Revenue', $labels['revenue']);
    $this->assertEquals('Data Exports', $labels['exports']);
});

it('export report can handle correct type', function () {
    $service = new SubscriptionsExportReport;

    $this->assertTrue($service::canHandle('subscriptions'));
    $this->assertFalse($service::canHandle('orders'));
    $this->assertFalse($service::canHandle('mrr-by-plan'));
});

it('revenue report can handle correct type', function () {
    $service = new MrrByPlanReport;

    $this->assertTrue($service::canHandle('mrr-by-plan'));
    $this->assertFalse($service::canHandle('sales-summary'));
    $this->assertFalse($service::canHandle('subscriptions'));
});

it('can register custom report', function () {
    ReportService::register('custom-report', SubscriptionsExportReport::class);

    $this->assertTrue(ReportService::has('custom-report'));
    $service = ReportService::resolve('custom-report');
    $this->assertInstanceOf(SubscriptionsExportReport::class, $service);

    // Cleanup
    ReportService::unregister('custom-report');
    $this->assertFalse(ReportService::has('custom-report'));
});
