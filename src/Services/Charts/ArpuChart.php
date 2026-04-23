<?php

namespace Foundry\Services\Charts;

use Foundry\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ArpuChart extends AbstractChart
{
    /**
     * Get ARPU trend chart data — subscription revenue / distinct subscribers per month.
     */
    public function get(): array
    {
        $labels = $this->getMonthLabels();
        $formattedData = [];

        for ($i = 0; $i < count($labels); $i++) {
            $monthStart = Carbon::now()->subMonths($this->months - 1 - $i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $revenue = DB::table('orders')
                ->join('subscriptions', function ($join) {
                    $join->on('orders.orderable_id', '=', 'subscriptions.id')
                        ->where('orders.orderable_type', (new Subscription)->getMorphClass());
                })
                ->where('orders.payment_status', 'paid')
                ->whereBetween('orders.created_at', [$monthStart, $monthEnd])
                ->sum('orders.grand_total') ?? 0.0;

            $subscribers = DB::table('subscriptions')
                ->where('status', 'active')
                ->where('created_at', '<=', $monthEnd)
                ->where(function ($q) use ($monthEnd) {
                    $q->whereNull('canceled_at')
                        ->orWhere('expires_at', '>', $monthEnd);
                })
                ->distinct('user_id')
                ->count('user_id');

            $formattedData[$labels[$i]] = $subscribers > 0
                ? round($revenue / $subscribers, 2)
                : 0;
        }

        return $formattedData;
    }
}
