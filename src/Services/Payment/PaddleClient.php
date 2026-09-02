<?php

namespace Foundry\Services\Payment;

use Paddle\SDK\Client;
use Paddle\SDK\Environment;
use Paddle\SDK\Options;

class PaddleClient
{
    /**
     * Create an instance of the official Paddle SDK Client
     */
    public static function make(array $options = []): Client
    {
        $apiKey = $options['api_key'] ?? config('foundry.payment-providers.paddle.api_key');

        if (empty($apiKey)) {
            throw new \InvalidArgumentException('Paddle API key is required.');
        }

        $envSetting = $options['environment'] ?? config('foundry.payment-providers.paddle.environment', 'sandbox');

        $environment = $envSetting === 'sandbox' ? Environment::SANDBOX : Environment::PRODUCTION;

        return new Client($apiKey, new Options($environment));
    }
}
