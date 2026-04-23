<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Shop - Workbench')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            color: white !important;
            font-weight: 700;
            font-size: 1.5rem;
        }
        .cart-icon {
            color: white;
            font-size: 1.2rem;
            position: relative;
            text-decoration: none;
            cursor: pointer;
        }
        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        .product-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
            background: white;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        .product-img {
            height: 200px;
            object-fit: cover;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
            font-size: 3rem;
        }
        .card-body {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
        }
        .product-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: #343a40;
        }
        .product-price {
            font-weight: 700;
            color: #667eea;
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }
        .btn-add-cart {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-weight: 600;
            width: 100%;
            padding: 0.6rem;
            border-radius: 8px;
            margin-top: auto;
            transition: opacity 0.2s;
        }
        .btn-add-cart:hover {
            opacity: 0.9;
            color: white;
        }
        .btn-add-cart:disabled {
            background: #adb5bd;
            opacity: 1;
        }
    </style>
    @stack('styles')
</head>
<body>

    <!-- Header -->
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}">
                <i class="fas fa-flask me-2"></i> Workbench Shop
            </a>
            <div class="d-flex align-items-center">
                <a href="{{ url('/cart') }}" class="cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-count" id="cart-count">
                        {{ $cart->count ?? 0 }}
                    </span>
                </a>
            </div>
        </div>
    </nav>

    @yield('content')

    <!-- Toast -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="cartToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto text-success"><i class="fas fa-check-circle"></i> Success</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                Item added to cart successfully!
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const cartCountEl = document.getElementById('cart-count');
            const toastEl = document.getElementById('cartToast');
            const toast = new bootstrap.Toast(toastEl);

            document.querySelectorAll('.add-to-cart-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.getAttribute('data-product-id');
                    const variantId = this.getAttribute('data-variant-id');
                    const hasVariants = this.getAttribute('data-has-variants') === 'true';

                    if (!variantId) {
                        alert('Product unavailable (no variant found)');
                        return;
                    }

                    // For simplicity in this demo, we auto-select the default variant or first variant
                    // In a real app, successful handling of variants would require a modal or separate page

                    const btn = this;
                    const originalText = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Adding...';

                    fetch('{{ route("cart.add") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            product_id: productId,
                            variant_id: variantId,
                            quantity: 1
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                         // API returns { data: [...], message: "..." }
                        if (data.data) {
                            // Calculate total quantity from data array
                            const count = data.data.reduce((acc, item) => acc + item.quantity, 0);
                            cartCountEl.textContent = count;
                            toast.show();
                        } else {
                            alert(data.message || 'Error adding to cart');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Something went wrong');
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    });
                });
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
