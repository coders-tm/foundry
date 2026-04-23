<?php

namespace Workbench\App\Http\Controllers\Admin;

use Foundry\Services\Charts\ChartService;
use Foundry\Services\Metrics\MetricsService;
use Foundry\Services\Reports\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Workbench\App\Http\Controllers\Controller;

class ReportsController extends Controller
{
    /**
     * Get time-series chart data
     */
    public function charts(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:revenue,subscriptions,customers,orders,mrr,churn,revenue-breakdown,members-breakdown,arpu,plan-distribution',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'period' => 'nullable|in:day,week,month,year',
            'granularity' => 'nullable|in:daily,weekly,monthly,yearly',
        ]);

        $chartService = new ChartService($request);

        $chartData = match ($request->input('type')) {
            'revenue' => $chartService->getRevenueChart(),
            'subscriptions' => $chartService->getSubscriptionChart(),
            'customers' => $chartService->getCustomerChart(),
            'orders' => $chartService->getOrderChart(),
            'mrr' => $chartService->getMrrChart(),
            'churn' => $chartService->getChurnChart(),
            'revenue-breakdown' => $chartService->getRevenueBreakdown(),
            'members-breakdown' => $chartService->getMembersBreakdown(),
            'arpu' => $chartService->getArpuChart(),
            'plan-distribution' => $chartService->getPlanDistribution(),
            default => [],
        };

        return response()->json($chartData);
    }

    /**
     * Get available report types.
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'types' => ReportService::allWithLabels(),
            'grouped' => ReportService::grouped(),
        ]);
    }

    /**
     * Get advanced metrics for a specific category.
     */
    public function metrics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'required|in:revenue,retention,economics,customers',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'compare' => 'nullable|boolean',
            'no_cache' => 'nullable|boolean',
        ]);

        if ($request->filled('date_from') && ! $request->filled('start_date')) {
            $request->merge(['start_date' => $validated['date_from']]);
        }

        if ($request->filled('date_to') && ! $request->filled('end_date')) {
            $request->merge(['end_date' => $validated['date_to']]);
        }

        $metricsService = new MetricsService($request->all());

        $metrics = match ($validated['category']) {
            'revenue' => $this->mapMetrics($metricsService->get([
                'total_revenue',
                'mrr',
                'arr',
                'aov',
            ])),
            'retention' => $this->mapMetrics($metricsService->get([
                'active_subscriptions',
                'trial_subscriptions',
                'grace_period_subscriptions',
                'cancelled_subscriptions',
                'churn',
                'trial_conversion_rate',
                'new_subscriptions',
            ]), [
                'active_subscriptions' => 'active_count',
                'trial_subscriptions' => 'trial_count',
                'grace_period_subscriptions' => 'grace_period_count',
                'cancelled_subscriptions' => 'cancelled_count',
                'churn' => 'churn_rate',
            ]),
            'economics' => $this->mapMetrics($metricsService->get([
                'arpu',
                'ltv',
            ]), [
                'ltv' => 'clv',
            ]),
            'customers' => $this->mapMetrics($metricsService->get([
                'total_users',
                'new_customers',
                'growth_rate',
                'ltv',
                'segments',
            ]), [
                'total_users' => 'total_count',
                'ltv' => 'clv',
            ]),
            default => [],
        };

        return response()->json($metrics);
    }

    /**
     * Get KPIs with period comparison
     */
    public function kpis(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'period' => 'nullable|in:day,week,month,year',
            'no_cache' => 'nullable|boolean',
            'includes' => 'nullable|string',
        ]);

        $metricsService = new MetricsService($request->all());

        // If includes parameter is present, only calculate requested KPIs
        if ($request->filled('includes')) {
            $includes = array_map('trim', explode(',', $request->input('includes')));

            return response()->json($metricsService->only($includes));
        }

        return response()->json($metricsService->get());
    }

    /**
     * Clear all reports cache
     */
    public function clearCache(Request $request): JsonResponse
    {
        (new MetricsService($request->all()))->clearCache();

        return response()->json(['message' => 'Reports cache cleared successfully']);
    }

    /**
     * Map metric keys to their expected output names.
     */
    protected function mapMetrics(array $metrics, array $map = []): array
    {
        if (empty($map)) {
            return $metrics;
        }

        $result = [];
        foreach ($metrics as $key => $value) {
            if ($key === 'comparisons') {
                // Re-key comparisons using the same map
                $mappedComparisons = [];
                foreach ($value as $compKey => $compValue) {
                    $newCompKey = $map[$compKey] ?? $compKey;
                    $mappedComparisons[$newCompKey] = $compValue;
                }
                $result['comparisons'] = $mappedComparisons;

                continue;
            }

            $newKey = $map[$key] ?? $key;
            $result[$newKey] = $value;

            // Also map comparison keys if present
            if (isset($metrics["{$key}_change"])) {
                $result["{$newKey}_change"] = $metrics["{$key}_change"];
            }
            if (isset($metrics["{$key}_previous"])) {
                $result["{$newKey}_previous"] = $metrics["{$key}_previous"];
            }
        }

        return $result;
    }
}
