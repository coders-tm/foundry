<?php

namespace Foundry\Services\Payment;

use Illuminate\Support\Facades\Http;

class PayuClient
{
    protected string $key;

    protected string $salt;

    protected bool $isTest;

    protected string $baseUrl;

    public function __construct(array $config = [])
    {
        $this->key = $config['merchant_key'] ?? $config['key'] ?? config('payu.merchant_key') ?? '';
        $this->salt = $config['merchant_salt'] ?? $config['salt'] ?? config('payu.merchant_salt') ?? '';
        $this->isTest = $config['test_mode'] ?? config('payu.test_mode', true);
        $this->baseUrl = $this->isTest ? 'https://test.payu.in' : 'https://info.payu.in';
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getSalt(): string
    {
        return $this->salt;
    }

    public function isTestMode(): bool
    {
        return $this->isTest;
    }

    /**
     * Call a PayU API post service command.
     */
    public function postService(string $command, array $var1Data): array
    {
        $var1 = json_encode($var1Data);
        $hashSequence = sprintf(
            '%s|%s|%s|%s',
            $this->key,
            $command,
            $var1,
            $this->salt
        );
        $hash = hash('sha512', $hashSequence);

        $url = $this->isTest
            ? "{$this->baseUrl}/merchant/postservice?form=2"
            : "{$this->baseUrl}/merchant/postservice.php?form=2";

        $response = Http::asForm()->post($url, [
            'key' => $this->key,
            'command' => $command,
            'var1' => $var1,
            'hash' => $hash,
        ]);

        return $response->json() ?? [];
    }

    /**
     * Create a payment intent (returns payment parameters + hash + checkout URL)
     */
    public function createPaymentIntent(array $params): array
    {
        $params['key'] = $this->key;

        if (empty($params['hash'])) {
            $params['hash'] = $this->calculateHash($params);
        }

        return array_merge($params, [
            'checkout_url' => $this->getCheckoutUrl(),
        ]);
    }

    /**
     * Get the checkout URL based on test mode.
     */
    public function getCheckoutUrl(): string
    {
        return $this->isTest ? 'https://test.payu.in/_payment' : 'https://secure.payu.in/_payment';
    }

    /**
     * Generate request hash for PayU.
     */
    public function calculateHash(array $params): string
    {
        $udfs = '';
        for ($i = 1; $i <= 10; $i++) {
            $udfs .= ($params["udf{$i}"] ?? '').'|';
        }

        $hashSequence = sprintf(
            '%s|%s|%s|%s|%s|%s|%s%s',
            $this->key,
            $params['txnid'] ?? '',
            $params['amount'] ?? '',
            $params['productinfo'] ?? '',
            $params['firstname'] ?? '',
            $params['email'] ?? '',
            $udfs,
            $this->salt
        );

        return hash('sha512', $hashSequence);
    }

    /**
     * Calculate response hash.
     */
    public function calculateResponseHash(array $response): string
    {
        $status = $response['status'] ?? '';
        $key = $response['key'] ?? '';
        $txnid = $response['txnid'] ?? '';
        $amount = $response['amount'] ?? '';
        $productinfo = $response['productinfo'] ?? '';
        $firstname = $response['firstname'] ?? '';
        $email = $response['email'] ?? '';

        $udfs = '';
        for ($i = 10; $i >= 1; $i--) {
            $udfs .= ($response["udf{$i}"] ?? '').'|';
        }

        $hashSequence = sprintf(
            '%s|%s|%s%s|%s|%s|%s|%s|%s',
            $this->salt,
            $status,
            $udfs,
            $email,
            $firstname,
            $productinfo,
            $amount,
            $txnid,
            $key
        );

        $additionalCharges = $response['additionalCharges'] ?? null;
        if ($additionalCharges) {
            $hashSequence = $additionalCharges.'|'.$hashSequence;
        }

        return hash('sha512', $hashSequence);
    }
}
