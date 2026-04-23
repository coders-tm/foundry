<div class="payment-form">
    <form class="payment-provider-form" data-provider="manual">
        <h6 class="mb-3">Manual Payment / Bank Transfer</h6>

        @if (!empty($method['payment_instructions']))
            <div class="alert alert-primary">
                <h6 class="alert-heading">
                    <i class="fas fa-info-circle me-2"></i>
                    Payment Instructions
                </h6>
                <div>{!! nl2br(e($method['payment_instructions'])) !!}</div>
            </div>
        @else
            <div class="alert alert-info">
                <i class="fas fa-university me-2"></i>
                Please transfer the payment to the provided bank details. Your order will be processed once payment is
                confirmed.
            </div>
        @endif

        <!-- Reference Number -->
        <div class="card bg-light mb-3">
            <div class="card-body">
                <h6 class="card-title">Reference Number</h6>
                <div class="d-flex align-items-center justify-content-between">
                    <code class="fs-5" id="referenceNumber">{{ 'REF-' . strtoupper(uniqid()) }}</code>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="copyReference()">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
                <small class="text-muted">Use this reference number in your payment</small>
            </div>
        </div>

        <!-- Payment Notes -->
        <div class="mb-3">
            <label class="form-label">Payment Notes (Optional)</label>
            <textarea class="form-control" rows="3" placeholder="Add any notes or transaction details..."></textarea>
        </div>

        <!-- Important Notes -->
        <div class="alert alert-warning">
            <small>
                <strong><i class="fas fa-exclamation-triangle me-1"></i> Important:</strong>
                <ul class="mb-0 mt-2 ps-3">
                    <li>Include the reference number in your payment</li>
                    <li>Your order will be on hold until payment is verified</li>
                    <li>Processing may take 1-3 business days</li>
                </ul>
            </small>
        </div>

        <!-- Complete Order Button -->
        <div class="d-flex justify-content-center mt-4">
            <button type="submit" class="btn btn-primary btn-pay">
                <i class="fas fa-check me-2"></i>
                Complete Order
            </button>
        </div>
    </form>
</div>

<script>
    function copyReference() {
        const refNumber = document.getElementById('referenceNumber').textContent;
        navigator.clipboard.writeText(refNumber).then(() => {
            alert('Reference number copied to clipboard!');
        }).catch(err => {
            console.error('Failed to copy:', err);
        });
    }
</script>
