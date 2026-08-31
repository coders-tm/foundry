<?php

namespace Foundry\Mandate\Services;

use Foundry\Foundry;
use Foundry\Mandate\Exceptions\PaymentIncomplete;
use Foundry\Mandate\Models\PaymentMethod as PaymentMethodModel;
use Foundry\Mandate\Payments\PaddlePayment as PaddlePaymentWrapper;
use Foundry\Payment\Mappers\PaddlePayment as PaddlePaymentMapper;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Foundry\Services\PaymentProvider;
use Illuminate\Http\Request;
use Paddle\SDK\Entities\Shared\CurrencyCode;
use Paddle\SDK\Entities\Shared\CustomData;
use Paddle\SDK\Entities\Shared\Money;
use Paddle\SDK\Entities\Shared\TaxCategory;
use Paddle\SDK\Entities\Transaction;
use Paddle\SDK\Resources\Transactions\Operations\Create\TransactionCreateItemWithPrice;
use Paddle\SDK\Resources\Transactions\Operations\CreateTransaction;
use Paddle\SDK\Resources\Transactions\Operations\Price\TransactionNonCatalogPriceWithProduct;
use Paddle\SDK\Resources\Transactions\Operations\Price\TransactionNonCatalogProduct;

/**
 * Paddle payment service implementation.
 */
class PaddlePaymentService extends PaymentService
{
    /**
     * Provider identifier.
     */
    public const PROVIDER = PaymentProvider::PADDLE;

    /**
     * Validate setup request parameters for Paddle.
     */
    public function validate(Request $request): array
    {
        return $request->validate([
            'payment_method' => 'nullable|string',
        ]);
    }

    /**
     * Confirm Paddle payment method setup.
     *
     * @throws \Exception
     */
    public function confirm(array $options): PaymentMethodModel
    {
        $pmId = $options['payment_method_id'] ?? $options['payment_method'] ?? null;
        $txnId = $options['transaction_id'] ?? null;

        if (! $pmId && ! $txnId) {
            throw new \InvalidArgumentException('Missing payment_method_id or transaction_id in options.');
        }

        $cardBrand = 'paddle';
        $cardLastFour = '';

        try {
            if ($txnId && empty($pmId)) {
                $paddle = Foundry::paddle();
                $transaction = $paddle->transactions->get($txnId);
                if ($transaction && isset($transaction->payments[0]->paymentMethodId)) {
                    $pmId = $transaction->payments[0]->paymentMethodId;
                }
            }
        } catch (\Throwable $e) {
            logger()->warning('Could not fetch Paddle transaction details on confirm', ['error' => $e->getMessage()]);
        }

        $finalPmId = (string) ($pmId ?: ($txnId ? 'pay_mtd_' . $txnId : 'pay_mtd_paddle_' . Math.random()));

        $pm = $this->createOrUpdatePaymentMethod(
            $this->getUserId(),
            self::PROVIDER,
            $finalPmId,
            array_merge([
                'payment_method_id' => $finalPmId,
                'transaction_id' => $txnId,
                'card_brand' => $cardBrand,
                'card_last_four' => $cardLastFour,
            ], $options)
        );

        $pm->markAsDefault();

        return $pm;
    }

    /**
     * Set up a saved Paddle payment method.
     *
     * @return PaymentMethodModel|array|null
     *
     * @throws \Exception
     */
    public function setup(): mixed
    {
        if (! $this->getUserId()) {
            throw new \Exception('User model key is required for Paddle setup.');
        }

        $customer = $this->getOrCreateCustomer(
            $this->getUserId(),
            self::PROVIDER
        );

        if ($this->paymentMethod) {
            $pmId = is_object($this->paymentMethod) ? ($this->paymentMethod->provider_id ?? $this->paymentMethod->id) : $this->paymentMethod;
            $pm = $this->createOrUpdatePaymentMethod(
                $this->getUserId(),
                self::PROVIDER,
                (string) $pmId,
                [
                    'payment_method_id' => (string) $pmId,
                ]
            );

            $pm->markAsDefault();

            return $pm;
        }

        $paddle = Foundry::paddle();
        $currencyCodeStr = strtoupper(config('foundry.payment_providers.paddle.currency', config('app.currency', 'USD')));
        $currencyCode = CurrencyCode::from($currencyCodeStr);

        $money = new Money('0', $currencyCode);
        $product = new TransactionNonCatalogProduct('Subscription Mandate Setup', TaxCategory::Standard());
        $nonCatalogPrice = new TransactionNonCatalogPriceWithProduct('Mandate Setup', $money, $product);

        $items = [
            new TransactionCreateItemWithPrice(
                price: $nonCatalogPrice,
                quantity: 1
            ),
        ];

        $customData = new CustomData([
            'user_id' => (string) $this->getUserId(),
            'action' => 'setup_mandate',
        ]);

        $createTransaction = new CreateTransaction(
            items: $items,
            customData: $customData,
            currencyCode: $currencyCode,
            customerId: $customer->provider_id ?: null
        );

        $transaction = $paddle->transactions->create($createTransaction);
        $transactionId = $transaction->id ?? null;

        return [
            'action' => 'sdk',
            'provider' => self::PROVIDER,
            'transaction_id' => $transactionId,
            'client_token' => config('foundry.payment_providers.paddle.client_token'),
            'environment' => config('foundry.payment_providers.paddle.environment', 'sandbox'),
        ];
    }

