<?php

uses(Foundry\Tests\TestCase::class);

it('can get all report types', function () {
    $types = \Foundry\Services\Reports\ReportService::all();

    $this->assertIsArray($types);
    $this->assertGreaterThan(0, count($types));
    $this->assertContains('subscriptions', $types);
    $this->assertContains('orders', $types);
    $this->assertContains('mrr-by-plan', $types);
});

it('can get grouped reports', function () {
    $grouped = \Foundry\Services\Reports\ReportService::grouped();

    $this->assertIsArray($grouped);
    $this->assertArrayHasKey('revenue', $grouped);
    $this->assertArrayHasKey('retention', $grouped);
    $this->assertArrayHasKey('economics', $grouped);
    $this->assertArrayHasKey('exports', $grouped);
});

it('can get report category', function () {
    $this->assertEquals('exports', \Foundry\Services\Reports\ReportService::getCategory('subscriptions'));
    $this->assertEquals('revenue', \Foundry\Services\Reports\ReportService::getCategory('mrr-by-plan'));
    $this->assertEquals('retention', \Foundry\Services\Reports\ReportService::getCategory('customer-churn'));
    $this->assertNull(\Foundry\Services\Reports\ReportService::getCategory('non-existent'));
});

it('can get report label', function () {
    $this->assertEquals('Subscriptions Export', \Foundry\Services\Reports\ReportService::getLabel('subscriptions'));
    $this->assertEquals('MRR by Plan', \Foundry\Services\Reports\ReportService::getLabel('mrr-by-plan'));
});

it('can resolve export report', function () {
    $service = \Foundry\Services\Reports\ReportService::resolve('subscriptions');
    $this->assertInstanceOf(\Foundry\Services\Reports\ReportInterface::class, $service);
    $this->assertInstanceOf(\Foundry\Services\Reports\Exports\SubscriptionsExportReport::class, $service);
});

it('can resolve revenue report', function () {
    $service = \Foundry\Services\Reports\ReportService::resolve('mrr-by-plan');
    $this->assertInstanceOf(\Foundry\Services\Reports\ReportInterface::class, $service);
    $this->assertInstanceOf(\Foundry\Services\Reports\Revenue\MrrByPlanReport::class, $service);
});

it('throws exception for invalid type', function () {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown report type: invalid-type');

    \Foundry\Services\Reports\ReportService::resolve('invalid-type');
});

it('can check if type exists', function () {
    $this->assertTrue(\Foundry\Services\Reports\ReportService::has('subscriptions'));
    $this->assertTrue(\Foundry\Services\Reports\ReportService::has('mrr-by-plan'));
    $this->assertFalse(\Foundry\Services\Reports\ReportService::has('invalid-type'));
});

it('can get reports for category', function () {
    $revenue = \Foundry\Services\Reports\ReportService::forCategory('revenue');
    $this->assertContains('mrr-by-plan', $revenue);

    $exports = \Foundry\Services\Reports\ReportService::forCategory('exports');
    $this->assertContains('subscriptions', $exports);
    $this->assertContains('orders', $exports);
});

it('can get all with labels', function () {
    $allWithLabels = \Foundry\Services\Reports\ReportService::allWithLabels();

    $this->assertIsArray($allWithLabels);
    $this->assertArrayHasKey('subscriptions', $allWithLabels);
    $this->assertEquals('Subscriptions Export', $allWithLabels['subscriptions']);
});

it('can get category labels', function () {
    $labels = \Foundry\Services\Reports\ReportService::getCategoryLabels();

    $this->assertIsArray($labels);
    $this->assertEquals('Revenue', $labels['revenue']);
    $this->assertEquals('Data Exports', $labels['exports']);
});

it('export report can handle correct type', function () {
    $service = new \Foundry\Services\Reports\Exports\SubscriptionsExportReport;

    $this->assertTrue($service::canHandle('subscriptions'));
    $this->assertFalse($service::canHandle('orders'));
    $this->assertFalse($service::canHandle('mrr-by-plan'));
});

it('revenue report can handle correct type', function () {
    $service = new \Foundry\Services\Reports\Revenue\MrrByPlanReport;

    $this->assertTrue($service::canHandle('mrr-by-plan'));
    $this->assertFalse($service::canHandle('sales-summary'));
    $this->assertFalse($service::canHandle('subscriptions'));
});

it('can register custom report', function () {
    \Foundry\Services\Reports\ReportService::register('custom-report', \Foundry\Services\Reports\Exports\SubscriptionsExportReport::class);

    $this->assertTrue(\Foundry\Services\Reports\ReportService::has('custom-report'));
    $service = \Foundry\Services\Reports\ReportService::resolve('custom-report');
    $this->assertInstanceOf(\Foundry\Services\Reports\Exports\SubscriptionsExportReport::class, $service);

    // Cleanup
    \Foundry\Services\Reports\ReportService::unregister('custom-report');
    $this->assertFalse(\Foundry\Services\Reports\ReportService::has('custom-report'));
});
