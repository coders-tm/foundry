<?php

namespace Foundry\Tests\Unit;

use Foundry\Services\PaymentProvider;
use Foundry\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PaymentProviderPublicKeyTest extends TestCase
{
    #[Test]
    public function it_resolves_correct_public_key_per_provider_config()
    {
        $stripeConfig = ['provider' => PaymentProvider::STRIPE, 'key' => 'pk_stripe_123'];
        $this->assertEquals('pk_stripe_123', PaymentProvider::getPublicKey($stripeConfig));

        $razorpayConfig = ['provider' => PaymentProvider::RAZORPAY, 'key_id' => 'rzp_live_123'];
        $this->assertEquals('rzp_live_123', PaymentProvider::getPublicKey($razorpayConfig));

        $paddleConfig = ['provider' => PaymentProvider::PADDLE, 'client_token' => 'live_ptok_123'];
        $this->assertEquals('live_ptok_123', PaymentProvider::getPublicKey($paddleConfig));

        $paypalConfig = ['provider' => PaymentProvider::PAYPAL, 'client_id' => 'paypal_client_123'];
        $this->assertEquals('paypal_client_123', PaymentProvider::getPublicKey($paypalConfig));

        $alipayConfig = ['provider' => PaymentProvider::ALIPAY, 'app_id' => 'alipay_app_123'];
        $this->assertEquals('alipay_app_123', PaymentProvider::getPublicKey($alipayConfig));

        $genericConfig = ['provider' => 'custom', 'public_key' => 'custom_pk_123'];
        $this->assertEquals('custom_pk_123', PaymentProvider::getPublicKey($genericConfig));
    }
}
