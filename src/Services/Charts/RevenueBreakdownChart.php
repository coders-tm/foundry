<?php

namespace Foundry\Services\Charts;

use Foundry\Services\Metrics\OrderMetrics;
use Illuminate\Http\Request;

class RevenueBreakdownChart
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Get revenue breakdown by source
     */
    public function get(): array
    {
        $orderMetrics = new OrderMetrics($this->request->all());

        return [
            'Subscription' => round($orderMetrics->getSubscriptionRevenue(), 2),
            'Product' => round($orderMetrics->getNonSubscriptionRevenue(), 2),
        ];
    }
}
