<?php

namespace Foundry\Tests\Feature;

use Foundry\Services\Reports\AbstractReport;
use Foundry\Services\Reports\ReportService;
use Foundry\Tests\BaseTestCase;

class ReportServiceExtensibilityTest extends BaseTestCase
{
    protected function tearDown(): void
    {
        // Cleanup to ensure we don't pollute other tests
        ReportService::unregister('test-report');
        parent::tearDown();
    }

    public function test_can_register_new_report_type()
    {
        ReportService::register('test-report', TestReport::class);

        $this->assertTrue(ReportService::has('test-report'));
        $this->assertEquals(TestReport::class, ReportService::getServiceClass('test-report'));
    }

    public function test_can_register_new_report_with_label()
    {
        ReportService::register('test-report', TestReport::class, 'My Test Report');

        $this->assertEquals('My Test Report', ReportService::getLabel('test-report'));
    }

    public function test_can_register_new_report_with_category()
    {
        ReportService::register('test-report', TestReport::class, 'My Test Report', 'revenue');

        $grouped = ReportService::grouped();
        $this->assertContains('test-report', $grouped['revenue']);
        $this->assertEquals('revenue', ReportService::getCategory('test-report'));
    }

    public function test_can_register_new_category()
    {
        ReportService::registerCategory('custom-category', 'Custom Category');
        ReportService::register('test-report', TestReport::class, 'My Test Report', 'custom-category');

        $categoryLabels = ReportService::getCategoryLabels();
        $this->assertArrayHasKey('custom-category', $categoryLabels);
        $this->assertEquals('Custom Category', $categoryLabels['custom-category']);

        $grouped = ReportService::grouped();
        $this->assertArrayHasKey('custom-category', $grouped);
        $this->assertContains('test-report', $grouped['custom-category']);
    }
}

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
