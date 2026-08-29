<?php

namespace Tests\Feature\Payment;

use Foundry\Foundry;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;
use Foundry\Payment\Mappers\PaddlePayment;
use Foundry\Payment\Payable;
use Foundry\Payment\Processor;
use Foundry\Payment\Processors\PaddleProcessor;
use Foundry\Tests\TestCase;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Paddle\SDK\Client as PaddleSdkClient;
use Paddle\SDK\Entities\Adjustment;
use Paddle\SDK\Entities\Shared\AdjustmentStatus;
use Paddle\SDK\Entities\Shared\CurrencyCode;
use Paddle\SDK\Entities\Shared\TransactionPaymentAttempt;
use Paddle\SDK\Entities\Shared\TransactionStatus;
use Paddle\SDK\Entities\Transaction;
use Paddle\SDK\Resources\Adjustments\AdjustmentsClient;
use Paddle\SDK\Resources\Transactions\TransactionsClient;
use PHPUnit\Framework\Attributes\Test;

class PaddleProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentMethod = PaymentMethod::factory()->paddle()->active()->create();
        PaymentMethod::updateProviderCache(PaymentMethod::PADDLE);
    }

    protected function tearDown(): void
    {
        Foundry::setPaddleClient(null);
        parent::tearDown();
    }

    protected function createMockPayable(): Payable
    {
        return Payable::make([
            'reference_id' => 'ORD-9988',
            'grand_total' => 49.99,
            'currency' => 'USD',
            'customer_email' => 'user@example.com',
            'description' => 'SaaS Subscription Order',
            'line_items' => [
                [
                    'id' => 'plan_pro',
                    'name' => 'Pro Plan Monthly',
                    'price' => 49.99,
                    'quantity' => 1,
                ],
            ],
            'source' => (object) ['id' => 1],
        ]);
    }

    #[Test]
    public function it_creates_paddle_processor_instance()
    {
        $processor = Processor::make('paddle');

        $this->assertInstanceOf(PaddleProcessor::class, $processor);
        $this->assertEquals('paddle', $processor->getProvider());
    }

    #[Test]
    public function it_sets_up_paddle_payment_intent()
    {
        $paddleMock = $this->createMock(PaddleSdkClient::class);

        $mockResponseBody = json_encode([
            'data' => [
                'id' => 'txn_01h8abcdef1234567890',
                'status' => 'draft',
                'checkout' => [
                    'url' => 'https://sandbox-checkout.paddle.com/txn_01h8abcdef1234567890',
                ],
            ],
        ]);

        $paddleMock->expects($this->once())
            ->method('postRaw')
            ->with('/transactions', $this->isType('array'))
            ->willReturn(new Response(200, ['Content-Type' => 'application/json'], $mockResponseBody));

        Foundry::setPaddleClient($paddleMock);

        $processor = new PaddleProcessor;
        $processor->setPaymentMethod($this->paymentMethod);
        $payable = $this->createMockPayable();

        $result = $processor->setupPaymentIntent(new Request, $payable);

        $this->assertEquals('txn_01h8abcdef1234567890', $result['transaction_id']);
        $this->assertEquals(49.99, $result['amount']);
        $this->assertEquals('USD', $result['currency']);
        $this->assertEquals('https://sandbox-checkout.paddle.com/txn_01h8abcdef1234567890', $result['checkout_url']);
    }

    #[Test]
    public function it_confirms_paddle_payment()
    {
        $paddleMock = $this->createMock(PaddleSdkClient::class);
        $transactionsClientMock = $this->createMock(TransactionsClient::class);

        $transactionMock = $this->createMock(Transaction::class);
        $transactionMock->id = 'txn_01h8abcdef1234567890';
        $transactionMock->status = TransactionStatus::Completed();
        $transactionMock->currencyCode = CurrencyCode::USD();
        $transactionMock->payments = [
            TransactionPaymentAttempt::from([
                'payment_attempt_id' => 'pay_attempt_123',
                'amount' => '4999',
                'status' => 'captured',
                'created_at' => '2026-01-01T00:00:00Z',
                'payment_method_id' => 'pay_123',
                'method_details' => [
                    'type' => 'card',
                    'card' => [
                        'type' => 'visa',
                        'last4' => '4242',
                        'expiry_month' => 12,
                        'expiry_year' => 2030,
                    ],
                ],
            ]),
        ];

        $transactionsClientMock->expects($this->once())
            ->method('get')
            ->with('txn_01h8abcdef1234567890')
            ->willReturn($transactionMock);

        $reflection = new \ReflectionClass(PaddleSdkClient::class);
        $property = $reflection->getProperty('transactions');
        $property->setValue($paddleMock, $transactionsClientMock);

        Foundry::setPaddleClient($paddleMock);

        $processor = Processor::make('paddle');
        $processor->setPaymentMethod($this->paymentMethod);

        $payable = $this->createMockPayable();
        $request = new Request(['transaction_id' => 'txn_01h8abcdef1234567890']);

        $result = $processor->confirmPayment($request, $payable);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('txn_01h8abcdef1234567890', $result->getTransactionId());
        $this->assertInstanceOf(PaddlePayment::class, $result->getPaymentData());
        $this->assertEquals('Paddle (Visa •••• 4242)', $result->getPaymentData()->toString());
    }

    #[Test]
    public function it_handles_paddle_refund()
    {
        $paddleMock = $this->createMock(PaddleSdkClient::class);
        $adjustmentsClientMock = $this->createMock(AdjustmentsClient::class);

        $adjustmentMock = $this->createMock(Adjustment::class);
        $adjustmentMock->id = 'rf_01h8ref12345';
        $adjustmentMock->status = AdjustmentStatus::Approved();

        $adjustmentsClientMock->expects($this->once())
            ->method('create')
            ->willReturn($adjustmentMock);

        $reflection = new \ReflectionClass(PaddleSdkClient::class);
        $property = $reflection->getProperty('adjustments');
        $property->setValue($paddleMock, $adjustmentsClientMock);

        Foundry::setPaddleClient($paddleMock);

        $processor = new PaddleProcessor;
        $processor->setPaymentMethod($this->paymentMethod);

        $payment = Payment::create([
            'paymentable_type' => 'App\Models\Order',
            'paymentable_id' => 1,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_id' => 'txn_01h8abcdef1234567890',
            'amount' => 49.99,
            'status' => 'completed',
        ]);

        $result = $processor->refund($payment, 49.99, 'Customer requested refund');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('rf_01h8ref12345', $result->getRefundId());
        $this->assertEquals(49.99, $result->getAmount());
    }
}
