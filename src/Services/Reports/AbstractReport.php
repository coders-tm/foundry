<?php

namespace Foundry\Services\Reports;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Foundry\Contracts\SubscriptionStatus;
use Foundry\Enum\PlanInterval;
use Foundry\Foundry;
use Foundry\Models\ReportExport;
use Foundry\Models\Subscription\Plan;
use Foundry\Concerns\DatabaseAgnostic;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use League\Csv\Writer;

/**
 * Abstract base class for single-responsibility report services.
 *
 * Each report class should:
 * - Handle ONE report type only
 * - Implement query(), headers(), and toRow() methods
 * - Use cursor-based streaming for large datasets
 *
 * @example
 * ```php
 * class SalesSummaryReport extends AbstractReport
 * {
 *     public static function getType(): string
 *     {
 *         return 'sales-summary';
 *     }
 *
 *     public function headers(): array
 *     {
 *         return [
 *             'id' => 'Order ID',
 *             'created_at' => 'Created At',
 *             'grand_total' => 'Grand Total',
 *         ];
 *     }
 *
 *     public function query(array $filters): Builder
 *     {
 *         return Order::query()
 *             ->whereBetween('created_at', [$filters['from'], $filters['to']]);
 *     }
 *
 *     public function toRow($row): array
 *     {
 *         return [
 *             'id' => $row->id,
 *             'created_at' => $this->formatDate($row->created_at),
 *             'grand_total' => $this->formatMoney($row->grand_total),
 *         ];
 *     }
 * }
 * ```
 */
abstract class AbstractReport implements ReportInterface
{
    use DatabaseAgnostic;

    /**
     * The report export context.
     */
    protected ReportExport $reportExport;

    /**
     * Parsed filters from report export.
     */
    protected array $filters = [];

    /**
     * The CSV writer instance.
     */
    protected Writer $csv;

    /**
     * Column definitions with metadata.
     * Define this property in child classes.
     *
     * @var array<string, array{label: string, type: string, format?: string}>
     */
    protected array $columns = [];

    /**
     * Get the single report type this class handles.
     */
    abstract public static function getType(): string;

    /**
     * Get ordered header map for exports.
     * Keys are column identifiers; values are human labels.
     * Automatically generated from columns property.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        $headers = [];
        foreach ($this->columns as $key => $column) {
            $headers[$key] = $column['label'];
        }

        return $headers;
    }

    /**
     * Get column metadata with data types for frontend rendering.
     *
     * @return array<string, array{label: string, type: string, format?: string}>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * Get a human-readable description of what this report does.
     */
    public function getDescription(): string
    {
        return 'Generate a detailed report for analysis';
    }

    /**
     * Validate and normalize filter input using Laravel validator.
     *
     * Works like Laravel's Request::validate() - returns validated filters or throws ValidationException.
     * Override in child classes to define report-specific filter validation rules.
     *
     * @param  array  $input  Raw filter input
     * @return array Validated and normalized filters
     *
     * @throws ValidationException
     *
     * @example
     * ```php
     * // In controller:
     * $filters = $report->validate($request->input('filters', []));
     *
     * // In report (override):
     * public function validate(array $input): array
     * {
     *     $validated = validator($input, [
     *         'date_from' => 'nullable|date',
     *         'date_to' => 'nullable|date|after_or_equal:date_from',
     *         'plan_id' => 'nullable|integer|exists:plans,id',
     *         'status' => 'nullable|string|in:active,inactive',
     *     ])->validate();
     *
     *     // Always return parent-processed filters
     *     return parent::validate($validated);
     * }
     * ```
     */
    public function validate(array $input): array
    {
        // Define validation rules for common filters
        $rules = [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'granularity' => 'nullable|string|in:daily,weekly,monthly,quarterly,yearly',
            'status' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:100000',
        ];

        // Validate input
        $validated = validator($input, $rules)->validate();

        // Parse and normalize the validated filters
        return $this->parseFilters($validated);
    }

    /**
     * Build the base query with all joins and conditions.
     *
     * @param  array  $filters  Parsed filter values
     * @return Builder|QueryBuilderContract
     */
    abstract public function query(array $filters);

    /**
     * Convert a model or row to exportable array in header order.
     *
     * @param  mixed  $row  Model instance or stdClass from query
     * @return array<string, mixed>
     */
    abstract public function toRow($row): array;

