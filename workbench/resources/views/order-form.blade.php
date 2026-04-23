<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Order Test - Create & Pay</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }

        .main-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }

        .btn-create {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .order-card {
            transition: transform 0.2s;
            cursor: pointer;
        }

        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 4px 12px;
        }

        .loading-spinner {
            display: none;
        }

        .loading .loading-spinner {
            display: inline-block;
        }

        .loading .btn-text {
            display: none;
        }
    </style>
</head>

<body>
    <div class="container main-container">
        <!-- Header -->
        <div class="text-center text-white mb-4">
            <h1 class="mb-2">
                <i class="fas fa-shopping-cart me-2"></i>
                Order Test Page
            </h1>
            <p class="lead">Create test orders and process payments</p>
        </div>

        <!-- Success Message -->
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Create Order Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-plus-circle me-2"></i>
                    Create Test Order
                </h4>
            </div>
            <div class="card-body">
                <form id="createOrderForm" action="{{ route('orders.create') }}" method="POST">
                    @csrf

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-dollar-sign me-1"></i>
                                Order Total
                            </label>
                            <input type="number" name="total" class="form-control" value="99.99" step="0.01"
                                min="0" required>
                            <small class="text-muted">Amount in {{ config('app.currency', 'USD') }}</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-shopping-bag me-1"></i>
                                Number of Items
                            </label>
                            <input type="number" name="items_count" class="form-control" value="3" min="1"
                                max="10" required>
                            <small class="text-muted">Line items in the order (1-10)</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-user me-1"></i>
                                Customer Name
                            </label>
                            <input type="text" name="customer_name" class="form-control" placeholder="John Doe">
                            <small class="text-muted">Leave empty for random name</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-envelope me-1"></i>
                                Customer Email
                            </label>
                            <input type="email" name="customer_email" class="form-control"
                                placeholder="customer@example.com">
                            <small class="text-muted">Leave empty for random email</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-info-circle me-1"></i>
                            Order Status
                        </label>
                        <select name="status" class="form-select">
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-credit-card me-1"></i>
                            Payment Status
                        </label>
                        <select name="payment_status" class="form-select">
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="paid">Paid</option>
                            <option value="failed">Failed</option>
                            <option value="refunded">Refunded</option>
                        </select>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="redirect_to_payment"
                            id="redirectToPayment" checked>
                        <label class="form-check-label" for="redirectToPayment">
                            <strong>Redirect to payment page after creation</strong>
                        </label>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-create btn-lg">
                            <span class="btn-text">
                                <i class="fas fa-magic me-2"></i>
                                Create Order & Go to Payment
                            </span>
                            <span class="loading-spinner">
                                <span class="spinner-border spinner-border-sm me-2" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </span>
                                Creating Order...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Recent Orders
                </h4>
            </div>
            <div class="card-body">
                @if (isset($orders) && $orders->count() > 0)
                    <div class="row">
                        @foreach ($orders as $order)
                            <div class="col-md-6 mb-3">
                                <div class="card order-card h-100"
                                    onclick="window.location.href='{{ route('payment', ['token' => $order->key]) }}'">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="mb-0">#{{ $order->order_number }}</h5>
                                            <span
                                                class="badge bg-{{ $order->payment_status === 'paid' ? 'success' : 'warning' }} status-badge">
                                                {{ ucfirst($order->payment_status) }}
                                            </span>
                                        </div>

                                        <p class="text-muted small mb-2">
                                            <i class="fas fa-calendar me-1"></i>
                                            {{ $order->created_at->format('M d, Y h:i A') }}
                                        </p>

                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="h4 mb-0 text-primary">
                                                {{ format_amount($order->grand_total ?? $order->total) }}
                                            </span>
                                            <span class="badge bg-secondary status-badge">
                                                {{ ucfirst($order->status) }}
                                            </span>
                                        </div>

                                        @php
                                            $billing = is_array($order->billing_address) ? $order->billing_address : [];
                                            $email = $billing['email'] ?? '';
                                            $firstName = $billing['first_name'] ?? '';
                                            $lastName = $billing['last_name'] ?? '';
                                        @endphp

                                        @if ($email || $firstName || $lastName)
                                            <p class="text-muted small mb-0 mt-2">
                                                <i class="fas fa-user me-1"></i>
                                                {{ $firstName }} {{ $lastName }}
                                            </p>
                                        @endif

                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-shopping-bag me-1"></i>
                                                {{ $order->line_items->count() }} item(s)
                                            </small>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-light">
                                        <small class="text-muted">
                                            <i class="fas fa-mouse-pointer me-1"></i>
                                            Click to view payment page
                                        </small>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No orders yet. Create your first test order above!</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Info Card -->
        <div class="card mt-4">
            <div class="card-body">
                <h6 class="mb-3">
                    <i class="fas fa-info-circle text-primary me-2"></i>
                    Quick Tips
                </h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Orders are created using Laravel factories with realistic data
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Each order gets a unique token for secure payment access
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Click any order card to view its payment page
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Payment status can be set to 'pending' to test the payment flow
                    </li>
                    <li>
                        <i class="fas fa-check text-success me-2"></i>
                        Use this page to test invoice emails and payment processing
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('createOrderForm').addEventListener('submit', function(e) {
            const button = this.querySelector('button[type="submit"]');
            button.classList.add('loading');
            button.disabled = true;
        });
    </script>
</body>

</html>
