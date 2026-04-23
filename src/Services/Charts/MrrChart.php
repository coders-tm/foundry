<?php

namespace Foundry\Services\Charts;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MrrChart extends AbstractChart
{
    /**
     * Get MRR chart data
     */
    public function get(): array
    {
        $labels = $this->getMonthLabels();
        $formattedData = [];

        // Calculate MRR for each month
        for ($i = 0; $i < count($labels); $i++) {
            $date = Carbon::now()->subMonths($this->months - 1 - $i)->endOfMonth();

            $mrr = DB::table('subscriptions')
                ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
                ->where('subscriptions.status', 'active')
                ->where('subscriptions.created_at', '<=', $date)
                ->where(function ($q) use ($date) {
                    $q->whereNull('subscriptions.canceled_at')
                        ->orWhere('subscriptions.expires_at', '>', $date);
                })
                ->sum(DB::raw("
                    CASE plans.interval
                        WHEN 'day' THEN (plans.price / COALESCE(plans.interval_count, 1)) * 30
                        WHEN 'week' THEN (plans.price / COALESCE(plans.interval_count, 1)) * 4.345
                        WHEN 'month' THEN (plans.price / COALESCE(plans.interval_count, 1))
                        WHEN 'year' THEN (plans.price / COALESCE(plans.interval_count, 1)) / 12
                        ELSE 0
                    END * COALESCE(subscriptions.quantity, 1)
                ")) ?? 0.0;

            $formattedData[$labels[$i]] = round($mrr, 2);
        }

        return $formattedData;
    }
}