    /**
     * {@inheritdoc}
     */
    public static function getTypes(): array
    {
        return [static::getType()];
    }

    /**
     * {@inheritdoc}
     */
    public static function canHandle(string $type): bool
    {
        return $type === static::getType();
    }

    /**
     * {@inheritdoc}
     */
    public function generate(Writer $csv, ReportExport $reportExport): int
    {
        $this->csv = $csv;
        $this->setReportExport($reportExport);

        $filters = $this->parseFilters($this->filters);
        $this->validateFilters($filters);

        // Write headers
        $this->csv->insertOne(array_values($this->headers()));

        // Stream rows using cursor
        $totalRecords = 0;
        $this->stream($filters, function (array $row) use (&$totalRecords) {
            $this->csv->insertOne($this->orderRow($row));
            $totalRecords++;
        });

        return $totalRecords;
    }

    /**
     * Parse and normalize filter input.
     *
     * @param  array  $input  Raw filter input
     * @return array Normalized filters
     */
    private function parseFilters(array $input): array
    {
        return [
            'from' => isset($input['date_from'])
                ? Carbon::parse($input['date_from'])->startOfDay()
                : now()->subDays(30)->startOfDay(),
            'to' => isset($input['date_to'])
                ? Carbon::parse($input['date_to'])->endOfDay()
                : now()->endOfDay(),
            'granularity' => $input['granularity'] ?? 'monthly',
            'status' => $input['status'] ?? null,
            'plan_id' => $input['plan_id'] ?? null,
            'limit' => isset($input['limit']) ? (int) $input['limit'] : null,
        ];
    }

    /**
     * Validate filter values.
     *
     * @param  array  $filters  Parsed filters
     *
     * @throws \InvalidArgumentException
     */
    private function validateFilters(array $filters): void
    {
        if ($filters['from'] instanceof \DateTimeInterface && $filters['to'] instanceof \DateTimeInterface) {
            if ($filters['from'] > $filters['to']) {
                throw new \InvalidArgumentException('Invalid date range: from date must be before to date');
            }
        }
    }

    /**
     * Stream rows to a consumer using cursor for memory efficiency.
     *
     * @param  array  $filters  Parsed filter values
     * @param  callable(array):void  $consume  Callback to process each row
     */
    public function stream(array $filters, callable $consume): void
    {
        $query = $this->query($filters);

        if (isset($filters['limit']) && $filters['limit'] > 0) {
            $query->limit($filters['limit']);
        }

        foreach ($query->cursor() as $row) {
            $consume($this->toRow($row));
        }
    }

    /**
     * Paginate report data for UI display.
     *
     * @param  array  $filters  Parsed filter values
     * @param  int  $perPage  Number of records per page
     * @param  int  $page  Page number (1-indexed)
     * @return array Paginated result with data, pagination meta, and summary
     */
    public function paginate(array $filters, int $perPage = 15, int $page = 1): array
    {
        $query = $this->query($filters);

        // Get paginated results
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform items to report rows
        $data = $paginator->getCollection()->map(function ($item) {
            return $this->toRow($item);
        })->values()->all();

        // Get summary data
        $summary = $this->summarize($filters);

        return [
            'data' => $data,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'summary' => $summary,
            'columns' => $this->columns(),
        ];
    }

    /**
     * Generate rows from an array dataset (for synthetic/aggregated reports).
     *
     * @param  array<int, mixed>  $items  Items to process
     * @param  callable(array):void  $consume  Callback to process each row
     */
    public function generateFromArray(array $items, callable $consume): void
    {
        foreach ($items as $item) {
            $consume($this->toRow($item));
        }
    }

    /**
     * Order row values according to header keys.
     *
     * @param  array  $row  Row data
     * @return array Ordered values
     */
    protected function orderRow(array $row): array
    {
        $ordered = [];
        foreach (array_keys($this->headers()) as $key) {
            $ordered[] = $row[$key] ?? null;
        }

        return $ordered;
    }

    /**
     * Return summary/KPI data for the report.
     * Override in subclasses to provide summary footer or dashboard data.
     *
     * @param  array  $filters  Parsed filter values
     * @return array<string, mixed>
     */
    public function summarize(array $filters): array
    {
        return [];
    }

