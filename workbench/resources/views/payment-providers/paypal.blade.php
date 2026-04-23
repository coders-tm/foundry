<div class="payment-form">
    <form class="payment-provider-form" data-provider="paypal">
        <h6 class="mb-3">PayPal Payment</h6>

        <div class="text-center mb-4">
            <i class="fab fa-paypal" style="font-size: 48px; color: #0070ba;"></i>
        </div>

        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            You will be redirected to PayPal to complete your payment securely.
        </div>

        @if (!empty($method['payment_instructions']))
            <div class="payment-instructions">
                <small>{{ $method['payment_instructions'] }}</small>
            </div>
        @endif

        <!-- Pay Button -->
        <div class="d-flex justify-content-center mt-4">
            <button type="submit" class="btn btn-primary btn-pay"
                style="background-color: #0070ba; border-color: #0070ba;">
                <i class="fab fa-paypal me-2"></i>
                Pay with PayPal
            </button>
        </div>

        <div class="text-center mt-3">
            <span class="security-badge">
                <i class="fas fa-shield-alt"></i>
                Secured by PayPal
            </span>
        </div>
    </form>
</div>
