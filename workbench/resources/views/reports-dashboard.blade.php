<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Reports Dashboard</title>
    <!-- Using specific Tailwind version for workbench demo - for production, install via PostCSS -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio,line-clamp"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Order Analytics Dashboard</h1>
                        <p class="mt-1 text-sm text-gray-500">Shopify-level reporting and insights</p>
                        @if(isset($admin_user))
                            <p class="mt-1 text-xs text-blue-600">Authenticated as Admin: {{ $admin_user['name'] ?? 'Unknown' }}</p>
                        @endif
                    </div>
                    <div class="flex items-center space-x-4">
                        <select id="dateRange" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="7">Last 7 Days</option>
                            <option value="30" selected>Last 30 Days</option>
                            <option value="90">Last 90 Days</option>
                            <option value="365">Last Year</option>
                        </select>
                        <button onclick="refreshData()" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Refresh
                        </button>
                        <a href="/_workbench" class="text-sm text-gray-500 hover:text-gray-700">Re-authenticate</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Error Container -->
            <div id="error-container"></div>

            <!-- Loading State -->
            <div id="loading" class="flex items-center justify-center py-12">
                <div class="text-center">
                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
                    <p class="mt-4 text-gray-600">Loading dashboard data...</p>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div id="dashboard" class="hidden space-y-6">
                <!-- Financial KPIs -->
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Financial Overview</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Gross Sales</p>
                                    <p id="gross-sales" class="text-2xl font-bold text-gray-900 mt-2">$0</p>
                                </div>
                                <div class="p-3 bg-green-100 rounded-full">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Total revenue before discounts</p>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Net Sales</p>
                                    <p id="net-sales" class="text-2xl font-bold text-gray-900 mt-2">$0</p>
                                </div>
                                <div class="p-3 bg-blue-100 rounded-full">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">After discounts & refunds</p>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Avg Discount Rate</p>
                                    <p id="discount-rate" class="text-2xl font-bold text-gray-900 mt-2">0%</p>
                                </div>
                                <div class="p-3 bg-yellow-100 rounded-full">
                                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                    </svg>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Average discount percentage</p>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Refund Rate</p>
                                    <p id="refund-rate" class="text-2xl font-bold text-gray-900 mt-2">0%</p>
                                </div>
                                <div class="p-3 bg-red-100 rounded-full">
                                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"></path>
                                    </svg>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Percentage of refunded orders</p>
                        </div>
                    </div>
                </div>

                <!-- Operational KPIs -->
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Operational Metrics</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="bg-white rounded-lg shadow p-6">
                            <p class="text-sm font-medium text-gray-600">Avg Fulfillment Time</p>
                            <p id="fulfillment-latency" class="text-2xl font-bold text-gray-900 mt-2">0h</p>
                            <p class="text-xs text-gray-500 mt-2">Order to shipment</p>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <p class="text-sm font-medium text-gray-600">Avg Delivery Time</p>
                            <p id="delivery-latency" class="text-2xl font-bold text-gray-900 mt-2">0h</p>
                            <p class="text-xs text-gray-500 mt-2">Shipment to delivery</p>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <p class="text-sm font-medium text-gray-600">Fulfillment Backlog</p>
                            <p id="fulfillment-backlog" class="text-2xl font-bold text-gray-900 mt-2">0</p>
                            <p class="text-xs text-gray-500 mt-2">Orders pending >48h</p>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <p class="text-sm font-medium text-gray-600">On-Time Delivery</p>
                            <p id="on-time-delivery" class="text-2xl font-bold text-gray-900 mt-2">0%</p>
                            <p class="text-xs text-gray-500 mt-2">Within 7 days</p>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 1 -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Top Products by Revenue -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Products by Revenue</h3>
                        <div id="topProductsChart" class="min-h-[350px] w-full"></div>
                    </div>

                    <!-- Revenue by Country -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Revenue by Country</h3>
                        <div id="countryRevenueChart" class="min-h-[350px] w-full"></div>
                    </div>
                </div>

                <!-- Charts Row 2 -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Top Discount Codes -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Discount Codes</h3>
                        <div id="discountCodesChart" class="min-h-[350px] w-full"></div>
                    </div>

                    <!-- Customer Segments -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">First-Time vs Repeat Customers</h3>
                        <div id="customerSegmentChart" class="min-h-[350px] w-full"></div>
                    </div>
                </div>

                <!-- Additional Metrics -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white rounded-lg shadow p-6">
                        <p class="text-sm font-medium text-gray-600">Shipping Revenue</p>
                        <p id="shipping-revenue" class="text-2xl font-bold text-gray-900 mt-2">$0</p>
                    </div>

                    <div class="bg-white rounded-lg shadow p-6">
                        <p class="text-sm font-medium text-gray-600">Tax Collected</p>
                        <p id="tax-collected" class="text-2xl font-bold text-gray-900 mt-2">$0</p>
                    </div>

                    <div class="bg-white rounded-lg shadow p-6">
                        <p class="text-sm font-medium text-gray-600">Avg Items Per Order</p>
                        <p id="items-per-order" class="text-2xl font-bold text-gray-900 mt-2">0</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let charts = {};
        let authToken = null;
        let adminToken = @json($admin_token ?? null);
        let adminUser = @json($admin_user ?? null);

        // API configuration
        const API_BASE = '/api/reports/orders';

        // Set authentication token from session
        function setAuthToken() {
            try {
                // Use admin token for reports (admin-only functionality)
                if (adminToken) {
                    authToken = adminToken;
                    axios.defaults.headers.common['Authorization'] = `Bearer ${authToken}`;
                    console.log('Using admin token from session');
                    return true;
                } else {
                    throw new Error('No admin authentication token available in session');
                }
            } catch (error) {
                console.error('Authentication error:', error);
                document.getElementById('error-container').innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Authentication Failed</h3>
                                <p class="mt-1 text-sm text-red-700">No admin authentication token available. <a href="/_workbench" class="underline">Click here to re-authenticate</a>.</p>
                            </div>
                        </div>
                    </div>
                `;
                return false;
            }
        }

        // Format currency
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(amount);
        }

        // Format number
        function formatNumber(num) {
            return new Intl.NumberFormat('en-US').format(num);
        }

        // Get date range
        function getDateRange() {
            const days = document.getElementById('dateRange').value;
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(startDate.getDate() - parseInt(days));

            return {
                start_date: startDate.toISOString().split('T')[0],
                end_date: endDate.toISOString().split('T')[0]
            };
        }

        // Load metrics
        async function loadMetrics() {
            try {
                const dateRange = getDateRange();
                const response = await axios.get(`${API_BASE}/metrics`, {
                    params: dateRange
                });

                const data = response.data.data || response.data;

                // Update financial KPIs
                document.getElementById('gross-sales').textContent = formatCurrency(data.gross_sales || 0);
                document.getElementById('net-sales').textContent = formatCurrency(data.net_sales || 0);
                document.getElementById('discount-rate').textContent = (data.discount_rate || 0).toFixed(1) + '%';
                document.getElementById('refund-rate').textContent = (data.refund_rate || 0).toFixed(1) + '%';

                // Update operational KPIs
                document.getElementById('fulfillment-latency').textContent =
                    (data.avg_fulfillment_latency || 0).toFixed(1) + 'h';
                document.getElementById('delivery-latency').textContent =
                    (data.avg_delivery_latency || 0).toFixed(1) + 'h';
                document.getElementById('fulfillment-backlog').textContent =
                    formatNumber(data.fulfillment_backlog || 0);
                document.getElementById('on-time-delivery').textContent =
                    (data.on_time_delivery_rate || 0).toFixed(1) + '%';

                // Update additional metrics
                document.getElementById('shipping-revenue').textContent =
                    formatCurrency(data.shipping_revenue || 0);
                document.getElementById('tax-collected').textContent =
                    formatCurrency(data.tax_collected || 0);
                document.getElementById('items-per-order').textContent =
                    (data.items_per_order || 0).toFixed(1);

                // Update customer segment chart
                if (data.first_vs_repeat) {
                    updateCustomerSegmentChart(data.first_vs_repeat);
                }
            } catch (error) {
                console.error('Error loading metrics:', error);
                if (error.response?.status === 401) {
                    document.getElementById('error-container').innerHTML = `
                        <div class="bg-red-50 border border-red-200 rounded-md p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">Authentication Error</h3>
                                    <p class="mt-1 text-sm text-red-700">Session expired. <a href="/_workbench" class="underline">Click here to re-authenticate</a>.</p>
                                </div>
                            </div>
                        </div>
                    `;
                }
            }
        }

        // Load top products
        async function loadTopProducts() {
            try {
                const dateRange = getDateRange();
                const response = await axios.get(`${API_BASE}/top-products-by-revenue`, {
                    params: { ...dateRange, limit: 10 }
                });

                updateTopProductsChart(response.data.data || []);
            } catch (error) {
                console.error('Error loading top products:', error);
            }
        }

        // Load revenue by country
        async function loadRevenueByCountry() {
            try {
                const dateRange = getDateRange();
                const response = await axios.get(`${API_BASE}/revenue-by-country`, {
                    params: { ...dateRange, limit: 10 }
                });

                updateCountryRevenueChart(response.data.data || []);
            } catch (error) {
                console.error('Error loading country revenue:', error);
            }
        }

        // Load discount codes
        async function loadDiscountCodes() {
            try {
                const dateRange = getDateRange();
                const response = await axios.get(`${API_BASE}/top-discount-codes`, {
                    params: { ...dateRange, limit: 10 }
                });

                updateDiscountCodesChart(response.data.data || []);
            } catch (error) {
                console.error('Error loading discount codes:', error);
            }
        }

        // Update top products chart
        function updateTopProductsChart(data) {
            if (charts.topProducts) {
                charts.topProducts.destroy();
            }

            // Check if we have valid data
            const hasData = data && Array.isArray(data) && data.length > 0;

            let categories, seriesData, colors;

            if (!hasData) {
                // Show empty state chart like Shopify - grayed out bars
                categories = ['Product A', 'Product B', 'Product C', 'Product D'];
                seriesData = [1500, 1200, 900, 600]; // Sample heights for empty state
                colors = ['#E5E7EB']; // Gray for empty state
            } else {
                categories = data.map(item => item.name || 'Unknown');
                seriesData = data.map(item => item.revenue || 0);
                colors = ['#3B82F6'];
            }

            const options = {
                series: [{
                    name: hasData ? 'Revenue' : 'Sample Data',
                    data: seriesData
                }],
                chart: {
                    type: 'bar',
                    height: 350,
                    fontFamily: 'Inter, sans-serif',
                    toolbar: {
                        show: hasData
                    },
                    animations: {
                        enabled: hasData,
                        easing: 'easeinout',
                        speed: 800,
                    }
                },
                plotOptions: {
                    bar: {
                        borderRadius: 4,
                        horizontal: false,
                        columnWidth: '70%',
                    }
                },
                dataLabels: {
                    enabled: false
                },
                xaxis: {
                    categories: categories,
                    labels: {
                        rotate: -45,
                        style: {
                            fontSize: '12px',
                            fontFamily: 'Inter, sans-serif',
                            colors: hasData ? '#374151' : '#9CA3AF'
                        }
                    }
                },
                yaxis: {
                    labels: {
                        formatter: function (val) {
                            return hasData ? '$' + val.toLocaleString() : '';
                        },
                        style: {
                            fontSize: '12px',
                            fontFamily: 'Inter, sans-serif',
                        }
                    }
                },
                colors: colors,
                grid: {
                    borderColor: '#E5E7EB',
                    strokeDashArray: 3,
                },
                tooltip: {
                    enabled: hasData,
                    y: {
                        formatter: function (val) {
                            return hasData ? '$' + val.toLocaleString() : 'No data';
                        }
                    }
                },
                // Add overlay text for empty state
                annotations: hasData ? {} : {
                    position: 'front',
                    points: [{
                        x: '50%',
                        y: '50%',
                        marker: {
                            size: 0
                        },
                        label: {
                            text: 'No product data available',
                            style: {
                                fontSize: '14px',
                                fontFamily: 'Inter, sans-serif',
                                color: '#6B7280',
                                background: 'transparent',
                                border: 'none'
                            }
                        }
                    }]
                },
                responsive: [
                    {
                        breakpoint: 768,
                        options: {
                            chart: {
                                height: 300
                            },
                            xaxis: {
                                labels: {
                                    rotate: -90
                                }
                            }
                        }
                    }
                ]
            };

            charts.topProducts = new ApexCharts(document.querySelector("#topProductsChart"), options);
            charts.topProducts.render();
        }

        // Update country revenue chart
        function updateCountryRevenueChart(data) {
            if (charts.countryRevenue) {
                charts.countryRevenue.destroy();
            }

            // Check if we have valid data
            const hasData = data && Array.isArray(data) && data.length > 0;

            let chartData, chartLabels, chartColors;

            if (!hasData) {
                // Show empty state chart like Shopify - grayed out segments
                chartData = [25, 20, 15, 15, 25]; // Equal segments for empty state
                chartLabels = ['Country A', 'Country B', 'Country C', 'Country D', 'Country E'];
                chartColors = ['#E5E7EB', '#D1D5DB', '#E5E7EB', '#D1D5DB', '#E5E7EB']; // Gray colors for empty state
            } else {
                chartData = data.map(item => item.revenue || 0);
                chartLabels = data.map(item => item.country || 'Unknown');
                chartColors = [
                    '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
                    '#EC4899', '#0EA5E9', '#22C55E', '#FB923C', '#A855F7'
                ];
            }

            const options = {
                series: chartData,
                chart: {
                    type: 'donut',
                    height: 350,
                    fontFamily: 'Inter, sans-serif',
                    animations: {
                        enabled: hasData,
                        easing: 'easeinout',
                        speed: 800,
                    }
                },
                labels: chartLabels,
                colors: chartColors,
                dataLabels: {
                    enabled: hasData,
                    formatter: function (val) {
                        return hasData ? val.toFixed(1) + '%' : '';
                    },
                    style: {
                        fontSize: '12px',
                        fontFamily: 'Inter, sans-serif',
                    }
                },
                plotOptions: {
                    pie: {
                        expandOnClick: hasData,
                        donut: {
                            size: '65%',
                            labels: {
                                show: hasData,
                                total: {
                                    show: hasData,
                                    showAlways: false,
                                    label: hasData ? 'Total' : '',
                                    fontSize: '22px',
                                    fontFamily: 'Inter, sans-serif',
                                    color: '#373d3f',
                                    formatter: function (w) {
                                        if (!hasData) return '';
                                        const total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                        return '$' + total.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                },
                legend: {
                    position: 'bottom',
                    fontSize: '12px',
                    fontFamily: 'Inter, sans-serif',
                },
                tooltip: {
                    enabled: hasData,
                    y: {
                        formatter: function (val) {
                            return hasData ? '$' + val.toLocaleString() : 'No data';
                        }
                    }
                },
                states: {
                    hover: {
                        filter: {
                            type: hasData ? 'lighten' : 'none',
                            value: hasData ? 0.15 : 0,
                        }
                    }
                },
                // Add overlay text for empty state
                annotations: hasData ? {} : {
                    position: 'front',
                    points: [{
                        x: '50%',
                        y: '50%',
                        marker: {
                            size: 0
                        },
                        label: {
                            text: 'No country data available',
                            style: {
                                fontSize: '14px',
                                fontFamily: 'Inter, sans-serif',
                                color: '#6B7280',
                                background: 'transparent',
                                border: 'none'
                            }
                        }
                    }]
                },
                responsive: [
                    {
                        breakpoint: 768,
                        options: {
                            chart: {
                                height: 300
                            },
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                ]
            };

            charts.countryRevenue = new ApexCharts(document.querySelector("#countryRevenueChart"), options);
            charts.countryRevenue.render();
        }

        // Update discount codes chart
        function updateDiscountCodesChart(data) {
            if (charts.discountCodes) {
                charts.discountCodes.destroy();
            }

            // Check if we have valid data
            const hasData = data && Array.isArray(data) && data.length > 0;

            let categories, seriesData, colors;

            if (!hasData) {
                // Show empty state chart like Shopify - grayed out bars
                categories = ['Code A', 'Code B', 'Code C', 'Code D'];
                seriesData = [15, 12, 8, 5]; // Sample heights for empty state
                colors = ['#E5E7EB']; // Gray for empty state
            } else {
                categories = data.map(item => item.discount_code || 'N/A');
                seriesData = data.map(item => item.usage_count || 0);
                colors = ['#10B981'];
            }

            const options = {
                series: [{
                    name: hasData ? 'Orders' : 'Sample Data',
                    data: seriesData
                }],
                chart: {
                    type: 'bar',
                    height: 350,
                    fontFamily: 'Inter, sans-serif',
                    toolbar: {
                        show: hasData
                    },
                    animations: {
                        enabled: hasData,
                        easing: 'easeinout',
                        speed: 800,
                    }
                },
                plotOptions: {
                    bar: {
                        borderRadius: 4,
                        horizontal: true,
                        barHeight: '70%',
                    }
                },
                dataLabels: {
                    enabled: false
                },
                xaxis: {
                    categories: categories,
                    labels: {
                        style: {
                            fontSize: '12px',
                            fontFamily: 'Inter, sans-serif',
                            colors: hasData ? '#374151' : '#9CA3AF'
                        }
                    }
                },
                yaxis: {
                    labels: {
                        style: {
                            fontSize: '12px',
                            fontFamily: 'Inter, sans-serif',
                        }
                    }
                },
                colors: colors,
                grid: {
                    borderColor: '#E5E7EB',
                    strokeDashArray: 3,
                },
                tooltip: {
                    enabled: hasData,
                    x: {
                        formatter: function (val) {
                            return hasData ? val : 'Sample Code';
                        }
                    },
                    y: {
                        formatter: function (val) {
                            return hasData ? val + ' orders' : 'No data';
                        }
                    }
                },
                // Add overlay text for empty state
                annotations: hasData ? {} : {
                    position: 'front',
                    points: [{
                        x: '50%',
                        y: '50%',
                        marker: {
                            size: 0
                        },
                        label: {
                            text: 'No discount codes used',
                            style: {
                                fontSize: '14px',
                                fontFamily: 'Inter, sans-serif',
                                color: '#6B7280',
                                background: 'transparent',
                                border: 'none'
                            }
                        }
                    }]
                },
                responsive: [
                    {
                        breakpoint: 768,
                        options: {
                            chart: {
                                height: 300
                            }
                        }
                    }
                ]
            };

            charts.discountCodes = new ApexCharts(document.querySelector("#discountCodesChart"), options);
            charts.discountCodes.render();
        }

        // Update customer segment chart
        function updateCustomerSegmentChart(data) {
            if (charts.customerSegment) {
                charts.customerSegment.destroy();
            }

            // Check if we have valid data
            const hasData = data && (data.first_purchase > 0 || data.repeat_purchase > 0);

            let chartData, chartLabels, chartColors;

            if (!hasData) {
                // Show empty state chart like Shopify - grayed out segments
                chartData = [50, 50]; // Equal segments for empty state
                chartLabels = ['First-Time Customers', 'Repeat Customers'];
                chartColors = ['#E5E7EB', '#D1D5DB']; // Gray colors for empty state
            } else {
                chartData = [data.first_purchase || 0, data.repeat_purchase || 0];
                chartLabels = ['First-Time Customers', 'Repeat Customers'];
                chartColors = ['#10B981', '#3B82F6']; // Normal colors
            }

            const options = {
                series: chartData,
                chart: {
                    type: 'pie',
                    height: 350,
                    fontFamily: 'Inter, sans-serif',
                    animations: {
                        enabled: hasData, // Disable animations for empty state
                        easing: 'easeinout',
                        speed: 800,
                    }
                },
                labels: chartLabels,
                colors: chartColors,
                dataLabels: {
                    enabled: hasData, // Only show data labels when there's real data
                    formatter: function (val) {
                        return hasData ? val.toFixed(1) + '%' : ''
                    },
                    style: {
                        fontSize: '12px',
                        fontFamily: 'Inter, sans-serif',
                    }
                },
                legend: {
                    position: 'bottom',
                    fontSize: '12px',
                    fontFamily: 'Inter, sans-serif',
                },
                tooltip: {
                    enabled: hasData,
                    y: {
                        formatter: function (val) {
                            return hasData ? val + ' customers' : 'No data'
                        }
                    }
                },
                states: {
                    hover: {
                        filter: {
                            type: hasData ? 'lighten' : 'none',
                            value: hasData ? 0.15 : 0,
                        }
                    }
                },
                plotOptions: {
                    pie: {
                        expandOnClick: hasData, // Disable click interactions for empty state
                    }
                },
                responsive: [
                    {
                        breakpoint: 768,
                        options: {
                            chart: {
                                height: 300
                            }
                        }
                    }
                ],
                // Add overlay text for empty state
                annotations: hasData ? {} : {
                    position: 'front',
                    points: [{
                        x: '50%',
                        y: '50%',
                        marker: {
                            size: 0
                        },
                        label: {
                            text: 'No data available',
                            style: {
                                fontSize: '14px',
                                fontFamily: 'Inter, sans-serif',
                                color: '#6B7280',
                                background: 'transparent',
                                border: 'none'
                            }
                        }
                    }]
                }
            };

            charts.customerSegment = new ApexCharts(document.querySelector("#customerSegmentChart"), options);
            charts.customerSegment.render();
        }

        // Refresh all data
        async function refreshData() {
            document.getElementById('loading').classList.remove('hidden');
            document.getElementById('dashboard').classList.add('hidden');

            try {
                await Promise.all([
                    loadMetrics(),
                    loadTopProducts(),
                    loadRevenueByCountry(),
                    loadDiscountCodes()
                ]);
            } catch (error) {
                console.error('Error refreshing data:', error);
            } finally {
                document.getElementById('loading').classList.add('hidden');
                document.getElementById('dashboard').classList.remove('hidden');
            }
        }

        // Handle window resize for responsive charts
        function handleResize() {
            setTimeout(() => {
                Object.values(charts).forEach(chart => {
                    if (chart && chart.resize) {
                        chart.resize();
                    }
                });
            }, 100);
        }

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', async function() {
            // Set authentication token from session
            const authenticated = setAuthToken();

            if (!authenticated) {
                console.error('Failed to authenticate from session');
                return;
            }

            // Add user info to console
            if (adminUser) {
                console.log('Authenticated as admin:', adminUser.name);
            }

            // Load initial data
            refreshData();

            // Add event listener for date range change
            document.getElementById('dateRange').addEventListener('change', refreshData);

            // Add window resize listener for responsive charts
            window.addEventListener('resize', handleResize);
        });
    </script>
</body>
</html>
