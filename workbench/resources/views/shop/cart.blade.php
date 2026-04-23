<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart - Workbench</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; min-height: 100vh; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 1rem 0; margin-bottom: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .navbar-brand { color: white !important; font-weight: 700; font-size: 1.5rem; }
        .cart-item { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 1rem; padding: 1.5rem; transition: transform 0.2s; }
        .cart-item:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .cart-summary { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); position: sticky; top: 2rem; }
        .quantity-input { width: 80px; text-align: center; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}"><i class="fas fa-flask me-2"></i> Workbench Shop</a>
            <a href="{{ route('shop.index') }}" class="btn btn-outline-light"><i class="fas fa-arrow-left me-2"></i> Continue Shopping</a>
        </div>
    </nav>
    <div class="container mb-5">
        <h2 class="mb-4">Shopping Cart</h2>
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        @endif
        <div class="row" id="cart-container">
            @if(isset($cart) && $cart->line_items->count() > 0)
                <div class="col-lg-8">
                    @foreach($cart->line_items as $item)
                        <div class="cart-item d-flex align-items-center" id="item-{{ $item->id }}">
                            <div class="flex-grow-1">
                                <h5 class="mb-1">{{ $item->title }}</h5>
                                <div class="text-muted small">
                                    Price: {{ format_amount($item->price) }}
                                    @if($item->variant_id) | Variant: {{ $item->variant->title ?? 'Default' }} @endif
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <input type="number" class="form-control quantity-input me-3"
                                       value="{{ $item->quantity }}" min="1"
                                       onchange="updateCartItem('{{ $item->id }}', this.value)">
                                <div class="fw-bold me-4" style="min-width: 80px; text-align: right;">
                                    {{ format_amount($item->total) }}
                                </div>
                                <button class="btn btn-outline-danger btn-sm" onclick="removeCartItem('{{ $item->id }}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="col-lg-4">
                    <div class="cart-summary">
                        <h4 class="mb-4">Summary</h4>
                        <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><span>{{ format_amount($cart->sub_total) }}</span></div>
                        <div class="d-flex justify-content-between mb-2"><span>Tax</span><span>{{ format_amount($cart->tax_total) }}</span></div>
                        <div class="d-flex justify-content-between mb-3"><span>Shipping</span><span>{{ format_amount($cart->shipping_total) }}</span></div>
                        <hr>
                        <div class="d-flex justify-content-between mb-4"><span class="h5">Total</span><span class="h5 text-primary">{{ format_amount($cart->grand_total) }}</span></div>
                        <button class="btn btn-primary w-100 py-2 fw-bold">Proceed to Checkout</button>
                    </div>
                </div>
            @else
                <div class="col-12 text-center py-5">
                    <div class="display-1 text-muted mb-3"><i class="fas fa-shopping-cart"></i></div>
                    <h3>Your cart is empty</h3>
                    <p class="text-muted">Looks like you haven't added any items yet.</p>
                    <a href="{{ route('shop.index') }}" class="btn btn-primary mt-3">Start Shopping</a>
                </div>
            @endif
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        function updateCartItem(itemId, quantity) {
            fetch('{{ route("cart.update", ["itemId" => ":itemId"]) }}'.replace(':itemId', itemId), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ quantity: quantity })
            })
            .then(res => res.json())
            .then(data => {
                location.reload();
            })
            .catch(err => console.error(err));
        }
        function removeCartItem(itemId) {
            if(!confirm('Are you sure you want to remove this item?')) return;
            fetch('{{ route("cart.remove", ["itemId" => ":itemId"]) }}'.replace(':itemId', itemId), {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                location.reload();
            })
            .catch(err => console.error(err));
        }
    </script>
</body>
</html>
