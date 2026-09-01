<?php

use Foundry\Services\Reports\AbstractReport;
use Foundry\Services\Reports\ReportService;
use Foundry\Tests\BaseTestCase;

uses(BaseTestCase::class);

afterEach(function () {
    ReportService::unregister('test-report');
});

it('can register new report type', function () {
    ReportService::register('test-report', TestReport::class);

    $this->assertTrue(ReportService::has('test-report'));
    $this->assertEquals(TestReport::class, ReportService::getServiceClass('test-report'));
});

it('can register new report with label', function () {
    ReportService::register('test-report', TestReport::class, 'My Test Report');

    $this->assertEquals('My Test Report', ReportService::getLabel('test-report'));
});

it('can register new report with category', function () {
    ReportService::register('test-report', TestReport::class, 'My Test Report', 'revenue');

    $grouped = ReportService::grouped();
    $this->assertContains('test-report', $grouped['revenue']);
    $this->assertEquals('revenue', ReportService::getCategory('test-report'));
});

it('can register new category', function () {
    ReportService::registerCategory('custom-category', 'Custom Category');
    ReportService::register('test-report', TestReport::class, 'My Test Report', 'custom-category');

    $categoryLabels = ReportService::getCategoryLabels();
    $this->assertArrayHasKey('custom-category', $categoryLabels);
    $this->assertEquals('Custom Category', $categoryLabels['custom-category']);

    $grouped = ReportService::grouped();
    $this->assertArrayHasKey('custom-category', $grouped);
    $this->assertContains('test-report', $grouped['custom-category']);
});

class TestReport extends AbstractReport
{
    public static function getType(): string
    {
        return 'test-report';
    }

    public function query(array $filters)
    {
        return [];
    }

    public function toRow($row): array
    {
        return [];
    }
}
