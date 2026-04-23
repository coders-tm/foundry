<div class="payment-form">
    <form class="payment-provider-form" data-provider="{{ $method['provider'] }}">
        <h6 class="mb-3">{{ $method['label'] ?? $method['name'] }}</h6>

        <div class="text-center mb-4">
            @if (!empty($method['logo']) && (str_starts_with($method['logo'], 'http') || str_starts_with($method['logo'], '/')))
                <img src="{{ $method['logo'] }}" alt="{{ $method['name'] }}" style="max-width: 120px; max-height: 60px;">
            @else
                <i class="fas fa-credit-card" style="font-size: 48px; color: #666;"></i>
            @endif
        </div>

        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            You will be redirected to complete your payment securely with {{ $method['name'] }}.
        </div>

        @if (!empty($method['payment_instructions']))
            <div class="payment-instructions">
                <small>{{ $method['payment_instructions'] }}</small>
            </div>
        @endif

        @if (!empty($method['methods']) && is_array($method['methods']))
            <div class="mb-3">
                <h6>Supported Payment Methods:</h6>
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($method['methods'] as $paymentMethod)
                        <span class="badge bg-secondary">
                            {{ ucfirst(str_replace('_', ' ', $paymentMethod)) }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Pay Button -->
        <div class="d-flex justify-content-center mt-4">
            <button type="submit" class="btn btn-primary btn-pay">
                <i class="fas fa-lock me-2"></i>
                Pay with {{ $method['name'] }}
            </button>
        </div>

        <div class="text-center mt-3">
            <span class="security-badge">
                <i class="fas fa-shield-alt"></i>
                Secure Payment
            </span>
        </div>
    </form>
</div>
