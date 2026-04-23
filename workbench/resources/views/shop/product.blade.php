@extends('layouts.shop')

@section('content')
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('shop.index') }}">Shop</a></li>
            @if($product->category)
                <li class="breadcrumb-item"><a href="{{ route('shop.index', ['category' => $product->category->slug]) }}">{{ $product->category->name }}</a></li>
            @endif
            <li class="breadcrumb-item active" aria-current="page">{{ $product->title }}</li>
        </ol>
    </nav>

    <div class="row g-5">
        <!-- Product Images -->
        <div class="col-md-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    @if(isset($product->media) && count($product->media) > 0)
                        <div id="productCarousel" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-inner">
                                @foreach($product->media as $index => $media)
                                    <div class="carousel-item {{ $index === 0 ? 'active' : '' }}">
                                        <img src="{{ $media->url }}" class="d-block w-100 rounded" alt="Product Image" style="max-height: 500px; object-fit: contain; background: #f8f9fa;">
                                    </div>
                                @endforeach
                            </div>
                            @if(count($product->media) > 1)
                                <button class="carousel-control-prev" type="button" data-bs-target="#productCarousel" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Previous</span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#productCarousel" data-bs-slide="next">
                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Next</span>
                                </button>
                            @endif
                        </div>
                        <div class="row g-2 mt-2">
                            @foreach($product->media as $index => $media)
                                <div class="col-2">
                                    <img src="{{ $media->url }}"
                                         class="img-thumbnail cursor-pointer"
                                         style="width: 100%; height: 60px; object-fit: cover;"
                                         onclick="var myCarousel = document.getElementById('productCarousel'); var carousel = new bootstrap.Carousel(myCarousel); carousel.to({{ $index }});">
                                </div>
                            @endforeach
                        </div>
                    @elseif($product->thumbnail)
                         <img src="{{ $product->thumbnail->url }}" class="img-fluid rounded" alt="{{ $product->title }}">
                    @else
                        <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 400px;">
                            <i class="fas fa-box-open fa-3x text-muted"></i>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Product Details -->
        <div class="col-md-5">
            <div class="ps-md-4">
                <h1 class="h2 fw-bold mb-3">{{ $product->title }}</h1>

                <div class="d-flex align-items-center mb-4">
                    <h2 class="text-primary fw-bold mb-0" id="product-price">
                        {{ $product->price_formatted }}
                    </h2>
                    @if(isset($product->compare_at_price) && $product->compare_at_price > $product->price)
                        <span class="text-decoration-line-through text-muted ms-3 fs-5" id="compare-price">
                             {{ $product->compare_at_price_formatted }}
                        </span>
                    @endif
                </div>

                <div class="mb-4">
                    <p class="text-muted">{{ $product->short_description ?? 'No description available' }}</p>
                </div>

                <form id="add-to-cart-form" action="{{ route('shop.add-to-cart') }}" method="POST">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <!-- Variant Selector -->
                    @if(isset($product->options) && count($product->options) > 0)
                        <div class="mb-4">
                            @foreach($product->options as $option)
                                <div class="mb-3">
                                    <label class="form-label fw-bold">{{ $option->name }}</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        @php
                                            $type = $option->type ?? 'select';
                                        @endphp

                                        @if($type === 'color' || $type === 'button')
                                            @foreach($option->values as $value)
                                                <div class="form-check p-0">
                                                    <input type="radio"
                                                           class="btn-check variant-option"
                                                           name="option[{{ $option->id }}]"
                                                           id="opt_{{ $option->id }}_{{ $loop->index }}"
                                                           value="{{ $value->name }}"
                                                           data-option-name="{{ $option->name }}"
                                                           autocomplete="off"
                                                           {{ (isset($option->value) && $option->value === $value->name) ? 'checked' : ($loop->first ? 'checked' : '') }}>

                                                    @if($type === 'color')
                                                        <label class="btn rounded-circle p-0 border border-2 d-flex align-items-center justify-content-center"
                                                               for="opt_{{ $option->id }}_{{ $loop->index }}"
                                                               style="width: 32px; height: 32px; background-color: {{ $value->color ?? $value->name }};"
                                                               title="{{ $value->name }}">
                                                        </label>
                                                    @else
                                                        <label class="btn btn-outline-secondary" for="opt_{{ $option->id }}_{{ $loop->index }}">
                                                            {{ $value->name }}
                                                        </label>
                                                    @endif
                                                </div>
                                            @endforeach
                                        @else
                                            <select class="form-select variant-select" name="option[{{ $option->id }}]" data-option-name="{{ $option->name }}">
                                                @foreach($option->values as $value)
                                                    <option value="{{ $value->name }}" {{ (isset($option->value) && $option->value === $value->name) ? 'selected' : '' }}>
                                                        {{ $value->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <input type="hidden" name="variant_id" id="variant_id" value="{{ $product->variant_id ?? '' }}">

                    <!-- Recurring Plans -->
                    @if(isset($product->recurring_plans) && count($product->recurring_plans) > 0)
                        <div class="mb-4">
                            <label class="form-label fw-bold">Select Plan</label>
                            <div class="d-flex flex-column gap-2">
                                @foreach($product->recurring_plans as $plan)
                                    <div class="form-check p-3 border rounded">
                                        <input class="form-check-input" type="radio" name="plan_id" id="plan_{{ $plan->id }}" value="{{ $plan->id }}"
                                            {{ (isset($product->plan) && $product->plan->id == $plan->id) ? 'checked' : '' }}
                                            onchange="updatePrice('{{ $plan->price_formatted }}')">
                                        <label class="form-check-label d-flex justify-content-between w-100" for="plan_{{ $plan->id }}">
                                            <span>
                                                {{ $plan->name }}
                                                <small class="text-muted d-block">{{ $plan->description ?? $plan->interval }}</small>
                                            </span>
                                            <span class="fw-bold">{{ $plan->price_formatted }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Add to Cart -->
                    <div class="d-flex gap-2 mb-4">
                        <input type="number" name="quantity" value="1" min="1" class="form-control" style="width: 80px;">
                        <button type="submit" class="btn btn-primary flex-grow-1" {{ !$product->in_stock ? 'disabled' : '' }}>
                            <i class="fas fa-cart-plus me-2"></i> {{ $product->in_stock ? 'Add to Cart' : 'Out of Stock' }}
                        </button>
                    </div>
                </form>

                <div class="card bg-light border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-truck text-muted me-3"></i>
                            <span>Free shipping on orders over $100</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-shield-alt text-muted me-3"></i>
                            <span>2 year warranty</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="row mt-5">
        <div class="col-12">
            <ul class="nav nav-tabs" id="productTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="description-tab" data-bs-toggle="tab" href="#description" role="tab">Description</a>
                </li>
            </ul>
            <div class="tab-content p-4 border border-top-0 rounded-bottom bg-white" id="productTabsContent">
                <div class="tab-pane fade show active" id="description" role="tabpanel">
                    {!! $product->description ?? 'No Description' !!}
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.productVariants = @json($product->variants ?? []);

    $(document).ready(function() {
        function findVariant() {
            var selectedOptions = [];

            // Collect values from radio buttons
            $('.variant-option:checked').each(function() {
                selectedOptions.push($(this).val());
            });

            // Collect values from selects
            $('.variant-select').each(function() {
                selectedOptions.push($(this).val());
            });

            var variantTitle = selectedOptions.join(' / ');

            return window.productVariants.find(function(variant) {
                return variant.title === variantTitle;
            });
        }

        function updateState() {
            var variant = findVariant();
            var $submitBtn = $('button[type="submit"]');

            if (variant) {
                // Update basic info
                $('#variant_id').val(variant.id);
                $('#product-price').text(variant.price_formatted);

                // Update Compare At Price
                if (variant.compare_at_price && variant.compare_at_price > variant.price) {
                    var compareHtml = variant.compare_at_price_formatted;
                    if ($('#compare-price').length) {
                        $('#compare-price').text(compareHtml).show();
                    } else {
                        $('<span class="text-decoration-line-through text-muted ms-3 fs-5" id="compare-price">' + compareHtml + '</span>').insertAfter('#product-price');
                    }
                } else {
                    $('#compare-price').hide();
                }

                // Update Stock Status
                if (variant.in_stock) {
                    $submitBtn.prop('disabled', false).html('<i class="fas fa-cart-plus me-2"></i> Add to Cart');
                } else {
                    $submitBtn.prop('disabled', true).html('<i class="fas fa-times me-2"></i> Out of Stock');
                }

                // Handling image switching if variant has thumbnail
                if (variant.thumbnail) {
                    var newSrc = variant.thumbnail.url;
                    // Find if any carousel item has this image
                    var $existingImg = $('.carousel-item img[src="' + newSrc + '"]');
                    if ($existingImg.length > 0) {
                        var index = $existingImg.closest('.carousel-item').index();
                        var myCarousel = document.getElementById('productCarousel');
                        var carousel = bootstrap.Carousel.getInstance(myCarousel);
                        if (!carousel) carousel = new bootstrap.Carousel(myCarousel);
                        carousel.to(index);
                    } else {
                         // Fallback or just ignore if image not in carousel
                    }
                }

            } else {
                // Variant not found / Unavailable
                $submitBtn.prop('disabled', true).html('<i class="fas fa-ban me-2"></i> Unavailable');
                $('#product-price').text('Unavailable');
            }
        }

        // Event Listeners
        $('.variant-option, .variant-select').on('change', updateState);

        // Initial check
        // Ensure options are selected on load (Vue logic auto-selects first if match)
        // Here we rely on Blade 'checked' logic, but we run updateState once to sync JS
        // but wait, blade rendering might pick a combination that doesn't exist?
        // Usually default_variant logic in controller handles the initial valid state.
        updateState();
    });

    function updatePrice(price) {
        document.getElementById('product-price').innerText = price;
    }
</script>
@endsection
