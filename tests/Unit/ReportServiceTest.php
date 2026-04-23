<?php

namespace Foundry\Tests\Unit;

use Foundry\Services\Reports\Exports\SubscriptionsExportReport;
use Foundry\Services\Reports\ReportInterface;
use Foundry\Services\Reports\ReportService;
use Foundry\Services\Reports\Revenue\MrrByPlanReport;
use Foundry\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ReportServiceTest extends TestCase
{
    #[Test]
    public function test_can_get_all_report_types()
    {
        $types = ReportService::all();

        $this->assertIsArray($types);
        $this->assertGreaterThan(0, count($types));
        $this->assertContains('subscriptions', $types);
        $this->assertContains('orders', $types);
        $this->assertContains('mrr-by-plan', $types);
    }

    #[Test]
    public function test_can_get_grouped_reports()
    {
        $grouped = ReportService::grouped();

        $this->assertIsArray($grouped);
        $this->assertArrayHasKey('revenue', $grouped);
        $this->assertArrayHasKey('retention', $grouped);
        $this->assertArrayHasKey('economics', $grouped);
        $this->assertArrayHasKey('exports', $grouped);
    }

    #[Test]
    public function test_can_get_report_category()
    {
        $this->assertEquals('exports', ReportService::getCategory('subscriptions'));
        $this->assertEquals('revenue', ReportService::getCategory('mrr-by-plan'));
        $this->assertEquals('retention', ReportService::getCategory('customer-churn'));
        $this->assertNull(ReportService::getCategory('non-existent'));
    }

    #[Test]
    public function test_can_get_report_label()
    {
        $this->assertEquals('Subscriptions Export', ReportService::getLabel('subscriptions'));
        $this->assertEquals('MRR by Plan', ReportService::getLabel('mrr-by-plan'));
    }

    #[Test]
    public function test_can_resolve_export_report()
    {
        $service = ReportService::resolve('subscriptions');
        $this->assertInstanceOf(ReportInterface::class, $service);
        $this->assertInstanceOf(SubscriptionsExportReport::class, $service);
    }

    #[Test]
    public function test_can_resolve_revenue_report()
    {
        $service = ReportService::resolve('mrr-by-plan');
        $this->assertInstanceOf(ReportInterface::class, $service);
        $this->assertInstanceOf(MrrByPlanReport::class, $service);
    }

    #[Test]
    public function test_throws_exception_for_invalid_type()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown report type: invalid-type');

        ReportService::resolve('invalid-type');
    }

    #[Test]
    public function test_can_check_if_type_exists()
    {
        $this->assertTrue(ReportService::has('subscriptions'));
        $this->assertTrue(ReportService::has('mrr-by-plan'));
        $this->assertFalse(ReportService::has('invalid-type'));
    }

    #[Test]
    public function test_can_get_reports_for_category()
    {
        $revenue = ReportService::forCategory('revenue');
        $this->assertContains('mrr-by-plan', $revenue);

        $exports = ReportService::forCategory('exports');
        $this->assertContains('subscriptions', $exports);
        $this->assertContains('orders', $exports);
    }

    #[Test]
    public function test_can_get_all_with_labels()
    {
        $allWithLabels = ReportService::allWithLabels();

        $this->assertIsArray($allWithLabels);
        $this->assertArrayHasKey('subscriptions', $allWithLabels);
        $this->assertEquals('Subscriptions Export', $allWithLabels['subscriptions']);
    }

    #[Test]
    public function test_can_get_category_labels()
    {
        $labels = ReportService::getCategoryLabels();

        $this->assertIsArray($labels);
        $this->assertEquals('Revenue', $labels['revenue']);
        $this->assertEquals('Data Exports', $labels['exports']);
    }

    #[Test]
    public function test_export_report_can_handle_correct_type()
    {
        $service = new SubscriptionsExportReport;

        $this->assertTrue($service::canHandle('subscriptions'));
        $this->assertFalse($service::canHandle('orders'));
        $this->assertFalse($service::canHandle('mrr-by-plan'));
    }

    #[Test]
    public function test_revenue_report_can_handle_correct_type()
    {
        $service = new MrrByPlanReport;

        $this->assertTrue($service::canHandle('mrr-by-plan'));
        $this->assertFalse($service::canHandle('sales-summary'));
        $this->assertFalse($service::canHandle('subscriptions'));
    }

    #[Test]
    public function test_can_register_custom_report()
    {
        ReportService::register('custom-report', SubscriptionsExportReport::class);

        $this->assertTrue(ReportService::has('custom-report'));
        $service = ReportService::resolve('custom-report');
        $this->assertInstanceOf(SubscriptionsExportReport::class, $service);

        // Cleanup
        ReportService::unregister('custom-report');
        $this->assertFalse(ReportService::has('custom-report'));
    }
}
