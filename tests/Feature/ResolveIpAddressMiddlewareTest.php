<?php

uses(Foundry\Tests\TestCase::class);

beforeEach(function () {
    \Illuminate\Support\Facades\Route::middleware(\Foundry\Http\Middleware\ResolveIpAddress::class)->get('/_test/ip-resolution', function () {
        return response()->json([
            'ip_location' => request()->attributes->get('ip_location'),
        ]);
    });
});

it('resolves ip location', function () {
    $ip = '8.8.8.8';
    $position = new \Stevebauman\Location\Position;
    $position->ip = $ip;
    $position->countryCode = 'US';
    $position->countryName = 'United States';

    \Stevebauman\Location\Facades\Location::shouldReceive('get')
        ->twice()
        ->with($ip)
        ->andReturn($position);

    $this->getJson('/_test/ip-resolution', ['REMOTE_ADDR' => $ip])
        ->assertOk()
        ->assertJson([
            'ip_location' => [
                'ip' => $ip,
                'countryCode' => 'US',
            ],
        ]);

    $this->getJson('/_test/ip-resolution', ['REMOTE_ADDR' => $ip])
        ->assertOk()
        ->assertJson([
            'ip_location' => [
                'countryCode' => 'US',
            ],
        ]);

    $request = new \Illuminate\Http\Request;
    $request->attributes->set('ip_location', (object) ['countryCode' => 'US']);
    $this->assertEquals('US', $request->ipLocation('countryCode'));
    $this->assertEquals('Default', $request->ipLocation('invalid', 'Default'));
});

it('resolves ip from cloudflare header', function () {
    $cloudflareEdgeIp = '104.18.0.1';
    $realClientIp = '203.0.113.5';

    $position = new \Stevebauman\Location\Position;
    $position->countryCode = 'AU';

    \Stevebauman\Location\Facades\Location::shouldReceive('get')
        ->once()
        ->with($realClientIp)
        ->andReturn($position);

    $this->getJson('/_test/ip-resolution', [
        'REMOTE_ADDR' => $cloudflareEdgeIp,
        'HTTP_CF_CONNECTING_IP' => $realClientIp,
    ])
        ->assertOk()
        ->assertJson([
            'ip_location' => [
                'countryCode' => 'AU',
            ],
        ]);
});

it('ignores cf connecting ip when remote addr is not cloudflare', function () {
    $attackerIp = '203.0.113.99';
    $spoofedIp = '8.8.8.8';

    $position = new \Stevebauman\Location\Position;
    $position->countryCode = 'XX';

    \Stevebauman\Location\Facades\Location::shouldReceive('get')
        ->once()
        ->with($attackerIp)
        ->andReturn($position);

    \Stevebauman\Location\Facades\Location::shouldReceive('get')
        ->with($spoofedIp)
        ->never();

    $this->getJson('/_test/ip-resolution', [
        'REMOTE_ADDR' => $attackerIp,
        'HTTP_CF_CONNECTING_IP' => $spoofedIp,
    ])->assertOk();
});
