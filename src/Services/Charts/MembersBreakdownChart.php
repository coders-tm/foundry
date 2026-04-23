<?php

namespace Foundry\Services\Charts;

use Foundry\Services\Metrics\SubscriptionMetrics;
use Illuminate\Http\Request;

class MembersBreakdownChart
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Get members breakdown by status
     */
    public function get(): array
    {
        $subscriptionMetrics = new SubscriptionMetrics($this->request->all());

        return [
            'Active' => $subscriptionMetrics->getActiveCount(),
            'On Trial' => $subscriptionMetrics->getTrialCount(),
            'Grace Period' => $subscriptionMetrics->getGracePeriodCount(),
            'Cancelled' => $subscriptionMetrics->getCancelledCount(),
        ];
    }
}
