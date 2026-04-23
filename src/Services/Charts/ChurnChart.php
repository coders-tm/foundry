<?php

namespace Foundry\Services\Charts;

use Foundry\Models\Subscription;
use Illuminate\Support\Carbon;

class ChurnChart extends AbstractChart
{
    /**
     * Get churn rate chart data
     */
    public function get(): array
    {
        $labels = $this->getMonthLabels();
        $formattedData = [];

        for ($i = 0; $i < count($labels); $i++) {
            $date = Carbon::now()->subMonths($this->months - 1 - $i);

            $activeStart = Subscription::query()
                ->where('status', 'active')
                ->where('created_at', '<', $date->copy()->startOfMonth())
                ->count();

            $churned = Subscription::query()
                ->whereYear('canceled_at', $date->year)
                ->whereMonth('canceled_at', $date->month)
                ->count();

            $churnRate = $activeStart > 0 ? ($churned / $activeStart) * 100 : 0;

            $formattedData[$labels[$i]] = [
                'churned' => $churned,
                'rate' => round($churnRate, 2),
            ];
        }

        return $formattedData;
    }
}
