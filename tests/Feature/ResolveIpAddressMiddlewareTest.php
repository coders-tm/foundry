<?php

use Foundry\Http\Middleware\ResolveIpAddress;
use Foundry\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;

uses(TestCase::class);

beforeEach(function () {
    Route::middleware(ResolveIpAddress::class)->get('/_test/ip-resolution', function () {
        return response()->json([
            'ip_location' => request()->attributes->get('ip_location'),
        ]);
    });
});

it('resolves ip location', function () {
    $ip = '8.8.8.8';
    $position = new Position;
    $position->ip = $ip;
    $position->countryCode = 'US';
    $position->countryName = 'United States';

    Location::shouldReceive('get')
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

    $request = new Request;
    $request->attributes->set('ip_location', (object) ['countryCode' => 'US']);
    expect($request->ipLocation('countryCode'))->toEqual('US');
    expect($request->ipLocation('invalid', 'Default'))->toEqual('Default');
});

it('resolves ip from cloudflare header', function () {
    $cloudflareEdgeIp = '104.18.0.1';
    $realClientIp = '203.0.113.5';

    $position = new Position;
    $position->countryCode = 'AU';

    Location::shouldReceive('get')
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

    $position = new Position;
    $position->countryCode = 'XX';

    Location::shouldReceive('get')
        ->once()
        ->with($attackerIp)
        ->andReturn($position);

    Location::shouldReceive('get')
        ->with($spoofedIp)
        ->never();

    $this->getJson('/_test/ip-resolution', [
        'REMOTE_ADDR' => $attackerIp,
        'HTTP_CF_CONNECTING_IP' => $spoofedIp,
    ])->assertOk();
});
