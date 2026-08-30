<?php

namespace Foundry\Payment\Processors;

use Foundry\Contracts\PaymentProcessorInterface;
use Foundry\Foundry;
use Foundry\Models\Payment;
use Foundry\Payment\Mappers\PaddlePayment;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Foundry\Payment\RefundResult;
use Foundry\Services\PaymentProvider;
use Illuminate\Http\Request;
use Paddle\SDK\Entities\Shared\Action;
use Paddle\SDK\Entities\Shared\CurrencyCode;
use Paddle\SDK\Entities\Shared\CustomData;
use Paddle\SDK\Entities\Shared\Money;
use Paddle\SDK\Entities\Shared\TaxCategory;
use Paddle\SDK\Entities\Transaction;
use Paddle\SDK\Resources\Adjustments\Operations\CreateAdjustment;
use Paddle\SDK\Resources\Transactions\Operations\Create\TransactionCreateItemWithPrice;
use Paddle\SDK\Resources\Transactions\Operations\CreateTransaction;
use Paddle\SDK\Resources\Transactions\Operations\Price\TransactionNonCatalogPriceWithProduct;
use Paddle\SDK\Resources\Transactions\Operations\Price\TransactionNonCatalogProduct;

class PaddleProcessor extends AbstractPaymentProcessor implements PaymentProcessorInterface
{
    private const SUPPORTED_CURRENCIES = [
        'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF', 'NZD', 'SGD', 'HKD',
        'SEK', 'NOK', 'DKK', 'PLN', 'BRL', 'INR', 'MXN', 'CZK', 'HUF', 'ILS',
        'MYR', 'PHP', 'RON', 'THB', 'TWD', 'ZAR', 'CLP', 'COP', 'IDR', 'KRW',
    ];

    public function getProvider(): string
    {
        return PaymentProvider::PADDLE;
    }

    public function supportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    public function setupPaymentIntent(Request $request, Payable $payable): array
    {
        $payable->setCurrencies($this->supportedCurrencies());
        $this->validateCurrency($payable);

        $paddle = Foundry::paddle();

        $currencyCode = CurrencyCode::from(strtoupper($payable->getCurrency()));
        $items = [];

        foreach ($payable->getLineItems() as $item) {
            $description = $item['name'] ?? $item['title'] ?? 'Item';
            $amountString = (string) round(($item['price'] ?? 0) * 100);

            $money = new Money($amountString, $currencyCode);
            $product = new TransactionNonCatalogProduct($description, TaxCategory::Standard());
            $nonCatalogPrice = new TransactionNonCatalogPriceWithProduct($description, $money, $product);

            $items[] = new TransactionCreateItemWithPrice(
                price: $nonCatalogPrice,
                quantity: (int) ($item['quantity'] ?? 1)
            );
        }

        if (empty($items)) {
            $description = $payable->getDescription() ?: 'Order Payment';
            $amountString = (string) round($payable->getGatewayAmount() * 100);

            $money = new Money($amountString, $currencyCode);
            $product = new TransactionNonCatalogProduct($description, TaxCategory::Standard());
            $nonCatalogPrice = new TransactionNonCatalogPriceWithProduct($description, $money, $product);

            $items[] = new TransactionCreateItemWithPrice(
                price: $nonCatalogPrice,
                quantity: 1
            );
        }

        $customData = new CustomData(array_merge($payable->getMetadata(), [
            'reference_id' => $payable->getReferenceId(),
            'customer_email' => $payable->getCustomerEmail(),
        ]));

        $createTransaction = new CreateTransaction(
            items: $items,
            customData: $customData,
            currencyCode: $currencyCode
        );

        $transaction = $paddle->transactions->create($createTransaction);

        return [
            'transaction_id' => $transaction->id ?? '',
            'amount' => $payable->getGrandTotal(),
            'currency' => strtoupper($payable->getCurrency()),
            'checkout_url' => $transaction->checkout->url ?? null,
            'status' => strtolower($transaction->status->getValue() ?? 'draft'),
        ];
    }

    public function confirmPayment(Request $request, Payable $payable): PaymentResult
    {
        $request->validate([
            'transaction_id' => 'required|string',
        ]);

        try {
            $paddle = Foundry::paddle();
            $transaction = $paddle->transactions->get($request->transaction_id);

            $status = strtolower($transaction instanceof Transaction ? $transaction->status->getValue() : ($transaction['status'] ?? ''));
            if (! in_array($status, ['completed', 'paid', 'ready', 'billed'])) {
                return PaymentResult::failed("Payment not completed. Status: {$status}");
            }

            $paymentData = new PaddlePayment($transaction);
            $transactionId = $transaction instanceof Transaction ? $transaction->id : ($transaction['id'] ?? $request->transaction_id);

            return PaymentResult::success(
                paymentData: $paymentData,
                transactionId: $transactionId,
                status: 'success'
            );
        } catch (\Throwable $e) {
            return PaymentResult::failed($e->getMessage());
        }
    }

    public function supportsRefund(): bool
    {
        return true;
    }

    public function refund(Payment $payment, ?float $amount = null, ?string $reason = null): RefundResult
    {
        try {
            $paddle = Foundry::paddle();

            $adjustmentOperation = CreateAdjustment::full(
                action: Action::Refund(),
                reason: $reason ?? 'general',
                transactionId: $payment->transaction_id
            );

            $adjustment = $paddle->adjustments->create($adjustmentOperation);

            $refundStatus = strtolower($adjustment->status->getValue() ?? 'completed');
            if (! in_array($refundStatus, ['completed', 'applied', 'approved'])) {
                return RefundResult::failed("Paddle refund failed with status: {$refundStatus}");
            }

            $refundAmount = isset($adjustment->totals->grandTotal) ? ((float) $adjustment->totals->grandTotal) / 100 : ($amount ?? $payment->amount);

            return RefundResult::success(
                refundId: $adjustment->id ?? 'ref_'.fake()->uuid(),
                amount: $refundAmount,
                status: $refundStatus,
                metadata: [
                    'paddle_refund_id' => $adjustment->id ?? null,
                    'transaction_id' => $payment->transaction_id,
                    'reason' => $reason,
                ]
            );
        } catch (\Throwable $e) {
            return RefundResult::failed('Paddle refund error: '.$e->getMessage());
        }
    }
}