    /**
     * Remove the saved Paddle payment method.
     *
     * @throws \Exception
     */
    public function remove(): bool
    {
        if (! $this->getUserId()) {
            throw new \Exception('User model key is required for Paddle removal.');
        }

        $this->deletePaymentMethod(
            $this->getUserId(),
            self::PROVIDER
        );

        return true;
    }

    /**
     * Charge a Payable entity using a saved Paddle payment method.
     *
     * @param  PaymentMethodModel|string|null  $paymentMethod
     *
     * @throws \Exception
     */
    public function charge(Payable $payable, mixed $paymentMethod = null, array $options = []): PaymentResult
    {
        if (! $this->getUserId()) {
            throw new \Exception('User model key is required for charging.');
        }

        $pmRecord = $paymentMethod;
        if (! $pmRecord) {
            $pmRecord = $this->getPaymentMethod($this->getUserId(), self::PROVIDER);
        }

        $pmId = is_object($pmRecord) ? ($pmRecord->provider_id ?? null) : $pmRecord;

        if (! $pmId) {
            throw new \Exception('No payment method found for Paddle charging.');
        }

        $customer = $this->getOrCreateCustomer($this->getUserId(), self::PROVIDER);

        try {
            $paddle = Foundry::paddle();

            $currencyCodeStr = strtoupper($payable->getCurrency() ?: 'USD');
            $currencyCode = CurrencyCode::from($currencyCodeStr);
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
                'user_id' => (string) $this->getUserId(),
                'payment_method_id' => (string) $pmId,
            ]));

            $createTransaction = new CreateTransaction(
                items: $items,
                customData: $customData,
                currencyCode: $currencyCode,
                customerId: $customer->provider_id ?: null
            );

            $transaction = $paddle->transactions->create($createTransaction);

            $status = strtolower($transaction instanceof Transaction ? $transaction->status->getValue() : ($transaction['status'] ?? ''));

            if (in_array($status, ['requires_action', 'requires_auth', 'authentication_required'])) {
                $transactionArray = $transaction instanceof Transaction ? [
                    'id' => $transaction->id,
                    'amount' => (int) round($payable->getGatewayAmount() * 100),
                    'currency' => $currencyCodeStr,
                    'status' => 'requires_action',
                ] : (array) $transaction;

                $wrapper = new PaddlePaymentWrapper($transactionArray);
                throw new PaymentIncomplete($wrapper);
            }

            if (in_array($status, ['completed', 'paid', 'ready', 'billed', 'succeeded'])) {
                $transactionId = $transaction instanceof Transaction ? $transaction->id : ($transaction['id'] ?? 'txn_paddle');
                $paymentMapper = $transaction instanceof Transaction
                    ? new PaddlePaymentMapper($transaction)
                    : null;

                return PaymentResult::success(
                    paymentData: $paymentMapper,
                    transactionId: $transactionId,
                    status: 'succeeded'
                );
            }

            return PaymentResult::failed("Paddle charge failed with status: {$status}");
        } catch (PaymentIncomplete $incompleteEx) {
            throw $incompleteEx;
        } catch (\Throwable $e) {
            logger()->error('Paddle charge error', [
                'user_id' => $this->getUserId(),
                'error' => $e->getMessage(),
            ]);

            return PaymentResult::failed("Charge failed: {$e->getMessage()}");
        }
    }
}
