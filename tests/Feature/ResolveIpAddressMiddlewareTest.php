<?php

namespace Tests\Feature;

use Foundry\Http\Middleware\ResolveIpAddress;
use Foundry\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;

class ResolveIpAddressMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Define a test route using the middleware
        Route::middleware(ResolveIpAddress::class)->get('/_test/ip-resolution', function () {
            return response()->json([
                'ip_location' => request()->attributes->get('ip_location'),
            ]);
        });
    }

    #[Test]
    public function it_resolves_ip_location()
    {
        $ip = '8.8.8.8';
        $position = new Position;
        $position->ip = $ip;
        $position->countryCode = 'US';
        $position->countryName = 'United States';

        // Mock Location service to expect two calls (one per request, as caching is disabled)
        Location::shouldReceive('get')
            ->twice()
            ->with($ip)
            ->andReturn($position);

        // First request: should call Location service
        $this->getJson('/_test/ip-resolution', ['REMOTE_ADDR' => $ip])
            ->assertOk()
            ->assertJson([
                'ip_location' => [
                    'ip' => $ip,
                    'countryCode' => 'US',
                ],
            ]);

        // Second request: should call Location service again
        $this->getJson('/_test/ip-resolution', ['REMOTE_ADDR' => $ip])
            ->assertOk()
            ->assertJson([
                'ip_location' => [
                    'countryCode' => 'US',
                ],
            ]);

        // Verify macro usage
        $request = new Request;
        $request->attributes->set('ip_location', (object) ['countryCode' => 'US']);
        $this->assertEquals('US', $request->ipLocation('countryCode'));
        $this->assertEquals('Default', $request->ipLocation('invalid', 'Default'));
    }

    #[Test]
    public function it_resolves_ip_from_cloudflare_header()
    {
        // 1.1.1.1 is a Cloudflare IP (104.16.0.0/13 range used for edge nodes).
        // We simulate: REMOTE_ADDR = a real Cloudflare edge IP, CF-Connecting-IP = real client IP.
        $cloudflareEdgeIp = '104.18.0.1'; // falls inside 104.16.0.0/13
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
    }

    #[Test]
    public function it_ignores_cf_connecting_ip_when_remote_addr_is_not_cloudflare()
    {
        // Attacker sends a spoofed CF-Connecting-IP from a non-Cloudflare IP.
        $attackerIp = '203.0.113.99';
        $spoofedIp = '8.8.8.8';

        $position = new Position;
        $position->countryCode = 'XX'; // should never be used

        // Location::get should be called with the REAL remote addr, not the spoofed one
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
    }
}
