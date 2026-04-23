<?php

namespace Foundry\Services\Charts;

use Illuminate\Http\Request;

class ChartService
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Get revenue chart data
     */
    public function getRevenueChart(): array
    {
        return (new RevenueChart($this->request))->get();
    }

    /**
     * Get subscription growth chart data
     */
    public function getSubscriptionChart(): array
    {
        return (new SubscriptionChart($this->request))->get();
    }

    /**
     * Get customer growth chart data
     */
    public function getCustomerChart(): array
    {
        return (new CustomerChart($this->request))->get();
    }

    /**
     * Get order trends chart data
     */
    public function getOrderChart(): array
    {
        return (new OrderChart($this->request))->get();
    }

    /**
     * Get MRR chart data
     */
    public function getMrrChart(): array
    {
        return (new MrrChart($this->request))->get();
    }

    /**
     * Get churn rate chart data
     */
    public function getChurnChart(): array
    {
        return (new ChurnChart($this->request))->get();
    }

    /**
     * Get revenue breakdown by source
     */
    public function getRevenueBreakdown(): array
    {
        return (new RevenueBreakdownChart($this->request))->get();
    }

    /**
     * Get members breakdown by status
     */
    public function getMembersBreakdown(): array
    {
        return (new MembersBreakdownChart($this->request))->get();
    }

    /**
     * Get ARPU trend chart data
     */
    public function getArpuChart(): array
    {
        return (new ArpuChart($this->request))->get();
    }

    /**
     * Get plan distribution chart data
     */
    public function getPlanDistribution(): array
    {
        return (new PlanDistributionChart($this->request))->get();
    }
}
