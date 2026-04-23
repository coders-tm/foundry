<?php

namespace Foundry\Services\Charts;

use Foundry\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderChart extends AbstractChart
{
    /**
     * Get order trends chart data
     */
    public function get(): array
    {
        $dateFormat = $this->getDateFormatExpression('created_at');

        $orderData = Order::select(
            DB::raw("{$dateFormat} as label"),
            DB::raw('COUNT(*) as orders'),
            DB::raw("SUM(CASE WHEN payment_status = 'paid' THEN grand_total ELSE 0 END) as revenue")
        )
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->groupBy('label')
            ->orderBy(DB::raw('MIN(created_at)'), 'ASC')
            ->get()
            ->mapWithKeys(fn ($item) => [$this->formatLabel($item->label) => [
                'orders' => $item->orders,
                'revenue' => round($item->revenue, 2),
            ]]);

        $labels = $this->getMonthLabels();
        $formattedData = [];

        foreach ($labels as $label) {
            $formattedData[$label] = $orderData[$label] ?? ['orders' => 0, 'revenue' => 0];
        }

        return $formattedData;
    }
}
