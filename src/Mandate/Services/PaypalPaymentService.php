<?php

namespace Foundry\Mandate\Services;

use Exception;
use Foundry\Foundry;
use Foundry\Mandate\Models\PaymentMethod as PaymentMethodModel;
use Foundry\Payment\Mappers\PayPalPayment as PaypalPaymentMapper;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Foundry\Services\PaymentProvider;
use Illuminate\Http\Request;

/**
 * PayPal payment service implementation.
 */
class PaypalPaymentService extends PaymentService
{
    /**
     * Provider identifier.
     */
    public const PROVIDER = PaymentProvider::PAYPAL;

    /**
     * Handle PayPal agreement redirect callback.
     *
     * @throws Exception
     */
    public function handleCallback(Request $request): ?PaymentMethodModel
    {
        if ($request->input('error') === 'cancelled') {
            throw new Exception('PayPal mandate registration was cancelled.');
        }

        $baToken = $request->query('ba_token') ?? $request->input('ba_token');
        if (! $baToken) {
            throw new Exception('Agreement token not found in callback request.');
        }

        $paypal = Foundry::paypal();
        $confirmResponse = $paypal->executeBillingAgreement($baToken);

        if (isset($confirmResponse['error']) || ! isset($confirmResponse['id'])) {
            $errorMsg = $confirmResponse['error']['message'] ?? $confirmResponse['message'] ?? 'PayPal confirmation failed';
            throw new Exception($errorMsg);
        }

        $agreementId = $confirmResponse['id'];

        $pm = $this->createOrUpdatePaymentMethod(
            $this->getUserId(),
            self::PROVIDER,
            $agreementId,
            [
                'status' => 'active',
                'type' => 'paypal',
                'email' => $confirmResponse['payer']['payer_info']['email'] ?? null,
                'first_name' => $confirmResponse['payer']['payer_info']['first_name'] ?? null,
                'last_name' => $confirmResponse['payer']['payer_info']['last_name'] ?? null,
                'payer_id' => $confirmResponse['payer']['payer_info']['payer_id'] ?? null,
            ]
        );

        $pm->markAsDefault();

        return $pm;
    }

    /**
     * Set up a saved PayPal Vault ID or billing agreement.
     *
     * @return PaymentMethodModel|RedirectResponse|null
     *
     * @throws Exception
     */
    public function setup(): mixed
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

        $paypal = Foundry::paypal();

        $callbackUrl = route(config('foundry.mandate.callback_route', 'payment-methods.callback'), ['provider' => self::PROVIDER]);
        $cancelUrl = route(config('foundry.mandate.callback_route', 'payment-methods.callback'), ['provider' => self::PROVIDER, 'error' => 'cancelled']);

        $response = $paypal->createBillingAgreementToken([
            'description' => 'Automatic Billing for '.config('app.name'),
            'payer' => [
                'payment_method' => 'PAYPAL',
            ],
            'plan' => [
                'type' => 'MERCHANT_INITIATED_BILLING',
                'merchant_preferences' => [
                    'return_url' => $callbackUrl,
                    'cancel_url' => $cancelUrl,
                    'accepted_pymt_type' => 'INSTANT',
                    'skip_shipping_address' => true,
                ],
            ],
        ]);

        if (isset($response['error']) || ! isset($response['token_id'])) {
            $errorMsg = $response['error']['message'] ?? $response['message'] ?? 'PayPal token generation failed';
            throw new Exception($errorMsg);
        }

        $baToken = $response['token_id'];

        $gatewayUrl = ($paypal->mode ?? 'sandbox') === 'sandbox' ? 'https://www.sandbox.paypal.com' : 'https://www.paypal.com';
        $redirectUrl = "{$gatewayUrl}/agreements/approve?ba_token={$baToken}";

        return new \Foundry\Mandate\Responses\RedirectResponse($redirectUrl, [
            'token_id' => $baToken,
            'checkout_url' => $redirectUrl,
        ]);
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
            self::PROVIDER,
            $this->paymentMethod
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

            return PaymentResult::success(
                paymentData: new PaypalPaymentMapper($response),
                transactionId: $response['id'],
                status: $response['status'] ?? 'COMPLETED'
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