    /**
     * Pre-process hook called before streaming begins.
     * Override to perform setup operations.
     */
    protected function beforeStream(array $filters): void
    {
        // Override in subclasses if needed
    }

    /**
     * Post-process hook called after streaming completes.
     * Override to perform cleanup or final calculations.
     */
    protected function afterStream(array $filters, int $totalRecords): void
    {
        // Override in subclasses if needed
    }

    /**
     * Set the report export context.
     */
    public function setReportExport(ReportExport $reportExport): self
    {
        $this->reportExport = $reportExport;
        $this->filters = $reportExport->filters ?? [];

        return $this;
    }

    /**
     * Get filters from report export.
     */
    protected function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Get a specific filter value.
     */
    protected function getFilter(string $key, mixed $default = null): mixed
    {
        return $this->filters[$key] ?? $default;
    }

    /**
     * Get date periods based on granularity filter.
     */
    protected function getDatePeriods(): CarbonPeriod
    {
        $granularity = $this->getFilter('granularity', 'monthly');

        $dateFrom = $this->getFilter('date_from')
            ? Carbon::parse($this->getFilter('date_from'))
            : Carbon::now()->subMonths(12)->startOfMonth();

        $dateTo = $this->getFilter('date_to')
            ? Carbon::parse($this->getFilter('date_to'))
            : Carbon::now();

        $interval = match ($granularity) {
            'daily' => '1 day',
            'weekly' => '1 week',
            'quarterly' => '3 months',
            'yearly' => '1 year',
            default => '1 month',
        };

        return CarbonPeriod::create($dateFrom, $interval, $dateTo);
    }

    /**
     * Format period label based on granularity.
     */
    protected function formatPeriodLabel(Carbon $date): string
    {
        $granularity = $this->getFilter('granularity', 'monthly');

        return match ($granularity) {
            'daily' => $date->format('Y-m-d'),
            'weekly' => $date->format('Y-\WW'),
            'quarterly' => $date->format('Y-') . 'Q' . $date->quarter,
            'yearly' => $date->format('Y'),
            default => $date->format('Y-m'),
        };
    }

    /**
     * Get period end date based on granularity.
     */
    protected function getPeriodEnd(Carbon $periodStart): Carbon
    {
        $granularity = $this->getFilter('granularity', 'monthly');

        return match ($granularity) {
            'daily' => (clone $periodStart)->endOfDay(),
            'weekly' => (clone $periodStart)->addWeek()->subDay()->endOfDay(),
            'quarterly' => (clone $periodStart)->addQuarter()->subDay()->endOfDay(),
            'yearly' => (clone $periodStart)->addYear()->subDay()->endOfDay(),
            default => (clone $periodStart)->addMonth()->subDay()->endOfDay(),
        };
    }

    /**
     * Shortcut alias for formatMoney().
     */
    protected function money($value, ?string $currency = null): string
    {
        return $this->formatMoney($value, $currency);
    }

    /**
     * Shortcut alias for formatDate().
     */
    protected function date($value, string $format = 'Y-m-d H:i:s'): ?string
    {
        return $this->formatDate($value, $format);
    }

    /**
     * Format a monetary value.
     *
     * @param  float|int|null  $value
     */
    protected function formatMoney($value, ?string $currency = null): string
    {
        return format_amount((float) ($value ?? 0), $currency);
    }

