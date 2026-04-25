<?php

namespace Foundry\Services\Metrics;

use Carbon\Carbon;
use Foundry\Foundry;
use Illuminate\Support\Facades\DB;

class OrderMetrics extends MetricsCalculator
{
    protected string $cachePrefix = 'order_metrics';

    /**
     * Get total revenue for date range
     */
    public function getTotalRevenue(): float
    {
        return $this->remember('total_revenue', function () {
            $range = $this->getDateRange();

            return Foundry::$orderModel::query()
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->sum('grand_total') ?? 0.0;
        });
    }

    /**
     * Get revenue from subscription orders only
     */
    public function getSubscriptionRevenue(): float
    {
        return $this->remember('subscription_revenue', function () {
            $range = $this->getDateRange();

            return Foundry::$orderModel::query()
                ->where('payment_status', 'paid')
                ->where('orderable_type', (new Foundry::$subscriptionModel)->getMorphClass())
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->sum('grand_total') ?? 0.0;
        });
    }

    /**
     * Get revenue from non-subscription orders
     */
    public function getNonSubscriptionRevenue(): float
    {
        return $this->remember('non_subscription_revenue', function () {
            $range = $this->getDateRange();

            return Foundry::$orderModel::query()
                ->where('payment_status', 'paid')
                ->where(function ($q) {
                    $q->whereNull('orderable_type')
                        ->orWhere('orderable_type', '!=', (new Foundry::$subscriptionModel)->getMorphClass());
                })
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->sum('grand_total') ?? 0.0;
        });
    }

