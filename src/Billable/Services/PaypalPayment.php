<?php

namespace Foundry\Billable\Services;

use Exception;
use Foundry\Billable\Payments\PaypalPayment as PaypalPaymentWrapper;
use Foundry\Foundry;
use Foundry\Models\PaymentMethod as PaymentMethodModel;
use Foundry\Payment\Mappers\PayPalPayment as PaypalPaymentMapper;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;

/**
 * PayPal billable payment service.
 *
 * Manages PayPal customer records, stores Vault IDs / billing agreements,
 * and charges Payable entities off-session.
 */
class PaypalPayment extends BillablePayment
{
    /**
     * Provider identifier.
     */
    public const PROVIDER = 'paypal';

    /**
     * Set up saved PayPal Vault ID / billing agreement for the user.
     */
    public function setup()
    {
        if (! $this->userId) {
            throw new Exception('No user identified for PayPal billable setup.');
        }

        $customer = $this->getOrCreateCustomer(
            $this->userId,
            self::PROVIDER
        );

        if ($this->paymentMethod) {
            $this->createOrUpdatePaymentMethod(
                $this->userId,
                self::PROVIDER,
                $this->paymentMethod,
                [
                    'type' => 'paypal',
                ]
            );
        }

        return $customer;
    }

    /**
     * Remove saved PayPal payment method for the user.
     */
    public function remove()
    {
        if (! $this->userId) {
            throw new Exception('No user identified for PayPal billable removal.');
        }

        $this->deletePaymentMethod(
            $this->userId,
            self::PROVIDER
        );

        return true;
    }

    /**
     * Charge a Payable entity with PayPal using a saved Vault ID.
     *
     * @param  Payable  $payable
     * @param  mixed  $paymentMethod
     * @param  array  $options
     * @return PaymentResult
     *
     * @throws Exception
     */
    public function charge(Payable $payable, mixed $paymentMethod = null, array $options = []): PaymentResult
    {
        if (! $this->userId) {
            throw new Exception('No user identified for charging.');
        }

        $pmRecord = $paymentMethod;
        if (! $pmRecord) {
            $pmRecord = $this->getPaymentMethod($this->userId, self::PROVIDER);
        }

        $pmId = is_object($pmRecord) ? ($pmRecord->provider_id ?? null) : $pmRecord;

        if (! $pmId) {
            throw new Exception('No PayPal payment method found for charging.');
        }

        try {
            $paypal = Foundry::paypal();

            $data = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => $payable->getCurrency(),
                            'value' => number_format($payable->getGatewayAmount(), 2, '.', ''),
                        ],
                        'description' => $payable->getDescription(),
                        'reference_id' => $payable->getReferenceId(),
                    ],
                ],
                'payment_source' => [
                    'paypal' => [
                        'vault_id' => $pmId,
                    ],
                ],
            ];

            $data = array_merge_recursive($data, $options);
            $response = $paypal->createOrder($data);

            if (isset($response['id']) && ($response['status'] ?? '') === 'CREATED') {
                $response = $paypal->capturePaymentOrder($response['id']);
            }

            if (! isset($response['id']) || (isset($response['status']) && $response['status'] === 'FAILED')) {
                throw new Exception('PayPal charge failed: '.json_encode($response));
            }

            $paymentMethodModel = PaymentMethodModel::byProvider(self::PROVIDER);
            $paymentMapper = new PaypalPaymentMapper($response, $paymentMethodModel);
            $wrapper = new PaypalPaymentWrapper($response);

            return PaymentResult::success(
                paymentData: $paymentMapper,
                transactionId: $response['id'],
                status: $response['status'] ?? 'COMPLETED',
                metadata: [
                    'wrapper' => $wrapper,
                    'response' => $response,
                ]
            );
        } catch (Exception $e) {
            logger()->error('PayPal charge failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);

            return PaymentResult::failed("Charge failed: {$e->getMessage()}");
        }
    }
}
