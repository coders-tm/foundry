@extends('layouts.shop')

@section('content')
    <!-- Main Content -->
    <div class="container mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Products</h2>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="row g-4">
            @forelse($products as $product)
                <div class="col-md-3">
                    <a class="text-decoration-none" href="{{ route('shop.product', $product->slug) }}">
                        <div class="card product-card">
                            <div class="product-img">
                                @if($product->thumbnail)
                                    <img src="{{ $product->thumbnail->url }}" alt="{{ $product->title }}" style="width:100%; height:100%; object-fit:cover; border-radius:12px 12px 0 0;">
                                @else
                                    <i class="fas fa-box-open"></i>
                                @endif
                            </div>
                            <div class="card-body">
                                <h5 class="product-title">{{ $product->title }}</h5>
                                <div class="product-price">
                                    {{ $product->price }}
                                </div>

                                <button class="btn btn-add-cart add-to-cart-btn"
                                        data-product-id="{{ $product->id }}"
                                        data-variant-id="{{ $product->default_variant?->id ?? $product->variants->first()?->id }}"
                                        data-has-variants="{{ $product->has_variant ? 'true' : 'false' }}">
                                    <i class="fas fa-cart-plus me-2"></i> Add to Cart
                                </button>
                            </div>
                        </div>
                    </a>
                </div>
            @empty
                <div class="col-12 text-center py-5">
                    <div class="display-1 text-muted mb-3"><i class="fas fa-search"></i></div>
                    <h3>No products found</h3>
                    <p class="text-muted">Try adding some products to the database.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $products->links() }}
        </div>
    </div>
@endsection

