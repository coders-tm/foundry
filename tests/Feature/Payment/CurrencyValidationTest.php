<?php

namespace Tests\Feature\Payment;

use Foundry\Payment\Payable;
use Foundry\Payment\Processors\StripeProcessor;
use Foundry\Tests\BaseTestCase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class CurrencyValidationTest extends BaseTestCase
{
    #[Test]
    public function it_validates_currency_for_stripe_processor()
    {
        // This test ensures we hooked it up correctly in StripeProcessor specifically
        // It should fail BEFORE calling Stripe API
        $processor = new StripeProcessor;

        $payable = Mockery::mock(Payable::class);
        $payable->shouldReceive('getCurrency')->andReturn('XXX'); // Invalid currency
        $payable->shouldReceive('getGatewayAmount')->andReturn(100);
        $payable->shouldReceive('setCurrencies'); // Allow this call as processors are now setting it

        $this->expectException(ValidationException::class);

        // We know StripeProcessor calls validateCurrency first thing
        $processor->setupPaymentIntent(new Request, $payable);
    }
}
