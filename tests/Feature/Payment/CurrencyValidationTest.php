<?php

use Foundry\Payment\Payable;
use Foundry\Payment\Processors\StripeProcessor;
use Foundry\Tests\BaseTestCase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

uses(BaseTestCase::class);

it('validates currency for stripe processor', function () {
    $processor = new StripeProcessor;

    $payable = Mockery::mock(Payable::class);
    $payable->shouldReceive('getCurrency')->andReturn('XXX');
    $payable->shouldReceive('getGatewayAmount')->andReturn(100);
    $payable->shouldReceive('setCurrencies');

    $this->expectException(ValidationException::class);

    $processor->setupPaymentIntent(new Request, $payable);
});