    /**
     * Format a date value.
     *
     * @param  mixed  $value
     */
    protected function formatDate($value, string $format = 'Y-m-d H:i:s'): ?string
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format($format);
        }

        return Carbon::parse($value)->format($format);
    }

    /**
     * Format a number with decimal places.
     */
    protected function formatNumber(float $value, int $decimals = 2): string
    {
        return number_format($value, $decimals, '.', '');
    }

    /**
     * Format a percentage value.
     */
    protected function formatPercentage(float $value, int $decimals = 2): string
    {
        return number_format($value, $decimals, '.', '');
    }

    /**
     * Format a boolean value.
     */
    protected function formatBoolean($value): string
    {
        return $value ? 'Yes' : 'No';
    }

    /**
     * Safely convert a value to a string for CSV export.
     * Handles enums, objects, and null values properly.
     *
     * @param  mixed  $value
     */
    protected function toString($value): ?string
    {
        // Handle null
        if ($value === null) {
            return null;
        }

        // Handle BackedEnum (extracts the underlying value)
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        // Handle UnitEnum (uses the name)
        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        // Handle objects with __toString
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        // For primitives and everything else, cast to string
        return (string) $value;
    }

    /**
     * Get monthly price for a plan (normalized from yearly if needed).
     */
    protected function getMonthlyPrice(Plan $plan): float
    {
        $price = $plan->price;

        return match ($plan->interval) {
            PlanInterval::YEAR => $price / 12,
            PlanInterval::WEEK => $price * 4.345,
            PlanInterval::DAY => $price * 30,
            default => $price / ($plan->interval_count ?? 1),
        };
    }

    /**
     * Calculate MRR at a specific date.
     */
    protected function calculateMrrAtDate(Carbon $date): float
    {
        $mrr = 0;

        $subscriptions = DB::table('subscriptions')
            ->where('created_at', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('canceled_at')
                    ->orWhere('canceled_at', '>', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $date);
            })
            ->get();

        foreach ($subscriptions as $subscription) {
            $plan = Plan::find($subscription->plan_id);
            if ($plan) {
                $monthlyPrice = $this->getMonthlyPrice($plan);
                $mrr += $monthlyPrice * ($subscription->quantity ?? 1);
            }
        }

        return $mrr;
    }

    /**
     * Build base subscription query with common filters.
     */
    protected function buildSubscriptionQuery()
    {
        $query = DB::table('subscriptions');

        $status = $this->getFilter('status');
        if ($status) {
            if ($status === 'active') {
                $query->whereNull('canceled_at')
                    ->where('status', SubscriptionStatus::ACTIVE)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    });
            } elseif ($status === 'canceled') {
                $query->whereNotNull('canceled_at');
            }
        }

        if ($this->getFilter('date_from')) {
            $query->whereDate('created_at', '>=', $this->getFilter('date_from'));
        }

        if ($this->getFilter('date_to')) {
            $query->whereDate('created_at', '<=', $this->getFilter('date_to'));
        }

        return $query;
    }

    /**
     * Build base user query with common filters.
     */
    protected function buildUserQuery()
    {
        $query = Foundry::$userModel::query();

        if ($this->getFilter('status')) {
            $query->where('status', $this->getFilter('status'));
        }

        if ($this->getFilter('date_from')) {
            $query->whereDate('created_at', '>=', $this->getFilter('date_from'));
        }

        if ($this->getFilter('date_to')) {
            $query->whereDate('created_at', '<=', $this->getFilter('date_to'));
        }

        return $query;
    }

    /**
     * Build base orders query with common filters.
     */
    protected function buildOrdersQuery()
    {
        $query = DB::table('orders');

        if ($this->getFilter('status')) {
            $query->where('status', $this->getFilter('status'));
        }

        if ($this->getFilter('date_from')) {
            $query->whereDate('created_at', '>=', $this->getFilter('date_from'));
        }

        if ($this->getFilter('date_to')) {
            $query->whereDate('created_at', '<=', $this->getFilter('date_to'));
        }

        return $query;
    }

    /**
     * Build base payments query with common filters.
     */
    protected function buildPaymentsQuery()
    {
        $query = DB::table('payments');

        if ($this->getFilter('status')) {
            $query->where('status', $this->getFilter('status'));
        }

        if ($this->getFilter('date_from')) {
            $query->whereDate('created_at', '>=', $this->getFilter('date_from'));
        }

        if ($this->getFilter('date_to')) {
            $query->whereDate('created_at', '<=', $this->getFilter('date_to'));
        }

        return $query;
    }

    /**
     * Build base checkouts query with common filters.
     */
    protected function buildCheckoutsQuery()
    {
        $query = DB::table('checkouts');

        if ($this->getFilter('status')) {
            $query->where('status', $this->getFilter('status'));
        }

        if ($this->getFilter('date_from')) {
            $query->whereDate('started_at', '>=', $this->getFilter('date_from'));
        }

        if ($this->getFilter('date_to')) {
            $query->whereDate('started_at', '<=', $this->getFilter('date_to'));
        }

        return $query;
    }

    /**
     * Get plans filtered by the plan_id filter if set.
     */
    protected function getFilteredPlans()
    {
        $query = Plan::query();

        $planIds = $this->getFilter('plan_id');
        if (! empty($planIds)) {
            $query->whereIn('id', (array) $planIds);
        }

        return $query->get();
    }
}
