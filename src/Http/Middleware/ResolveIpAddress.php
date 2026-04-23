<?php

namespace Foundry\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Stevebauman\Location\Facades\Location;

class ResolveIpAddress
{
    /**
     * Known Cloudflare IPv4 CIDR ranges.
     * Keep these in sync with https://www.cloudflare.com/ips/
     */
    private const CLOUDFLARE_CIDRS = [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
    ];

    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // 1. Resolve IP — only trust CF-Connecting-IP when the direct peer is
        //    a genuine Cloudflare edge node, preventing header spoofing attacks.
        $remoteIp = $request->server('REMOTE_ADDR');
        $cfHeader = $request->header('CF-Connecting-IP');

        $ip = ($cfHeader && $this->isCloudflareIp($remoteIp))
            ? $cfHeader
            : $request->ip();

        // 2. Resolve & Cache Location
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
            try {
                // Cache for 24 hours (86400 seconds)
                $location = Cache::remember("location.{$ip}", 86400, function () use ($ip) {
                    return Location::get($ip);
                });

                if ($location) {
                    // 3. Inject into request attributes
                    $request->attributes->set('ip_location', $location);
                }
            } catch (\Throwable $e) {
                // Silently fail if location service is down
            }
        }

        return $next($request);
    }

    /**
     * Check whether the given IP address falls within any Cloudflare CIDR range.
     */
    protected function isCloudflareIp(?string $ip): bool
    {
        if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        foreach (self::CLOUDFLARE_CIDRS as $cidr) {
            if ($this->ipInCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether $ip is contained in $cidr.
     */
    protected function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = $bits < 32 ? (~0 << (32 - (int) $bits)) : ~0;

        return ($ip & $mask) === ($subnet & $mask);
    }
}
