<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workbench Test Pages</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .main-container {
            max-width: 800px;
            width: 100%;
        }

        .logo {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo h1 {
            color: white;
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .logo p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.2rem;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3);
        }

        .card-body {
            padding: 30px;
        }

        .test-link {
            display: block;
            padding: 25px;
            background: white;
            border-radius: 12px;
            text-decoration: none;
            color: inherit;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .test-link:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: white;
            transform: translateX(10px);
        }

        .test-link:hover .icon {
            transform: scale(1.2);
        }

        .test-link .icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            transition: transform 0.3s ease;
        }

        .test-link h4 {
            margin-bottom: 10px;
            font-weight: 600;
        }

        .test-link p {
            margin-bottom: 0;
            opacity: 0.8;
            font-size: 0.95rem;
        }

        .badge-new {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 10px;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }

        .footer a {
            color: white;
            text-decoration: none;
            font-weight: 600;
        }

        .footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="main-container">
        <!-- Logo & Title -->
        <div class="logo">
            <h1>
                <i class="fas fa-flask"></i>
                Workbench
            </h1>
            <p>Foundry Package Testing Environment</p>
        </div>

        <!-- Test Pages Card -->
        <div class="card">
            <div class="card-body">
                <h3 class="mb-4">
                    <i class="fas fa-vial me-2"></i>
                    Available Test Pages
                </h3>

                <!-- Order Test Page -->
                <a href="{{ route('order-form') }}" class="test-link">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="icon">
                                <i class="fas fa-shopping-cart text-primary"></i>
                            </div>
                        </div>
                        <div class="col">
                            <h4>
                                Order Test Page
                                <span class="badge-new">NEW</span>
                            </h4>
                            <p>
                                Create test orders with line items, billing details, and process payments through
                                various payment gateways.
                            </p>
                            <small class="text-muted">
                                <i class="fas fa-check-circle me-1"></i>
                                Order creation
                                <i class="fas fa-check-circle ms-3 me-1"></i>
                                Payment processing
                                <i class="fas fa-check-circle ms-3 me-1"></i>
                                Invoice generation
                            </small>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                </a>

                <!-- Shop Page -->
                <a href="{{ route('shop.index') }}" class="test-link">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="icon">
                                <i class="fas fa-store text-info"></i>
                            </div>
                        </div>
                        <div class="col">
                            <h4>
                                Shop Page
                                <span class="badge-new">NEW</span>
                            </h4>
                            <p>
                                Browse products and test add-to-cart functionality with session persistence.
                            </p>
                            <small class="text-muted">
                                <i class="fas fa-check-circle me-1"></i>
                                Product listing
                                <i class="fas fa-check-circle ms-3 me-1"></i>
                                Details
                                <i class="fas fa-check-circle ms-3 me-1"></i>
                                AJAX Cart
                            </small>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                </a>

                <!-- Cart Test Page -->
                <a href="/cart" class="test-link">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="icon">
                                <i class="fas fa-shopping-basket text-success"></i>
                            </div>
                        </div>
                        <div class="col">
                            <h4>Cart Test Page</h4>
                            <p>
                                Test shopping cart functionality including add to cart, update quantities, apply
                                discounts, and checkout flow.
                            </p>
                            <small class="text-muted">
                                <i class="fas fa-check-circle me-1"></i>
                                Cart operations
                                <i class="fas fa-check-circle ms-3 me-1"></i>
                                Discount codes
                                <i class="fas fa-check-circle ms-3 me-1"></i>
                                Tax calculation
                            </small>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                </a>

                <!-- Payment Page -->
                <a href="{{ route('payment') }}" class="test-link">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="icon">
                                <i class="fas fa-credit-card text-warning"></i>
                            </div>
                        </div>
                        <div class="col">
                            <h4>Payment Page (Standalone)</h4>
                            <p>
                                View payment methods and test payment gateway integrations without creating an order
                                first.
                            </p>
                            <small class="text-muted">
                                <i class="fas fa-check-circle me-1"></i>
                                Multiple gateways
                                <i class="fas fa-check-circle ms-3 me-1"></i>
                                Test mode
                                <i class="fas fa-check-circle ms-3 me-1"></i>
                                API-driven
                            </small>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p class="mb-2">
                <i class="fas fa-code me-1"></i>
                <strong>coders-tm/foundry</strong> Package
            </p>
            <p class="mb-0">
                <a href="https://github.com/coders-tm/foundry" target="_blank">
                    <i class="fab fa-github me-1"></i>
                    View on GitHub
                </a>
                <span class="mx-2">•</span>
                <a href="https://foundry.netlify.com" target="_blank">
                    <i class="fas fa-book me-1"></i>
                    Documentation
                </a>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
