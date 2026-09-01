<?php

uses(Foundry\Tests\BaseTestCase::class);

it('validates currency for stripe processor', function () {
    $processor = new \Foundry\Payment\Processors\StripeProcessor;

    $payable = \Mockery::mock(\Foundry\Payment\Payable::class);
    $payable->shouldReceive('getCurrency')->andReturn('XXX');
    $payable->shouldReceive('getGatewayAmount')->andReturn(100);
    $payable->shouldReceive('setCurrencies');

    $this->expectException(\Illuminate\Validation\ValidationException::class);

    $processor->setupPaymentIntent(new \Illuminate\Http\Request, $payable);
});
