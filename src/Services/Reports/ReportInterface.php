<?php

namespace Foundry\Services\Reports;

use Foundry\Models\ReportExport;
use Foundry\Traits\DatabaseAgnostic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use League\Csv\Writer;

/**
 * Interface for all report generation services.
 *
 * Each report service should implement this interface to provide
 * a consistent API for generating reports.
 *
 * @see DatabaseAgnostic
 */
interface ReportInterface
{
    /**
     * Get the list of report types this service handles.
     *
     * @return array<string>
     */
    public static function getTypes(): array;

    /**
     * Check if this service can handle the given report type.
     */
    public static function canHandle(string $type): bool;

    /**
     * Generate the report and return the total record count.
     *
     * @return int Total records generated
     */
    public function generate(Writer $csv, ReportExport $reportExport): int;

    /**
     * Get the description for this report type.
     */
    public function getDescription(): string;

    /**
     * Validate and normalize filter input.
     *
     * Works like Laravel's Request::validate() - returns validated filters or throws exception.
     * Each report defines its own filter validation rules.
     *
     * @param  array  $input  Raw filter input from request
     * @return array Validated and normalized filters
     *
     * @throws ValidationException
     *
     * @example
     * ```php
     * // In controller:
     * $filters = $report->validate($request->input('filters', []));
     *
     * // In report:
     * public function validate(array $input): array
     * {
     *     return validator($input, [
     *         'date_from' => 'nullable|date',
     *         'date_to' => 'nullable|date|after_or_equal:date_from',
     *         'plan_id' => 'nullable|integer|exists:plans,id',
     *         'status' => 'nullable|string|in:active,inactive',
     *     ])->validate();
     * }
     * ```
     */
    public function validate(array $input): array;

    /**
     * Get ordered header map for exports.
     * Keys are column identifiers; values are human labels.
     *
     * @return array<string, string>
     */
    public function headers(): array;

    /**
     * Paginate report data for UI display.
     *
     * @param  array  $filters  Validated filter values
     * @param  int  $perPage  Number of records per page
     * @param  int  $page  Page number (1-indexed)
     * @return array Paginated result with data, pagination meta, and summary
     */
    public function paginate(array $filters, int $perPage = 15, int $page = 1): array;

    /**
     * Build the base query with all joins and conditions.
     *
     * @param  array  $filters  Validated filter values
     * @return \Illuminate\Contracts\Database\Query\Builder|Builder
     */
    public function query(array $filters);

    /**
     * Convert a model or row to exportable array in header order.
     *
     * @param  mixed  $row  Model instance or stdClass from query
     * @return array<string, mixed>
     */
    public function toRow($row): array;

    /**
     * Return summary/KPI data for the report.
     *
     * @param  array  $filters  Validated filter values
     * @return array<string, mixed>
     */
    public function summarize(array $filters): array;
}
