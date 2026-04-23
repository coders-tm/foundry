<?php

namespace Foundry\Services\Charts;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

abstract class AbstractChart
{
    protected Request $request;

    protected int $months;

    protected string $format;

    protected Carbon $startDate;

    protected Carbon $endDate;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $period = $request->input('period', 'month');
        $this->months = $period === 'year' ? 24 : 12;
        $this->format = 'M y'; // MMM YY format like "Jan 24"

        $this->startDate = Carbon::now()->subMonths($this->months - 1)->startOfMonth();
        $this->endDate = Carbon::now()->endOfMonth();
    }

    /**
     * Generate list of month labels
     */
    protected function getMonthLabels(): array
    {
        $labels = [];
        $diffInMonths = $this->startDate->diffInMonths($this->endDate);

        for ($i = 0; $i <= $diffInMonths; $i++) {
            $currentDate = $this->startDate->copy()->addMonths($i);
            $labels[] = $currentDate->format($this->format);
        }

        return $labels;
    }

    /**
     * Get database-specific date format expression
     */
    protected function getDateFormatExpression(string $column, string $format = '%b %y'): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => "DATE_FORMAT({$column}, '{$format}')",
            'pgsql' => "TO_CHAR({$column}, 'Mon YY')",
            'sqlite' => "strftime('%Y-%m', {$column})", // SQLite doesn't support %b or %y, so we use %Y-%m and format in PHP
            'sqlsrv' => "FORMAT({$column}, 'MMM yy')",
            default => "DATE_FORMAT({$column}, '{$format}')",
        };
    }

    /**
     * Format label for display (used for SQLite date conversion)
     */
    protected function formatLabel(string $label): string
    {
        $driver = DB::connection()->getDriverName();

        // SQLite returns YYYY-MM format, convert to "MMM YY"
        if ($driver === 'sqlite' && preg_match('/^\d{4}-\d{2}$/', $label)) {
            return Carbon::parse($label.'-01')->format($this->format);
        }

        return $label;
    }

    /**
     * Get chart data
     */
    abstract public function get(): array;
}
