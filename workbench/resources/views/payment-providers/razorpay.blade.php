<div class="payment-form">
    <form class="payment-provider-form" data-provider="razorpay">
        <h6 class="mb-3">Razorpay Payment</h6>

        <div class="text-center mb-4">
            <i class="fas fa-credit-card" style="font-size: 48px; color: #3395ff;"></i>
        </div>

        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            A secure payment window will open when you click "Pay Now".
        </div>

        <div class="mb-3">
            <h6>Accepted Payment Methods:</h6>
            <div class="d-flex flex-wrap gap-2">
                <span class="badge bg-secondary">
                    <i class="fas fa-credit-card me-1"></i>
                    Cards
                </span>
                <span class="badge bg-secondary">
                    <i class="fas fa-university me-1"></i>
                    Net Banking
                </span>
                <span class="badge bg-secondary">
                    <i class="fas fa-mobile-alt me-1"></i>
                    UPI
                </span>
                <span class="badge bg-secondary">
                    <i class="fas fa-wallet me-1"></i>
                    Wallets
                </span>
            </div>
        </div>

        @if (!empty($method['payment_instructions']))
            <div class="payment-instructions">
                <small>{{ $method['payment_instructions'] }}</small>
            </div>
        @endif

        <!-- Pay Button -->
        <div class="d-flex justify-content-center mt-4">
            <button type="submit" class="btn btn-primary btn-pay">
                <i class="fas fa-lock me-2"></i>
                Pay Now with Razorpay
            </button>
        </div>

        <div class="text-center mt-3">
            <span class="security-badge">
                <i class="fas fa-shield-alt"></i>
                Secured by Razorpay
            </span>
        </div>
    </form>
</div>
