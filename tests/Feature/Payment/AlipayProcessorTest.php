<?php

namespace Tests\Feature\Payment;

use Foundry\Foundry;
use Foundry\Models\ExchangeRate;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;
use Foundry\Payment\Mappers\AlipayPayment;
use Foundry\Payment\Payable;
use Foundry\Payment\Processor;
use Foundry\Payment\Processors\AlipayProcessor;
use Foundry\Tests\Feature\FeatureTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class AlipayProcessorTest extends FeatureTestCase
{
    use RefreshDatabase;

    protected PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        // Create CNY exchange rate
        ExchangeRate::create(['currency' => 'CNY', 'rate' => 7.0]);

        // Create Alipay payment method
        $this->paymentMethod = PaymentMethod::create([
            'name' => 'Alipay',
            'provider' => 'alipay',
            'integration_via' => 'stripe',
            'active' => true,
        ]);

        $this->paymentMethod->credentials = collect([
            ['key' => 'API_KEY', 'value' => 'pk_test_123', 'publish' => true],
            ['key' => 'API_SECRET', 'value' => 'sk_test_123', 'publish' => false],
        ]);
        $this->paymentMethod->save();
        PaymentMethod::updateProviderCache(PaymentMethod::ALIPAY);
    }

    protected function mockAlipayGateway($mock)
    {
        $reflection = new \ReflectionClass(Foundry::class);
        $property = $reflection->getProperty('alipayClient');
        $property->setAccessible(true);
        $property->setValue(null, $mock);
    }

    protected function resetAlipayGateway()
    {
        $reflection = new \ReflectionClass(Foundry::class);
        $property = $reflection->getProperty('alipayClient');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    protected function tearDown(): void
    {
        $this->resetAlipayGateway();
        parent::tearDown();
    }

    protected function createMockPayable()
    {
        return Payable::make([
            'reference_id' => '12345',
            'grand_total' => 100.00,
            'currency' => 'CNY',
            'billing_address' => [
                'country_code' => 'CN',
                'country' => 'China',
            ],
            'description' => 'Test Payment',
            'source' => (object) ['id' => 1],
        ]);
    }

    #[Test]
    public function it_creates_alipay_processor_instance()
    {
        $processor = Processor::make('alipay');

        $this->assertInstanceOf(AlipayProcessor::class, $processor);
        $this->assertEquals('alipay', $processor->getProvider());
    }

    #[Test]
    public function it_sets_up_alipay_payment_intent()
    {
        $processor = new AlipayProcessor;
        $processor->setPaymentMethod($this->paymentMethod);
        $payable = $this->createMockPayable();

        $alipayMock = Mockery::mock('Yansongda\Pay\Gateways\Alipay');

        // Mock RedirectResponse
        $redirectMock = Mockery::mock('Symfony\Component\HttpFoundation\RedirectResponse');
        $redirectMock->shouldReceive('getTargetUrl')->andReturn('https://alipay.com/pay');

        $alipayMock->shouldReceive('web')
            ->once()
            ->with(Mockery::on(function ($args) {
                return $args['out_trade_no'] === '12345' &&
                    $args['total_amount'] === '700.00' &&
                    strpos($args['_return_url'], 'state=') !== false;
            }))
            ->andReturn($redirectMock);

        $this->mockAlipayGateway($alipayMock);

        $result = $processor->setupPaymentIntent(new Request, $payable);

        $this->assertEquals('https://alipay.com/pay', $result['redirect_url']);
        $this->assertEquals('12345', $result['payment_intent_id']);
        $this->assertArrayHasKey('state_id', $result);

        // Verify payment record exists
        $this->assertDatabaseHas('payments', [
            'uuid' => $result['state_id'],
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function it_confirms_alipay_payment()
    {
        $processor = Processor::make('alipay');
        $processor->setPaymentMethod($this->paymentMethod);

        $payable = Payable::make(['grand_total' => 100.00]);
        $request = new Request;

        $alipayMock = Mockery::mock('Yansongda\Pay\Gateways\Alipay');

        // Mock the response as a Collection-like object or array
        $response = [
            'trade_no' => 'alipay_123',
            'out_trade_no' => 'order_123',
            'total_amount' => '100.00',
            'trade_status' => 'TRADE_SUCCESS',
            'fund_bill_list' => [
                ['fund_channel' => 'PCREDIT', 'amount' => '100.00'],
            ],
        ];

        $alipayMock->shouldReceive('verify')
            ->once()
            ->andReturn($response);

        $this->mockAlipayGateway($alipayMock);

        $result = $processor->confirmPayment($request, $payable);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('alipay_123', $result->getTransactionId());
        $this->assertInstanceOf(AlipayPayment::class, $result->getPaymentData());
        $this->assertEquals('Ant Credit Pay (Huabei)', $result->getPaymentData()->toString());
    }

    #[Test]
    public function it_handles_alipay_success_callback()
    {
        $processor = new AlipayProcessor;
        $processor->setPaymentMethod($this->paymentMethod);

        $payment = Payment::create([
            'paymentable_type' => 'App\Models\Order',
            'paymentable_id' => 1,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_id' => 'pending_123',
            'amount' => 100.00,
            'status' => 'pending',
        ]);

        $request = new Request(['state' => $payment->uuid]);

        $alipayMock = Mockery::mock('Yansongda\Pay\Gateways\Alipay');
        $alipayMock->shouldReceive('verify')->once()->andReturn([
            'trade_no' => 'alipay_123',
            'out_trade_no' => '12345',
            'total_amount' => '100.00',
            'trade_status' => 'TRADE_SUCCESS',
        ]);

        $this->mockAlipayGateway($alipayMock);

        $result = $processor->handleSuccessCallback($request);

        $this->assertEquals('success', $result->getMessageType());
        $this->assertEquals('Alipay payment was successful.', $result->getMessage());

        // Verify payment was updated
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'transaction_id' => 'alipay_123',
            'status' => 'completed',
        ]);
    }

    #[Test]
    public function it_handles_alipay_cancel_callback()
    {
        $processor = new AlipayProcessor;
        $processor->setPaymentMethod($this->paymentMethod);

        $payment = Payment::create([
            'paymentable_type' => 'App\Models\Order',
            'paymentable_id' => 1,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_id' => 'pending_123',
            'amount' => 100.00,
            'status' => 'pending',
        ]);

        $request = new Request(['state' => $payment->uuid]);

        $result = $processor->handleCancelCallback($request);

        $this->assertEquals('success', $result->getMessageType());
        $this->assertEquals('Alipay payment was cancelled.', $result->getMessage());

        // Verify payment was marked as failed
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'failed',
        ]);
    }
}
