<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Payment Test Page - Workbench</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2196f3;
            --border-color: #e0e0e0;
            --active-bg: #f8f9ff;
            --hover-bg: rgba(0, 0, 0, 0.02);
        }

        body {
            background: #f5f5f5;
        }

        .payment-container {
            max-width: 700px;
            margin: 0 auto;
        }

        .payment-methods-container {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            background: white;
        }

        .payment-method-option {
            border-bottom: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .payment-method-option:last-child {
            border-bottom: none;
        }

        .payment-method-option.active {
            background: var(--active-bg);
        }

        .payment-method-header {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 60px;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .payment-method-header:hover {
            background: var(--hover-bg);
        }

        .payment-method-option.active .payment-method-header {
            background: var(--active-bg);
        }

        .payment-method-info {
            flex: 1;
        }

        .payment-method-name {
            font-weight: 500;
            font-size: 14px;
            color: #333;
            margin-bottom: 2px;
        }

        .payment-method-description {
            font-size: 12px;
            color: #666;
        }

        .payment-method-logo {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
        }

        .payment-method-logo img {
            max-width: 60px;
            max-height: 40px;
            object-fit: contain;
        }

        .payment-logo-icon {
            font-size: 24px;
            color: #666;
            width: 40px;
            text-align: center;
        }

        /* Provider-specific icon colors */
        .fa-cc-stripe {
            color: #635bff;
        }

        .fa-cc-paypal {
            color: #0070ba;
        }

        .fa-cc-visa {
            color: #1a1f71;
        }

        .fa-cc-mastercard {
            color: #eb001b;
        }

        .fa-cc-amex {
            color: #006fcf;
        }

        .fa-google-pay {
            color: #4285f4;
        }

        .fa-apple-pay {
            color: #000;
        }

        .payment-method-content {
            padding: 20px;
            border-top: 1px solid #e8f0fe;
            background: var(--active-bg);
            display: none;
        }

        .payment-method-option.active .payment-method-content {
            display: block;
        }

        .payment-form {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .test-card-info {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 12px;
            margin-top: 12px;
        }

        .btn-pay {
            min-width: 250px;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 500;
        }

        .security-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #666;
            font-size: 13px;
        }

        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }

        .payment-instructions {
            background: #e7f3ff;
            border-left: 4px solid var(--primary-color);
            padding: 12px 16px;
            border-radius: 4px;
            margin-top: 12px;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-overlay.show {
            display: flex;
        }

        .loading-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="container py-5">
        <div class="payment-container">
            <!-- Header -->
            <div class="mb-4">
                <h2 class="mb-2" id="pageTitle">
                    @if ($token)
                        <span class="spinner-border spinner-border-sm me-2"></span>
                        Loading Order...
                    @else
                        Payment Test Page
                    @endif
                </h2>
                <p class="text-muted mb-3">
                    <i class="fas fa-lock me-1"></i>
                    All transactions are secure and encrypted.
                </p>
            </div>

            <!-- Order Details Card (will be populated via API) -->
            <div id="orderDetailsContainer" style="display: none;">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-file-invoice me-2"></i>
                            Order Details
                        </h5>
                    </div>
                    <div class="card-body" id="orderDetailsContent">
                        <!-- Content will be populated via JavaScript -->
                    </div>
                </div>

                <!-- Already Paid Notice -->
                <div id="alreadyPaidNotice" class="alert alert-success" style="display: none;">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>This order has already been paid.</strong>
                    Thank you for your payment!
                </div>
            </div>

            <!-- Payment Methods -->
            <div id="paymentMethodsContainer" class="mb-4">
                <h5 class="mb-3" id="paymentMethodsTitle">
                    @if ($token)
                        Select Payment Method
                    @else
                        Select Payment Method (Test Mode)
                    @endif
                </h5>

                @if ($paymentMethods->isEmpty())
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        No payment methods are currently enabled. Please configure payment methods in the admin
                        panel.
                    </div>
                @else
                    <div class="payment-methods-container">
                        @foreach ($paymentMethods as $index => $method)
                            <div class="payment-method-option" data-provider="{{ $method['provider'] }}"
                                data-method-id="{{ $method['id'] }}">
                                <div class="payment-method-header">
                                    <input type="radio" name="payment_method" value="{{ $method['provider'] }}"
                                        id="method-{{ $method['id'] }}" class="form-check-input"
                                        @if ($index === 0) checked @endif>
                                    <div class="payment-method-info">
                                        <div class="payment-method-name">
                                            {{ $method['label'] ?? $method['name'] }}
                                        </div>
                                        @if (!empty($method['description']))
                                            <div class="payment-method-description">
                                                {{ $method['description'] }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="payment-method-logo">
                                        @if (!empty($method['logo']) && (str_starts_with($method['logo'], 'http') || str_starts_with($method['logo'], '/')))
                                            <img src="{{ $method['logo'] }}" alt="{{ $method['name'] }}">
                                        @elseif(!empty($method['logo']))
                                            <i class="{{ $method['logo'] }} payment-logo-icon"></i>
                                        @else
                                            <i class="fas fa-credit-card payment-logo-icon"></i>
                                        @endif
                                    </div>
                                </div>

                                <div class="payment-method-content">
                                    @if ($method['provider'] === 'stripe')
                                        @include('payment-providers.stripe', ['method' => $method])
                                    @elseif($method['provider'] === 'paypal')
                                        @include('payment-providers.paypal', ['method' => $method])
                                    @elseif($method['provider'] === 'razorpay')
                                        @include('payment-providers.razorpay', ['method' => $method])
                                    @elseif($method['provider'] === 'manual')
                                        @include('payment-providers.manual', ['method' => $method])
                                    @else
                                        @include('payment-providers.generic', ['method' => $method])
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Footer Info -->
            @if (!$token)
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-3">
                            <i class="fas fa-info-circle text-primary me-2"></i>
                            Development Information
                        </h6>
                        <ul class="list-unstyled mb-0 small text-muted">
                            <li class="mb-2">
                                <i class="fas fa-server me-2"></i>
                                <strong>Start Server:</strong> <code>composer run serve</code>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-link me-2"></i>
                                <strong>Test URL:</strong> <code>http://localhost:8000/payment</code>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-file-invoice me-2"></i>
                                <strong>With Order:</strong> <code>http://localhost:8000/payment/{order_token}</code>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-database me-2"></i>
                                <strong>Payment Methods:</strong> Loaded from database via
                                <code>PaymentMethod::toPublic()</code>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-cloud me-2"></i>
                                <strong>Order Data:</strong> Fetched via API
                                <code>GET /api/payment/status/{token}</code>
                            </li>
                            <li>
                                <i class="fas fa-cog me-2"></i>
                                <strong>Configure:</strong> Enable/disable payment methods in the admin panel
                            </li>
                        </ul>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Processing...</span>
            </div>
            <h5>Processing Payment</h5>
            <p class="text-muted mb-0">Please wait...</p>
        </div>
    </div>

    <script>
        // Configuration
        const API_BASE_URL = '/api';
        const orderToken = '{{ $token ?? '' }}';

        // Order state
        let orderData = null;
        let selectedProvider = null;

        document.addEventListener('DOMContentLoaded', function() {
            // Load order details if token exists
            if (orderToken) {
                loadOrderDetails();
            }

            // Payment method selection
            const paymentOptions = document.querySelectorAll('.payment-method-option');
            const paymentHeaders = document.querySelectorAll('.payment-method-header');

            paymentHeaders.forEach((header, index) => {
                header.addEventListener('click', function(e) {
                    if (e.target.type === 'radio') return; // Let radio handle its own click

                    const option = this.closest('.payment-method-option');
                    const radio = option.querySelector('input[type="radio"]');

                    // Deactivate all options
                    paymentOptions.forEach(opt => opt.classList.remove('active'));

                    // Activate selected option
                    option.classList.add('active');
                    radio.checked = true;
                    selectedProvider = radio.value;
                });
            });

            // Radio change event - setup payment intent when provider is selected
            document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
                radio.addEventListener('change', async function() {
                    paymentOptions.forEach(opt => opt.classList.remove('active'));
                    this.closest('.payment-method-option').classList.add('active');
                    selectedProvider = this.value;

                    // Automatically setup payment intent for providers that need it
                    // (PayPal, Stripe, Razorpay, etc. need client_secret before rendering buttons)
                    await setupPaymentIntentForProvider(selectedProvider);
                });
            });

            // Set first option as active by default and setup payment intent
            if (paymentOptions.length > 0) {
                paymentOptions[0].classList.add('active');
                const firstRadio = paymentOptions[0].querySelector('input[type="radio"]');
                if (firstRadio) {
                    firstRadio.checked = true;
                    selectedProvider = firstRadio.value;
                    // Setup payment intent for default provider
                    setupPaymentIntentForProvider(selectedProvider);
                }
            }

            // Payment form submissions
            const paymentForms = document.querySelectorAll('.payment-provider-form');
            paymentForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    handlePayment(this);
                });
            });
        });

        /**
         * Load order details from API
         */
        async function loadOrderDetails() {
            try {
                const response = await fetch(`${API_BASE_URL}/payment/status/${orderToken}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                });

                const order = await response.json();

                if (order && order.id) {
                    orderData = order;
                    console.log('Order loaded:', orderData);

                    // Update UI with fresh order data if needed
                    updateOrderUI(orderData);
                } else {
                    console.error('Failed to load order');
                    showError('Failed to load order details');
                }
            } catch (error) {
                console.error('Error loading order:', error);
                showError('Failed to connect to server');
            }
        }

        /**
         * Update order UI with fresh data
         */
        function updateOrderUI(order) {
            // Update page title
            document.getElementById('pageTitle').innerHTML = `Pay Invoice #${order.id}`;

            // Check if order is already paid
            if (order.payment_status === 'paid') {
                document.getElementById('alreadyPaidNotice').style.display = 'block';
                document.getElementById('paymentMethodsContainer').style.display = 'none';
            } else {
                document.getElementById('alreadyPaidNotice').style.display = 'none';
                document.getElementById('paymentMethodsContainer').style.display = 'block';
            }

            // Render order details
            const orderDetailsHtml = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Order Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <td class="text-muted">Order Number:</td>
                                <td><strong>#${order.id}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Date:</td>
                                <td>${formatDate(order.created_at)}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Status:</td>
                                <td>
                                    <span class="badge bg-${order.status === 'completed' ? 'success' : 'warning'}">
                                        ${capitalizeFirst(order.status)}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Payment Status:</td>
                                <td>
                                    <span class="badge bg-${order.payment_status === 'paid' ? 'success' : 'danger'}">
                                        ${capitalizeFirst(order.payment_status)}
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Billing Details</h6>
                        ${renderBillingAddress(order.billing_address)}
                    </div>
                </div>

                ${order.line_items && order.line_items.length > 0 ? renderLineItems(order) : ''}
            `;

            document.getElementById('orderDetailsContent').innerHTML = orderDetailsHtml;
            document.getElementById('orderDetailsContainer').style.display = 'block';

            console.log('Order UI updated successfully');
        }

        /**
         * Render billing address
         */
        function renderBillingAddress(address) {
            if (!address) return '<p class="text-muted">No billing address</p>';

            let html = '';
            const fullName = [address.first_name, address.last_name].filter(Boolean).join(' ');

            if (fullName) {
                html += `<p class="mb-1"><strong>${fullName}</strong></p>`;
            }
            if (address.email) {
                html += `<p class="mb-1"><i class="fas fa-envelope me-2 text-muted"></i>${address.email}</p>`;
            }
            if (address.phone) {
                html += `<p class="mb-1"><i class="fas fa-phone me-2 text-muted"></i>${address.phone}</p>`;
            }
            if (address.address_1) {
                html += `<p class="mb-1">${address.address_1}</p>`;
            }
            if (address.address_2) {
                html += `<p class="mb-1">${address.address_2}</p>`;
            }

            const cityState = [address.city, address.state].filter(Boolean).join(', ');
            if (cityState || address.postcode) {
                html += `<p class="mb-1">${cityState} ${address.postcode || ''}</p>`;
            }
            if (address.country) {
                html += `<p class="mb-1">${address.country}</p>`;
            }

            return html || '<p class="text-muted">No billing address</p>';
        }

        /**
         * Render line items table
         */
        function renderLineItems(order) {
            let html = `
                <hr class="my-3">
                <h6 class="text-muted mb-3">Order Items</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            order.line_items.forEach(item => {
                html += `
                    <tr>
                        <td><strong>${item.title || item.description}</strong></td>
                        <td class="text-center">${item.quantity}</td>
                        <td class="text-end">${item.formatted_price || formatCurrency(item.price)}</td>
                        <td class="text-end">${item.formatted_total || formatCurrency(item.total)}</td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                <td class="text-end">${formatCurrency(order.sub_total)}</td>
                            </tr>
            `;

            if (order.tax_total > 0) {
                html += `
                    <tr>
                        <td colspan="3" class="text-end"><strong>Tax:</strong></td>
                        <td class="text-end">${formatCurrency(order.tax_total)}</td>
                    </tr>
                `;
            }

            if (order.discount_total > 0) {
                html += `
                    <tr>
                        <td colspan="3" class="text-end"><strong>Discount:</strong></td>
                        <td class="text-end text-success">-${formatCurrency(order.discount_total)}</td>
                    </tr>
                `;
            }

            if (order.shipping_total > 0) {
                html += `
                    <tr>
                        <td colspan="3" class="text-end"><strong>Shipping:</strong></td>
                        <td class="text-end">${formatCurrency(order.shipping_total)}</td>
                    </tr>
                `;
            }

            html += `
                            <tr class="table-active">
                                <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                <td class="text-end"><strong>${formatCurrency(order.grand_total)}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            `;

            return html;
        }

        /**
         * Helper: Format date
         */
        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        /**
         * Helper: Capitalize first letter
         */
        function capitalizeFirst(str) {
            if (!str) return '';
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        /**
         * Helper: Format currency
         */
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: orderData?.currency || 'USD'
            }).format(amount);
        }

        /**
         * Setup payment intent when payment provider is selected
         * This is needed for providers like PayPal, Stripe, Razorpay that require
         * client secrets before rendering their payment buttons
         */
        let paymentIntentCache = {}; // Cache payment intents per provider

        async function setupPaymentIntentForProvider(provider) {
            if (!provider || !orderToken) {
                return;
            }

            // Check if already cached
            if (paymentIntentCache[provider]) {
                console.log(`Using cached payment intent for ${provider}`);
                return paymentIntentCache[provider];
            }

            console.log(`Setting up payment intent for ${provider}...`);

            try {
                const setupResponse = await fetch(`${API_BASE_URL}/payment/${provider}/setup-intent`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        token: orderToken,
                    }),
                });

                const setupData = await setupResponse.json();

                if (!setupResponse.ok) {
                    console.error(`Failed to setup payment intent for ${provider}:`, setupData.message);
                    return null;
                }

                console.log(`Payment intent ready for ${provider}:`, setupData);

                // Cache the setup data
                paymentIntentCache[provider] = setupData;

                // For providers that need special rendering (like PayPal buttons)
                if (provider.toLowerCase() === 'paypal' && setupData.approval_url) {
                    renderPayPalButtons(setupData);
                }

                return setupData;
            } catch (error) {
                console.error(`Error setting up payment intent for ${provider}:`, error);
                return null;
            }
        }

        /**
         * Render PayPal buttons when approval URL is ready
         */
        function renderPayPalButtons(setupData) {
            const paypalContainer = document.getElementById('paypal-button-container');
            if (!paypalContainer) {
                console.warn('PayPal button container not found');
                return;
            }

            // Clear existing buttons
            paypalContainer.innerHTML = '';

            // If using PayPal SDK, render buttons here
            // For now, just show a payment button that redirects to approval URL
            if (setupData.approval_url) {
                paypalContainer.innerHTML = `
                    <button type="button" class="btn btn-primary w-100" onclick="window.location.href='${setupData.approval_url}'">
                        <i class="bi bi-paypal me-2"></i> Pay with PayPal
                    </button>
                `;
            }
        }

        /**
         * Handle payment form submission
         */
        async function handlePayment(form) {
            const provider = form.dataset.provider || selectedProvider;

            if (!provider) {
                showError('Please select a payment method');
                return;
            }

            if (!orderToken) {
                alert('No order token found. This is test mode.');
                return;
            }

            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.classList.add('show');

            try {
                // Check if payment intent is already setup (should be from selection)
                let setupData = paymentIntentCache[provider];

                // If not cached, setup now
                if (!setupData) {
                    console.log('Payment intent not cached, setting up now for provider:', provider);
                    const setupResponse = await fetch(`${API_BASE_URL}/payment/${provider}/setup-intent`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({
                            token: orderToken,
                        }),
                    });

                    setupData = await setupResponse.json();

                    if (!setupResponse.ok) {
                        throw new Error(setupData.message || 'Failed to setup payment');
                    }

                    console.log('Payment intent setup successful:', setupData);
                }

                // Step 2: Handle provider-specific payment flow
                await handleProviderPayment(provider, setupData);

            } catch (error) {
                console.error('Payment error:', error);
                loadingOverlay.classList.remove('show');
                showError(error.message || 'Payment failed. Please try again.');
            }
        }

        /**
         * Handle provider-specific payment flow
         */
        async function handleProviderPayment(provider, setupData) {
            const loadingOverlay = document.getElementById('loadingOverlay');

            switch (provider.toLowerCase()) {
                case 'stripe':
                    await handleStripePayment(setupData);
                    break;

                case 'paypal':
                    await handlePayPalPayment(setupData);
                    break;

                case 'razorpay':
                    await handleRazorpayPayment(setupData);
                    break;

                default:
                    // For other providers, confirm payment directly
                    await confirmPayment(provider, setupData);
                    break;
            }
        }

        /**
         * Handle Stripe payment
         */
        async function handleStripePayment(setupData) {
            console.log('Processing Stripe payment...');

            // In production, you would:
            // 1. Load Stripe.js
            // 2. Create payment element
            // 3. Confirm payment with Stripe
            // 4. Then confirm with our backend

            // For now, simulate and confirm
            await confirmPayment('stripe', setupData);
        }

        /**
         * Handle PayPal payment
         */
        async function handlePayPalPayment(setupData) {
            console.log('Processing PayPal payment...');

            // PayPal typically redirects, so we'll redirect to the approval URL
            if (setupData.approval_url) {
                window.location.href = setupData.approval_url;
                return;
            }

            // Or confirm directly if no redirect needed
            await confirmPayment('paypal', setupData);
        }

        /**
         * Handle Razorpay payment
         */
        async function handleRazorpayPayment(setupData) {
            console.log('Processing Razorpay payment...');

            // In production, you would open Razorpay checkout modal
            // For now, confirm directly
            await confirmPayment('razorpay', setupData);
        }

        /**
         * Confirm payment with backend
         */
        async function confirmPayment(provider, setupData) {
            const loadingOverlay = document.getElementById('loadingOverlay');

            try {
                console.log('Confirming payment with backend...');

                const confirmResponse = await fetch(`${API_BASE_URL}/payment/${provider}/confirm`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        token: orderToken,
                        payment_intent_id: setupData.payment_intent_id || setupData.id,
                        ...setupData, // Include any additional data from setup
                    }),
                });

                const confirmData = await confirmResponse.json();

                if (confirmData.success) {
                    console.log('Payment confirmed successfully:', confirmData);

                    // Show success and redirect
                    showSuccess('Payment successful!');

                    setTimeout(() => {
                        window.location.href = `/payment/${provider}/success?token=${orderToken}`;
                    }, 1500);
                } else {
                    throw new Error(confirmData.message || 'Payment confirmation failed');
                }

            } catch (error) {
                console.error('Payment confirmation error:', error);
                loadingOverlay.classList.remove('show');
                showError(error.message || 'Payment confirmation failed');
            }
        }

        /**
         * Show success message
         */
        function showSuccess(message) {
            // Create a better success notification
            const alertDiv = document.createElement('div');
            alertDiv.className =
                'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
            alertDiv.style.zIndex = '10000';
            alertDiv.innerHTML = `
                <i class="fas fa-check-circle me-2"></i>
                <strong>Success!</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);

            // Also log to console
            console.log('✓ SUCCESS:', message);

            // Auto remove after 5 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        /**
         * Show error message
         */
        function showError(message) {
            // Create a better error notification
            const alertDiv = document.createElement('div');
            alertDiv.className =
                'alert alert-danger alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
            alertDiv.style.zIndex = '10000';
            alertDiv.innerHTML = `
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Error!</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);

            // Also log to console
            console.error('✗ ERROR:', message);

            // Auto remove after 8 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 8000);
        }

        /**
         * Show loading overlay
         */
        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('show');
        }

        /**
         * Hide loading overlay
         */
        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('show');
        }
    </script>
</body>

</html>