    /**
     * Get Monthly Recurring Revenue (MRR) from subscription orders
     */
    public function getMRR(): float
    {
        return $this->remember('mrr', function () {
            return DB::table('orders')
                ->join('subscriptions', function ($join) {
                    $join->on('orders.orderable_id', '=', 'subscriptions.id')
                        ->where('orders.orderable_type', (new Foundry::$subscriptionModel)->getMorphClass());
                })
                ->where('orders.payment_status', 'paid')
                ->where('subscriptions.status', 'active')
                ->whereIn('orders.id', function ($query) {
                    $query->select(DB::raw('MAX(id)'))
                        ->from('orders')
                        ->where('payment_status', 'paid')
                        ->where('orderable_type', (new Foundry::$subscriptionModel)->getMorphClass())
                        ->groupBy('orderable_id');
                })
                ->sum(DB::raw("
                    CASE subscriptions.billing_interval
                        WHEN 'day' THEN (orders.grand_total / COALESCE(subscriptions.billing_interval_count, 1)) * 30
                        WHEN 'week' THEN (orders.grand_total / COALESCE(subscriptions.billing_interval_count, 1)) * 4.345
                        WHEN 'month' THEN (orders.grand_total / COALESCE(subscriptions.billing_interval_count, 1))
                        WHEN 'year' THEN (orders.grand_total / COALESCE(subscriptions.billing_interval_count, 1)) / 12
                        ELSE 0
                    END
                ")) ?? 0.0;
        });
    }

    /**
     * Get Annual Recurring Revenue (ARR)
     */
    public function getARR(): float
    {
        return $this->getMRR() * 12;
    }

    /**
     * Get Average Revenue Per User (ARPU) from subscription orders
     */
    public function getARPU(): float
    {
        return $this->remember('arpu', function () {
            $totalRevenue = DB::table('orders')
                ->join('subscriptions', function ($join) {
                    $join->on('orders.orderable_id', '=', 'subscriptions.id')
                        ->where('orders.orderable_type', (new Foundry::$subscriptionModel)->getMorphClass());
                })
                ->where('orders.payment_status', 'paid')
                ->sum('orders.grand_total') ?? 0.0;

            $totalUsers = DB::table('subscriptions')
                ->distinct('user_id')
                ->count('user_id');

            return $totalUsers > 0 ? round($totalRevenue / $totalUsers, 2) : 0.0;
        });
    }

    /**
     * Get revenue for specific period (today, week, month)
     */
    public function getRevenueByPeriod(string $period = 'today'): float
    {
        return $this->remember("revenue_{$period}", function () use ($period) {
            $query = Foundry::$orderModel::where('payment_status', 'paid');

            match ($period) {
                'today' => $query->whereDate('created_at', today()),
                'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                'month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
                'year' => $query->whereYear('created_at', now()->year),
                default => $query->whereDate('created_at', today()),
            };

            return $query->sum('grand_total') ?? 0.0;
        });
    }

    /**
     * Get Average Order Value (AOV)
     */
    public function getAOV(): float
    {
        return $this->remember('aov', function () {
            $range = $this->getDateRange();

            $total = Foundry::$orderModel::query()
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->sum('grand_total') ?? 0.0;

            $count = Foundry::$orderModel::query()
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->count();

            return $count > 0 ? round($total / $count, 2) : 0.0;
        });
    }

    /**
     * Get total orders count
     */
    public function getTotalOrders(): int
    {
        return $this->remember('total_orders', function () {
            $range = $this->getDateRange();

            return Foundry::$orderModel::query()
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->count();
        });
    }

    /**
     * Get orders by status
     */
    public function getByStatus(): array
    {
        return $this->remember('by_status', function () {
            $range = $this->getDateRange();

            return Foundry::$orderModel::query()
                ->select('status', DB::raw('count(*) as count'))
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status')
                ->toArray();
        });
    }

    /**
     * Get orders by payment status
     */
    public function getByPaymentStatus(): array
    {
        return $this->remember('by_payment_status', function () {
            $range = $this->getDateRange();

            return Foundry::$orderModel::query()
                ->select('payment_status', DB::raw('count(*) as count'))
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->groupBy('payment_status')
                ->get()
                ->pluck('count', 'payment_status')
                ->toArray();
        });
    }

    /**
     * Get orders by fulfillment status
     */
    public function getByFulfillmentStatus(): array
    {
        return $this->remember('by_fulfillment_status', function () {
            $range = $this->getDateRange();

            return Foundry::$orderModel::query()
                ->select('fulfillment_status', DB::raw('count(*) as count'))
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->groupBy('fulfillment_status')
                ->get()
                ->pluck('count', 'fulfillment_status')
                ->toArray();
        });
    }

    /**
     * Get pending orders (needs action)
     */
    public function getPendingCount(): int
    {
        return $this->remember('pending_count', function () {
            return Foundry::$orderModel::query()
                ->where('status', 'pending')
                ->where('payment_status', '!=', 'paid')
                ->count();
        });
    }

    /**
     * Get failed payments count
     */
    public function getFailedPaymentsCount(): int
    {
        return $this->remember('failed_payments', function () {
            return Foundry::$orderModel::where('payment_status', 'failed')->count();
        });
    }

    /**
     * Get refunded amount
     */
    public function getRefundedAmount(): float
    {
        return $this->remember('refunded_amount', function () {
            $range = $this->getDateRange();

            return Foundry::$orderModel::query()
                ->whereIn('payment_status', ['refunded', 'partially_refunded'])
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->sum('grand_total') ?? 0.0;
        });
    }

    /**
     * Get net revenue (revenue - refunds)
     */
    public function getNetRevenue(): float
    {
        return $this->remember('net_revenue', function () {
            return $this->getTotalRevenue() - $this->getRefundedAmount();
        });
    }

    /**
     * Get completion rate (percentage)
     */
    public function getCompletionRate(): float
    {
        return $this->remember('completion_rate', function () {
            $range = $this->getDateRange();

            $total = Foundry::$orderModel::query()
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->count();

            if ($total === 0) {
                return 0.0;
            }

            $completed = Foundry::$orderModel::query()
                ->where('status', 'completed')
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->count();

            return round(($completed / $total) * 100, 2);
        });
    }

    /**
     * Get gross sales (before discounts/refunds)
     */
    public function getGrossSales(): float
    {
        return $this->remember('gross_sales', function () {
            $range = $this->getDateRange();

            return Foundry::$orderModel::query()
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->sum(DB::raw('sub_total + tax_total + shipping_total')) ?? 0.0;
        });
    }

    /**
     * Get average discount rate (percentage)
     */
    public function getDiscountRate(): float
    {
        return $this->remember('discount_rate', function () {
            $gross = $this->getGrossSales();

            if ($gross <= 0) {
                return 0.0;
            }

            $range = $this->getDateRange();

            $totalDiscount = Foundry::$orderModel::query()
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->sum('discount_total') ?? 0.0;

            return round(($totalDiscount / $gross) * 100, 2);
        });
    }

    /**
     * Get average refund rate (percentage)
     */
    public function getRefundRate(): float
    {
        return $this->remember('refund_rate', function () {
            $gross = $this->getGrossSales();

            if ($gross <= 0) {
                return 0.0;
            }

            $refunded = $this->getRefundedAmount();

            return round(($refunded / $gross) * 100, 2);
        });
    }

    /**
     * Get average items per order
     */
    public function getItemsPerOrder(): float
    {
        return $this->remember('items_per_order', function () {
            $range = $this->getDateRange();

            $totalItems = DB::table('line_items')
                ->join('orders', 'line_items.itemable_id', '=', 'orders.id')
                ->where('line_items.itemable_type', (new Foundry::$orderModel)->getMorphClass())
                ->whereBetween('orders.created_at', [$range['start'], $range['end']])
                ->sum('line_items.quantity') ?? 0;

            $orderCount = Foundry::$orderModel::query()
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->count();

            return $orderCount > 0 ? round($totalItems / $orderCount, 2) : 0.0;
        });
    }

    /**
     * Get shipping revenue
     */
    public function getShippingRevenue(): float
    {
        return $this->remember('shipping_revenue', function () {
            $range = $this->getDateRange();

            return Foundry::$orderModel::query()
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->sum('shipping_total') ?? 0.0;
        });
    }

    /**
     * Get tax collected
     */
    public function getTaxCollected(): float
    {
        return $this->remember('tax_collected', function () {
            $range = $this->getDateRange();

            return Foundry::$orderModel::query()
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->sum('tax_total') ?? 0.0;
        });
    }

    /**
     * Get discount utilization (percentage of orders with discount)
     */
    public function getDiscountUtilization(): float
    {
        return $this->remember('discount_utilization', function () {
            $range = $this->getDateRange();

            $totalOrders = Foundry::$orderModel::query()
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->count();

            if ($totalOrders === 0) {
                return 0.0;
            }

            $ordersWithDiscount = Foundry::$orderModel::query()
                ->where('discount_total', '>', 0)
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->count();

            return round(($ordersWithDiscount / $totalOrders) * 100, 2);
        });
    }

    /**
     * Get top discount codes
     */
    public function getTopDiscountCodes(int $limit = 10): array
    {
        return $this->remember("top_discount_codes_{$limit}", function () use ($limit) {
            $range = $this->getDateRange();

            return DB::table('discount_lines')
                ->join('orders', function ($join) {
                    $join->on('discount_lines.discountable_id', '=', 'orders.id')
                        ->where('discount_lines.discountable_type', (new Foundry::$orderModel)->getMorphClass());
                })
                ->select(
                    'discount_lines.coupon_code as discount_code',
                    DB::raw('COUNT(*) as usage_count'),
                    DB::raw('SUM(discount_lines.value) as total_discount')
                )
                ->whereNotNull('discount_lines.coupon_code')
                ->where('discount_lines.coupon_code', '!=', '')
                ->whereBetween('orders.created_at', [$range['start'], $range['end']])
                ->groupBy('discount_lines.coupon_code')
                ->orderByDesc('usage_count')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }

    /**
     * Get top products by revenue
     */
    public function getTopProductsByRevenue(int $limit = 10): array
    {
        return $this->remember("top_products_revenue_{$limit}", function () use ($limit) {
            $range = $this->getDateRange();

            return DB::table('line_items')
                ->join('orders', function ($join) {
                    $join->on('line_items.itemable_id', '=', 'orders.id')
                        ->where('line_items.itemable_type', (new Foundry::$orderModel)->getMorphClass());
                })
                ->join('products', 'line_items.product_id', '=', 'products.id')
                ->select(
                    'products.id',
                    'products.title',
                    DB::raw('SUM(line_items.quantity) as units_sold'),
                    DB::raw('SUM(line_items.quantity * line_items.price) as total_revenue')
                )
                ->where('orders.payment_status', 'paid')
                ->whereBetween('orders.created_at', [$range['start'], $range['end']])
                ->groupBy('products.id', 'products.title')
                ->orderByDesc('total_revenue')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }

    /**
     * Get top products by units sold
     */
    public function getTopProductsByUnits(int $limit = 10): array
    {
        return $this->remember("top_products_units_{$limit}", function () use ($limit) {
            $range = $this->getDateRange();

            return DB::table('line_items')
                ->join('orders', function ($join) {
                    $join->on('line_items.itemable_id', '=', 'orders.id')
                        ->where('line_items.itemable_type', (new Foundry::$orderModel)->getMorphClass());
                })
                ->join('products', 'line_items.product_id', '=', 'products.id')
                ->select(
                    'products.id',
                    'products.title',
                    DB::raw('SUM(line_items.quantity) as units_sold'),
                    DB::raw('SUM(line_items.quantity * line_items.price) as total_revenue')
                )
                ->where('orders.payment_status', 'paid')
                ->whereBetween('orders.created_at', [$range['start'], $range['end']])
                ->groupBy('products.id', 'products.title')
                ->orderByDesc('units_sold')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }

    /**
     * Get orders by source/channel
     */
    public function getBySource(): array
    {
        return $this->remember('by_source', function () {
            $range = $this->getDateRange();

            return Foundry::$orderModel::query()
                ->select(
                    'source',
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(grand_total) as revenue')
                )
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->groupBy('source')
                ->get()
                ->map(function ($item) {
                    return [
                        'source' => $item->source ?? 'Direct',
                        'count' => $item->count,
                        'revenue' => $item->revenue ?? 0.0,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Get orders by payment method
     */
    public function getByPaymentMethod(): array
    {
        return $this->remember('by_payment_method', function () {
            $range = $this->getDateRange();

            return DB::table('payments')
                ->join('payment_methods', 'payments.payment_method_id', '=', 'payment_methods.id')
                ->join('orders', function ($join) {
                    $join->on('payments.paymentable_id', '=', 'orders.id')
                        ->where('payments.paymentable_type', (new Foundry::$orderModel)->getMorphClass());
                })
                ->select(
                    'payment_methods.provider',
                    DB::raw('COUNT(DISTINCT orders.id) as order_count'),
                    DB::raw('SUM(payments.amount) as total_amount')
                )
                ->where('payments.status', 'completed')
                ->whereBetween('orders.created_at', [$range['start'], $range['end']])
                ->groupBy('payment_methods.provider')
                ->get()
                ->toArray();
        });
    }

    /**
     * Get average fulfillment latency in hours
     */
    public function getAvgFulfillmentLatency(): float
    {
        return $this->remember('avg_fulfillment_latency', function () {
            $range = $this->getDateRange();

            $orders = Foundry::$orderModel::query()
                ->whereNotNull('shipped_at')
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->get(['created_at', 'shipped_at']);

            if ($orders->isEmpty()) {
                return 0.0;
            }

            $totalHours = $orders->sum(function ($order) {
                return $order->created_at->diffInHours($order->shipped_at);
            });

            return round($totalHours / $orders->count(), 2);
        });
    }

    /**
     * Get average delivery latency in hours
     */
    public function getAvgDeliveryLatency(): float
    {
        return $this->remember('avg_delivery_latency', function () {
            $range = $this->getDateRange();

            $orders = Foundry::$orderModel::query()
                ->whereNotNull('shipped_at')
                ->whereNotNull('delivered_at')
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->get(['shipped_at', 'delivered_at']);

            if ($orders->isEmpty()) {
                return 0.0;
            }

            $totalHours = $orders->sum(function ($order) {
                return $order->shipped_at->diffInHours($order->delivered_at);
            });

            return round($totalHours / $orders->count(), 2);
        });
    }

    /**
     * Get fulfillment backlog count (unshipped orders older than threshold)
     */
    public function getFulfillmentBacklog(int $hoursThreshold = 24): int
    {
        return $this->remember("fulfillment_backlog_{$hoursThreshold}", function () use ($hoursThreshold) {
            return Foundry::$orderModel::query()
                ->whereNull('shipped_at')
                ->whereNotIn('status', ['cancelled', 'delivered'])
                ->where('created_at', '<', now()->subHours($hoursThreshold))
                ->count();
        });
    }

    /**
     * Get on-time delivery rate (percentage)
     */
    public function getOnTimeDeliveryRate(int $expectedDays = 5): float
    {
        return $this->remember("on_time_delivery_{$expectedDays}", function () use ($expectedDays) {
            $range = $this->getDateRange();

            $delivered = Foundry::$orderModel::query()
                ->whereNotNull('delivered_at')
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->get();

            if ($delivered->isEmpty()) {
                return 0.0;
            }

            $onTime = $delivered->filter(function ($order) use ($expectedDays) {
                $expectedDelivery = $order->created_at->copy()->addDays($expectedDays);

                return $order->delivered_at <= $expectedDelivery;
            })->count();

            return round(($onTime / $delivered->count()) * 100, 2);
        });
    }

    /**
     * Get revenue by country
     */
    public function getRevenueByCountry(int $limit = 10): array
    {
        return $this->remember("revenue_by_country_{$limit}", function () use ($limit) {
            $range = $this->getDateRange();

            // For SQLite compatibility, we'll use a different approach
            $connection = DB::connection()->getDriverName();

            if ($connection === 'sqlite') {
                // SQLite-compatible version using json_extract
                return Foundry::$orderModel::query()
                    ->select(
                        DB::raw("json_extract(shipping_address, '$.country') as country"),
                        DB::raw('COUNT(*) as order_count'),
                        DB::raw('SUM(grand_total) as total_revenue')
                    )
                    ->where('payment_status', 'paid')
                    ->whereNotNull('shipping_address')
                    ->whereBetween('created_at', [$range['start'], $range['end']])
                    ->groupBy('country')
                    ->orderByDesc('total_revenue')
                    ->limit($limit)
                    ->get()
                    ->map(function ($item) {
                        return [
                            'country' => $item->country ?? 'Unknown',
                            'order_count' => $item->order_count,
                            'revenue' => $item->total_revenue ?? 0.0,
                        ];
                    })
                    ->toArray();
            } else {
                // MySQL/PostgreSQL compatible version
                return Foundry::$orderModel::query()
                    ->select(
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(shipping_address, '$.country')) as country"),
                        DB::raw('COUNT(*) as order_count'),
                        DB::raw('SUM(grand_total) as total_revenue')
                    )
                    ->where('payment_status', 'paid')
                    ->whereNotNull('shipping_address')
                    ->whereBetween('created_at', [$range['start'], $range['end']])
                    ->groupBy('country')
                    ->orderByDesc('total_revenue')
                    ->limit($limit)
                    ->get()
                    ->map(function ($item) {
                        return [
                            'country' => $item->country ?? 'Unknown',
                            'order_count' => $item->order_count,
                            'revenue' => $item->total_revenue ?? 0.0,
                        ];
                    })
                    ->toArray();
            }
        });
    }

    /**
     * Get revenue by region/state
     */
    public function getRevenueByRegion(int $limit = 10): array
    {
        return $this->remember("revenue_by_region_{$limit}", function () use ($limit) {
            $range = $this->getDateRange();

            // For SQLite compatibility, we'll use a different approach
            $connection = DB::connection()->getDriverName();

            if ($connection === 'sqlite') {
                // SQLite-compatible version using json_extract
                return Foundry::$orderModel::query()
                    ->select(
                        DB::raw("json_extract(shipping_address, '$.state') as region"),
                        DB::raw("json_extract(shipping_address, '$.country') as country"),
                        DB::raw('COUNT(*) as order_count'),
                        DB::raw('SUM(grand_total) as total_revenue')
                    )
                    ->where('payment_status', 'paid')
                    ->whereNotNull('shipping_address')
                    ->whereBetween('created_at', [$range['start'], $range['end']])
                    ->groupBy('region', 'country')
                    ->orderByDesc('total_revenue')
                    ->limit($limit)
                    ->get()
                    ->map(function ($item) {
                        return [
                            'region' => $item->region ?? 'Unknown',
                            'country' => $item->country ?? 'Unknown',
                            'order_count' => $item->order_count,
                            'revenue' => $item->total_revenue ?? 0.0,
                        ];
                    })
                    ->toArray();
            } else {
                // MySQL/PostgreSQL compatible version
                return Foundry::$orderModel::query()
                    ->select(
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(shipping_address, '$.state')) as region"),
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(shipping_address, '$.country')) as country"),
                        DB::raw('COUNT(*) as order_count'),
                        DB::raw('SUM(grand_total) as total_revenue')
                    )
                    ->where('payment_status', 'paid')
                    ->whereNotNull('shipping_address')
                    ->whereBetween('created_at', [$range['start'], $range['end']])
                    ->groupBy('region', 'country')
                    ->orderByDesc('total_revenue')
                    ->limit($limit)
                    ->get()
                    ->map(function ($item) {
                        return [
                            'region' => $item->region ?? 'Unknown',
                            'country' => $item->country ?? 'Unknown',
                            'order_count' => $item->order_count,
                            'revenue' => $item->total_revenue ?? 0.0,
                        ];
                    })
                    ->toArray();
            }
        });
    }

    /**
     * Get first vs repeat purchase breakdown
     */
    public function getFirstVsRepeatPurchases(): array
    {
        return $this->remember('first_vs_repeat', function () {
            $range = $this->getDateRange();

            $orders = Foundry::$orderModel::query()
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->get();

            $firstPurchases = 0;
            $firstRevenue = 0;
            $repeatPurchases = 0;
            $repeatRevenue = 0;

            foreach ($orders as $order) {
                if ($order->isFirstPurchase()) {
                    $firstPurchases++;
                    $firstRevenue += $order->grand_total;
                } else {
                    $repeatPurchases++;
                    $repeatRevenue += $order->grand_total;
                }
            }

            return [
                'first_purchase_count' => $firstPurchases,
                'first_purchase_revenue' => round($firstRevenue, 2),
                'repeat_purchase_count' => $repeatPurchases,
                'repeat_purchase_revenue' => round($repeatRevenue, 2),
                'repeat_purchase_rate' => $orders->count() > 0
                    ? round(($repeatPurchases / $orders->count()) * 100, 2)
                    : 0.0,
            ];
        });
    }

    /**
     * Get overdue unpaid orders count
     */
    public function getOverdueCount(): int
    {
        return $this->remember('overdue_count', function () {
            return Foundry::$orderModel::query()
                ->where('payment_status', '!=', 'paid')
                ->whereNotNull('due_date')
                ->where('due_date', '<', now())
                ->count();
        });
    }

    /**
     * Get all metrics
     */
    public function get(): array
    {
        $payload = [
            'total_revenue' => $this->getTotalRevenue(),
            'subscription_revenue' => $this->getSubscriptionRevenue(),
            'non_subscription_revenue' => $this->getNonSubscriptionRevenue(),
            'mrr' => $this->getMRR(),
            'arr' => $this->getARR(),
            'arpu' => $this->getARPU(),
            'revenue_today' => $this->getRevenueByPeriod('today'),
            'revenue_this_week' => $this->getRevenueByPeriod('week'),
            'revenue_this_month' => $this->getRevenueByPeriod('month'),
            'revenue_this_year' => $this->getRevenueByPeriod('year'),
            'aov' => $this->getAOV(),
            'total_orders' => $this->getTotalOrders(),
            'by_status' => $this->getByStatus(),
            'by_payment_status' => $this->getByPaymentStatus(),
            'by_fulfillment_status' => $this->getByFulfillmentStatus(),
            'pending_count' => $this->getPendingCount(),
            'failed_payments_count' => $this->getFailedPaymentsCount(),
            'refunded_amount' => $this->getRefundedAmount(),
            'net_revenue' => $this->getNetRevenue(),
            'completion_rate' => $this->getCompletionRate(),
            'gross_sales' => $this->getGrossSales(),
            'discount_rate' => $this->getDiscountRate(),
            'refund_rate' => $this->getRefundRate(),
            'items_per_order' => $this->getItemsPerOrder(),
            'shipping_revenue' => $this->getShippingRevenue(),
            'tax_collected' => $this->getTaxCollected(),
            'discount_utilization' => $this->getDiscountUtilization(),
            'by_source' => $this->getBySource(),
            'by_payment_method' => $this->getByPaymentMethod(),
            'avg_fulfillment_latency' => $this->getAvgFulfillmentLatency(),
            'avg_delivery_latency' => $this->getAvgDeliveryLatency(),
            'fulfillment_backlog_24h' => $this->getFulfillmentBacklog(24),
            'on_time_delivery_rate' => $this->getOnTimeDeliveryRate(5),
            'first_vs_repeat' => $this->getFirstVsRepeatPurchases(),
            'overdue_count' => $this->getOverdueCount(),
            'metadata' => $this->getMetadata(),
        ];

        $periods = $this->getComparisonPeriods();

        return $this->withComparisons($payload, [
            'total_revenue' => [
                'calculator' => fn (Carbon $start, Carbon $end) => $this->revenueBetween($start, $end),
                'type' => 'currency',
                'description' => __('Total revenue from :current_start to :current_end compared with :previous_start to :previous_end', [
                    'current_start' => $periods['current']['start']->format('d M'),
                    'current_end' => $periods['current']['end']->format('d M'),
                    'previous_start' => $periods['previous']['start']->format('d M'),
                    'previous_end' => $periods['previous']['end']->format('d M'),
                ]),
            ],
            'net_revenue' => [
                'calculator' => fn (Carbon $start, Carbon $end) => $this->netRevenueBetween($start, $end),
                'type' => 'currency',
                'description' => __('Net revenue from :current_start to :current_end compared with :previous_start to :previous_end', [
                    'current_start' => $periods['current']['start']->format('d M'),
                    'current_end' => $periods['current']['end']->format('d M'),
                    'previous_start' => $periods['previous']['start']->format('d M'),
                    'previous_end' => $periods['previous']['end']->format('d M'),
                ]),
            ],
            'total_orders' => [
                'calculator' => fn (Carbon $start, Carbon $end) => $this->orderCountBetween($start, $end),
                'description' => __('Total orders from :current_start to :current_end compared with :previous_start to :previous_end', [
                    'current_start' => $periods['current']['start']->format('d M'),
                    'current_end' => $periods['current']['end']->format('d M'),
                    'previous_start' => $periods['previous']['start']->format('d M'),
                    'previous_end' => $periods['previous']['end']->format('d M'),
                ]),
            ],
            'aov' => [
                'calculator' => fn (Carbon $start, Carbon $end) => $this->aovBetween($start, $end),
                'type' => 'currency',
                'description' => __('Average order value from :current_start to :current_end compared with :previous_start to :previous_end', [
                    'current_start' => $periods['current']['start']->format('d M'),
                    'current_end' => $periods['current']['end']->format('d M'),
                    'previous_start' => $periods['previous']['start']->format('d M'),
                    'previous_end' => $periods['previous']['end']->format('d M'),
                ]),
            ],
            'refund_rate' => [
                'calculator' => fn (Carbon $start, Carbon $end) => $this->refundRateBetween($start, $end),
                'type' => 'percentage',
                'description' => __('Refund rate from :current_start to :current_end compared with :previous_start to :previous_end', [
                    'current_start' => $periods['current']['start']->format('d M'),
                    'current_end' => $periods['current']['end']->format('d M'),
                    'previous_start' => $periods['previous']['start']->format('d M'),
                    'previous_end' => $periods['previous']['end']->format('d M'),
                ]),
            ],
        ]);
    }

    protected function revenueBetween(Carbon $start, Carbon $end): float
    {
        return Foundry::$orderModel::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->sum('grand_total') ?? 0.0;
    }

    protected function refundedBetween(Carbon $start, Carbon $end): float
    {
        return Foundry::$orderModel::query()
            ->whereIn('payment_status', ['refunded', 'partially_refunded'])
            ->whereBetween('created_at', [$start, $end])
            ->sum('grand_total') ?? 0.0;
    }

    protected function netRevenueBetween(Carbon $start, Carbon $end): float
    {
        return $this->revenueBetween($start, $end) - $this->refundedBetween($start, $end);
    }

    protected function orderCountBetween(Carbon $start, Carbon $end): int
    {
        return Foundry::$orderModel::query()
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    protected function aovBetween(Carbon $start, Carbon $end): float
    {
        $revenue = $this->revenueBetween($start, $end);
        $orders = $this->orderCountBetween($start, $end);

        return $orders > 0 ? round($revenue / $orders, 2) : 0.0;
    }

    protected function refundRateBetween(Carbon $start, Carbon $end): float
    {
        $revenue = $this->revenueBetween($start, $end);

        if ($revenue <= 0) {
            return 0.0;
        }

        return round(($this->refundedBetween($start, $end) / $revenue) * 100, 2);
    }
}
