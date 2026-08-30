<?php

namespace Foundry\Mandate\Services;

use Exception;
use Foundry\Foundry;
use Foundry\Mandate\Models\PaymentMethod as PaymentMethodModel;
use Foundry\Mandate\Payments\PaypalPayment as PaypalPaymentWrapper;
use Foundry\Models\PaymentMethod;
use Foundry\Payment\Mappers\PayPalPayment as PaypalPaymentMapper;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;

/**
 * PayPal payment service implementation.
 */
class PaypalPaymentService extends PaymentService
{
    /**
     * Provider identifier.
     */
    public const PROVIDER = 'paypal';

    /**
     * Set up a saved PayPal Vault ID or billing agreement.
     *
     *
     * @throws Exception
     */
    public function setup(): ?PaymentMethodModel
    {
        if (! $this->getUserId()) {
            throw new Exception('User model key is required for PayPal setup.');
        }

        $this->getOrCreateCustomer(
            $this->getUserId(),
            self::PROVIDER
        );

        if ($this->paymentMethod) {
            $pm = $this->createOrUpdatePaymentMethod(
                $this->getUserId(),
                self::PROVIDER,
                $this->paymentMethod,
                [
                    'type' => 'paypal',
                ]
            );

            $pm->markAsDefault();

            return $pm;
        }

        return $this->getPaymentMethod($this->getUserId(), self::PROVIDER);
    }

    /**
     * Remove the saved PayPal payment method.
     *
     *
     * @throws Exception
     */
    public function remove(): bool
    {
        if (! $this->getUserId()) {
            throw new Exception('User model key is required for PayPal removal.');
        }

        $this->deletePaymentMethod(
            $this->getUserId(),
            self::PROVIDER
        );

        return true;
    }

    /**
     * Charge a Payable entity using a saved PayPal Vault ID.
     *
     * @param  PaymentMethodModel|string|null  $paymentMethod
     *
     * @throws Exception
     */
    public function charge(Payable $payable, mixed $paymentMethod = null, array $options = []): PaymentResult
    {
        if (! $this->getUserId()) {
            throw new Exception('User model key is required for charging.');
        }

        $pmRecord = $paymentMethod;
        if (! $pmRecord) {
            $pmRecord = $this->getPaymentMethod($this->getUserId(), self::PROVIDER);
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

            $paymentMethodModel = PaymentMethod::byProvider(self::PROVIDER);
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
                'user_id' => $this->getUserId(),
                'error' => $e->getMessage(),
            ]);

            return PaymentResult::failed("Charge failed: {$e->getMessage()}");
        }
    }
}
