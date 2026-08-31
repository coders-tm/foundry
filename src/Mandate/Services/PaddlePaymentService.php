<?php

namespace Foundry\Mandate\Services;

use Foundry\Foundry;
use Foundry\Mandate\Models\PaymentMethod as PaymentMethodModel;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Foundry\Services\PaymentProvider;
use Illuminate\Http\Request;
use Paddle\SDK\Entities\Shared\CurrencyCode;
use Paddle\SDK\Entities\Shared\CustomData;
use Paddle\SDK\Entities\Shared\Interval;
use Paddle\SDK\Entities\Shared\Money;
use Paddle\SDK\Entities\Shared\TaxCategory;
use Paddle\SDK\Entities\Shared\TimePeriod;
use Paddle\SDK\Entities\Subscription\SubscriptionEffectiveFrom;
use Paddle\SDK\Resources\Subscriptions\Operations\Charge\SubscriptionChargeItemWithPrice;
use Paddle\SDK\Resources\Subscriptions\Operations\Charge\SubscriptionChargeNonCatalogPriceWithProduct;
use Paddle\SDK\Resources\Subscriptions\Operations\Charge\SubscriptionChargeNonCatalogProduct;
use Paddle\SDK\Resources\Subscriptions\Operations\CreateOneTimeCharge;
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
        $subscriptionId = null;
        $paymentMethodType = null;

        try {
            $paddle = Foundry::paddle();
            $transaction = $paddle->transactions->get($txnId);

            if ($transaction) {
                if (isset($transaction->payments[0]->paymentMethodId)) {
                    $pmId = $transaction->payments[0]->paymentMethodId;
                }

                $subscriptionId = $transaction->subscriptionId ?? null;

                if (isset($transaction->payments[0]->methodDetails->type)) {
                    $paymentMethodType = strtolower($transaction->payments[0]->methodDetails->type->getValue());
                }
            }
        } catch (\Throwable $e) {
            logger()->warning('Could not fetch Paddle transaction details on confirm', ['error' => $e->getMessage()]);
        }

        $finalPmId = (string) ($pmId ?: ($txnId ? 'pay_mtd_'.$txnId : 'pay_mtd_paddle_'.mt_rand()));

        $pm = $this->createOrUpdatePaymentMethod(
            $this->getUserId(),
            self::PROVIDER,
            $finalPmId,
            [
                'payment_method_id' => $finalPmId,
                'transaction_id' => $txnId,
                'subscription_id' => $subscriptionId,
                'payment_method_type' => $paymentMethodType,
                'card_brand' => $cardBrand,
                'card_last_four' => $cardLastFour,
            ]
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
        $product = new TransactionNonCatalogProduct('Mandate Setup', TaxCategory::Standard());
        $nonCatalogPrice = new TransactionNonCatalogPriceWithProduct(
            description: 'Mandate Setup',
            unitPrice: $money,
            product: $product,
            billingCycle: new TimePeriod(Interval::Month(), 1),
        );

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

        $pmRecord = $paymentMethod ?? $this->getPaymentMethod($this->getUserId(), self::PROVIDER);

        if (! $pmRecord) {
            throw new \Exception('No payment method found for Paddle charging.');
        }

        $subscriptionId = is_object($pmRecord)
            ? ($pmRecord->options['subscription_id'] ?? null)
            : null;

        if (! $subscriptionId) {
            throw new \Exception('No subscription ID found on Paddle payment method. Payment method must be set up with a $0 subscription.');
        }

        return $this->chargeViaSubscription($payable, $subscriptionId, $options);
    }

    /**
     * Charge a Payable entity via a Paddle subscription using createOneTimeCharge.
     */
    protected function chargeViaSubscription(Payable $payable, string $subscriptionId, array $options): PaymentResult
    {
        $paddle = Foundry::paddle();
        $currencyCode = CurrencyCode::from(strtoupper($payable->getCurrency() ?: 'USD'));

        $items = [];
        foreach ($payable->getLineItems() as $item) {
            $description = $item['name'] ?? $item['title'] ?? 'Item';
            $amountString = (string) (int) round(($item['price'] ?? 0) * 100);
            $money = new Money($amountString, $currencyCode);
            $product = new SubscriptionChargeNonCatalogProduct($description, TaxCategory::Standard());
            $price = new SubscriptionChargeNonCatalogPriceWithProduct($description, $money, $product);

            $items[] = new SubscriptionChargeItemWithPrice(
                price: $price,
                quantity: (int) ($item['quantity'] ?? 1)
            );
        }

        if (empty($items)) {
            $description = $payable->getDescription() ?: 'Usage Charge';
            $amountString = (string) (int) round($payable->getGatewayAmount() * 100);
            $money = new Money($amountString, $currencyCode);
            $product = new SubscriptionChargeNonCatalogProduct($description, TaxCategory::Standard());
            $price = new SubscriptionChargeNonCatalogPriceWithProduct($description, $money, $product);
            $items[] = new SubscriptionChargeItemWithPrice(price: $price, quantity: 1);
        }

        $charge = new CreateOneTimeCharge(
            effectiveFrom: SubscriptionEffectiveFrom::Immediately(),
            items: $items,
        );

        try {
            $subscription = $paddle->subscriptions->createOneTimeCharge($subscriptionId, $charge);

            return PaymentResult::success(
                paymentData: null,
                transactionId: $subscription->id,
                status: 'succeeded'
            );
        } catch (\Throwable $e) {
            logger()->error('Paddle subscription charge error', [
                'user_id' => $this->getUserId(),
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception("Paddle charge failed: {$e->getMessage()}", 0, $e);
        }
    }
}
