<?php

namespace Foundry\Services\Charts;

use Foundry\Models\Subscription;
use Illuminate\Support\Facades\DB;

class SubscriptionChart extends AbstractChart
{
    /**
     * Get subscription growth chart data
     */
    public function get(): array
    {
        $dateFormat = $this->getDateFormatExpression('created_at');
        $canceledDateFormat = $this->getDateFormatExpression('cancels_at');

        $newSubs = Subscription::select(
            DB::raw("{$dateFormat} as label"),
            DB::raw('COUNT(*) as total')
        )
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->groupBy('label')
            ->orderBy(DB::raw('MIN(created_at)'), 'ASC')
            ->get()
            ->mapWithKeys(fn ($item) => [$this->formatLabel($item->label) => $item->total]);

        $cancelled = Subscription::select(
            DB::raw("{$canceledDateFormat} as label"),
            DB::raw('COUNT(*) as total')
        )
            ->whereBetween('cancels_at', [$this->startDate, $this->endDate])
            ->groupBy('label')
            ->orderBy(DB::raw('MIN(cancels_at)'), 'ASC')
            ->get()
            ->mapWithKeys(fn ($item) => [$this->formatLabel($item->label) => $item->total]);

        $labels = $this->getMonthLabels();
        $formattedData = [];

        foreach ($labels as $label) {
            $new = $newSubs[$label] ?? 0;
            $cancel = $cancelled[$label] ?? 0;
            $formattedData[$label] = [
                'new' => $new,
                'cancelled' => $cancel,
                'net' => $new - $cancel,
            ];
        }

        return $formattedData;
    }
}
