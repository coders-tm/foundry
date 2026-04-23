<?php

namespace Foundry\Services\Charts;

use Foundry\Models\Subscription;
use Illuminate\Support\Facades\DB;

class PlanDistributionChart extends AbstractChart
{
    /**
     * Get plan distribution — active subscriber count per plan (pie/donut chart).
     */
    public function get(): array
    {
        return Subscription::query()
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->where('subscriptions.status', 'active')
            ->select('plans.label', DB::raw('COUNT(*) as total'))
            ->groupBy('plans.label')
            ->orderByDesc('total')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->label => $item->total])
            ->toArray();
    }
}
