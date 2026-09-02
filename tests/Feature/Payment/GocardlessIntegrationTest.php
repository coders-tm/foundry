<?php

use Foundry\Foundry;
use Foundry\Services\PaymentProvider;
use Foundry\Tests\Feature\FeatureTestCase;
use GoCardlessPro\Client;

uses(FeatureTestCase::class);

beforeEach(function () {
    if (! env('GOCARDLESS_ACCESS_TOKEN')) {
        $this->markTestSkipped('GoCardless credentials not configured.');
    }
    config(['foundry.payment_providers.gocardless.enabled' => true]);
});

it('retrieves gocardless provider config', function () {
    $gocardless = PaymentProvider::find(PaymentProvider::GOCARDLESS);
    expect($gocardless)->not->toBeNull();
    expect($gocardless['provider'])->toBe(PaymentProvider::GOCARDLESS);
});

it('creates gocardless client instance', function () {
    $client = Foundry::gocardless();
    expect($client)->toBeInstanceOf(Client::class);
});

it('supports multiple country payment schemes', function () {
    $schemes = config('foundry.payment_providers.gocardless.schemes');
    expect($schemes['GB'])->toBe('bacs');
    expect($schemes['DE'])->toBe('sepa_core');
    expect($schemes['FR'])->toBe('sepa_core');
    expect($schemes['ES'])->toBe('sepa_core');
    expect($schemes['IT'])->toBe('sepa_core');
    expect($schemes['NL'])->toBe('sepa_core');
    expect($schemes['BE'])->toBe('sepa_core');
    expect($schemes['AU'])->toBe('becs');
    expect($schemes['NZ'])->toBe('becs_nz');
    expect($schemes['US'])->toBe('ach');
    expect($schemes['CA'])->toBe('pad');
    expect($schemes['SE'])->toBe('autogiro');
});

it('validates access token format', function () {
    $token = config('foundry.payment_providers.gocardless.access_token');
    $provider = PaymentProvider::find(PaymentProvider::GOCARDLESS);
    expect($token)->not->toBeEmpty();
    expect($token)->toBeString();
});
