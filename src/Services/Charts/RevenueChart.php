<?php

namespace Foundry\Services\Charts;

use Foundry\Models\Order;
use Illuminate\Support\Facades\DB;

class RevenueChart extends AbstractChart
{
    /**
     * Get revenue chart data
     */
    public function get(): array
    {
        $dateFormat = $this->getDateFormatExpression('created_at');

        $revenueData = Order::select(
            DB::raw("{$dateFormat} as label"),
            DB::raw('SUM(grand_total) as total')
        )
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->where('payment_status', 'paid')
            ->groupBy('label')
            ->orderBy(DB::raw('MIN(created_at)'), 'ASC')
            ->get()
            ->mapWithKeys(fn ($item) => [$this->formatLabel($item->label) => round($item->total, 2)]);

        $labels = $this->getMonthLabels();
        $formattedData = [];

        foreach ($labels as $label) {
            $formattedData[$label] = $revenueData[$label] ?? 0;
        }

        return $formattedData;
    }
}
