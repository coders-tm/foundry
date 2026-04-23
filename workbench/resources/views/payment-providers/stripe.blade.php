<div class="payment-form">
    <form class="payment-provider-form" data-provider="stripe">
        <h6 class="mb-3">Stripe Payment</h6>

        <!-- Card Number -->
        <div class="mb-3">
            <label class="form-label">Card Number</label>
            <input type="text" class="form-control" placeholder="4242 4242 4242 4242" maxlength="19" required>
        </div>

        <!-- Expiry and CVC -->
        <div class="row">
            <div class="col-6">
                <div class="mb-3">
                    <label class="form-label">Expiry Date</label>
                    <input type="text" class="form-control" placeholder="MM/YY" maxlength="5" required>
                </div>
            </div>
            <div class="col-6">
                <div class="mb-3">
                    <label class="form-label">CVC</label>
                    <input type="text" class="form-control" placeholder="123" maxlength="4" required>
                </div>
            </div>
        </div>

        <!-- Test Card Info -->
        <div class="test-card-info">
            <small>
                <strong><i class="fas fa-info-circle"></i> Test Card:</strong> 4242 4242 4242 4242 (Any future date, Any
                CVC)
            </small>
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
                Pay Now with Stripe
            </button>
        </div>

        <div class="text-center mt-3">
            <span class="security-badge">
                <i class="fas fa-shield-alt"></i>
                Secured by Stripe
            </span>
        </div>
    </form>
</div>
