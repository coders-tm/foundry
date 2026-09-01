<?php

uses(\Foundry\Tests\Feature\FeatureTestCase::class);

beforeEach(function () {
    if (! env('GOCARDLESS_ACCESS_TOKEN')) {
        $this->markTestSkipped('GoCardless credentials not configured.');
    }
    config(['foundry.payment_providers.gocardless.enabled' => true]);
});

it('retrieves gocardless provider config', function () {
    $gocardless = \Foundry\Services\PaymentProvider::find(\Foundry\Services\PaymentProvider::GOCARDLESS);
    $this->assertNotNull($gocardless);
    $this->assertEquals(\Foundry\Services\PaymentProvider::GOCARDLESS, $gocardless['provider']);
});

it('creates gocardless client instance', function () {
    $client = \Foundry\Foundry::gocardless();
    $this->assertInstanceOf(\GoCardlessPro\Client::class, $client);
});

it('supports multiple country payment schemes', function () {
    $schemes = config('foundry.payment_providers.gocardless.schemes');
    $this->assertEquals('bacs', $schemes['GB']);
    $this->assertEquals('sepa_core', $schemes['DE']);
    $this->assertEquals('sepa_core', $schemes['FR']);
    $this->assertEquals('sepa_core', $schemes['ES']);
    $this->assertEquals('sepa_core', $schemes['IT']);
    $this->assertEquals('sepa_core', $schemes['NL']);
    $this->assertEquals('sepa_core', $schemes['BE']);
    $this->assertEquals('becs', $schemes['AU']);
    $this->assertEquals('becs_nz', $schemes['NZ']);
    $this->assertEquals('ach', $schemes['US']);
    $this->assertEquals('pad', $schemes['CA']);
    $this->assertEquals('autogiro', $schemes['SE']);
});

it('validates access token format', function () {
    $token = config('foundry.payment_providers.gocardless.access_token');
    $provider = \Foundry\Services\PaymentProvider::find(\Foundry\Services\PaymentProvider::GOCARDLESS);
    $this->assertNotEmpty($token);
    $this->assertIsString($token);
});
