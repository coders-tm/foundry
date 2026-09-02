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

    expect(ReportService::has('test-report'))->toBeTrue();
    expect(ReportService::getServiceClass('test-report'))->toEqual(TestReport::class);
});

it('can register new report with label', function () {
    ReportService::register('test-report', TestReport::class, 'My Test Report');

    expect(ReportService::getLabel('test-report'))->toEqual('My Test Report');
});

it('can register new report with category', function () {
    ReportService::register('test-report', TestReport::class, 'My Test Report', 'revenue');

    $grouped = ReportService::grouped();
    expect($grouped['revenue'])->toContain('test-report');
    expect(ReportService::getCategory('test-report'))->toEqual('revenue');
});

it('can register new category', function () {
    ReportService::registerCategory('custom-category', 'Custom Category');
    ReportService::register('test-report', TestReport::class, 'My Test Report', 'custom-category');

    $categoryLabels = ReportService::getCategoryLabels();
    expect($categoryLabels)->toHaveKey('custom-category');
    expect($categoryLabels['custom-category'])->toEqual('Custom Category');

    $grouped = ReportService::grouped();
    expect($grouped)->toHaveKey('custom-category');
    expect($grouped['custom-category'])->toContain('test-report');
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
