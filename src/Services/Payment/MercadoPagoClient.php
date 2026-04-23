<?php

namespace Foundry\Services\Payment;

use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

class MercadoPagoClient
{
    /**
     * @var PaymentClient
     */
    protected $paymentClient;

    /**
     * @var PreferenceClient
     */
    protected $preferenceClient;

    /**
     * MercadoPagoClient constructor.
     */
    public function __construct(array $options = [])
    {
        $accessToken = $options['access_token'] ?? config('mercadopago.access_token');
        if (! $accessToken) {
            throw new \InvalidArgumentException('MercadoPago access_token is required.');
        }
        MercadoPagoConfig::setAccessToken($accessToken);
        $this->paymentClient = new PaymentClient;
        $this->preferenceClient = new PreferenceClient;
    }

    /**
     * Create a payment intent (preference or payment)
     *
     * @return mixed
     */
    public function createPaymentIntent(array $params)
    {
        // You can use PreferenceClient for checkout preferences or PaymentClient for direct payments
        if (isset($params['items'])) {
            // Create a checkout preference
            return $this->preferenceClient->create($params);
        }

        // Otherwise, create a direct payment
        return $this->paymentClient->create($params);
    }

    /**
     * Confirm a payment (fetch payment by ID)
     *
     * @param  string  $paymentId
     * @return mixed
     */
    public function confirmPayment($paymentId)
    {
        return $this->paymentClient->get($paymentId);
    }
}
