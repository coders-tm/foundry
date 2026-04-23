<?php

namespace Tests\Feature;

use Foundry\Models\ExchangeRate;
use Foundry\Models\Order;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;
use Foundry\Models\User;
use Foundry\Payment\Payable;
use Foundry\Tests\Feature\FeatureTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;

class PaymentCurrencyHandlingTest extends FeatureTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup currencies (Base: USD)
        Config::set('app.currency', 'USD');

        // Create payment method
        PaymentMethod::create([
            'name' => 'Stripe',
            'provider' => 'stripe',
            'active' => true,
        ]);
    }

    #[Test]
    public function it_stores_correct_gateway_amount_and_currency_in_payment_metadata()
    {
        // Create Exchange Rate: 1 USD = 0.85 EUR
        ExchangeRate::updateOrCreate(
            ['currency' => 'EUR'],
            ['rate' => 0.85]
        );

        // Create User
        $user = User::factory()->create();

        // Create Order (100 USD) with EUR billing address
        $order = Order::factory()->create([
            'customer_id' => $user->id,
            'grand_total' => 100.00,
            'billing_address' => [
                'country' => 'Germany',
                'country_code' => 'DE', // Germany uses EUR
            ],
        ]);

        // Verify Payable conversion
        $payable = Payable::fromOrder($order);

        // Assert Payable logic - should detect EUR from billing address country
        $this->assertEquals('EUR', $payable->getCurrency());
        // 100 USD * 0.85 = 85.00 EUR
        $this->assertEquals(85.00, $payable->getGatewayAmount());

        // Simulate Payment Creation (as done in PaymentController/Order)
        // Create a Payment record mimicking the result of a processor
        $payment = Payment::createForOrder($order, [
            'payment_method_id' => PaymentMethod::where('provider', 'stripe')->first()->id,
            'transaction_id' => 'tx_123456',
            'amount' => $payable->getGrandTotal(), // Base Amount (100)
            'status' => 'completed',
            'metadata' => [
                'gateway_amount' => $payable->getGatewayAmount(),
                'gateway_currency' => $payable->getCurrency(),
            ],
        ]);

        // Assert Payment Record
        $this->assertEquals(100.00, $payment->amount, 'Payment amount should be in Base Currency');
        $this->assertEquals(85.00, $payment->metadata['gateway_amount'], 'Metadata should store Gateway Amount');

        // Assert Order Paid Total is updated (in Base Currency)
        $order->refresh();
        $this->assertEquals(100.00, $order->paid_total);
    }
}
