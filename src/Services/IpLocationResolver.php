<?php

namespace Foundry\Services;

use Illuminate\Http\Request;
use Stevebauman\Location\Facades\Location;

class IpLocationResolver
{
    /**
     * Known Cloudflare IPv4 CIDR ranges.
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
     * Resolve the location for a given request.
     */
    public function resolve(Request $request): ?IpLocation
    {
        try {
            $ip = $this->resolveIp($request);

            if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP)) {
                return null;
            }

            $position = Location::get($ip);
            if ($position && is_object($position)) {
                return IpLocation::fromSource($position);
            }

            return null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Resolve the Cloudflare-safe client IP.
     */
    public function resolveIp(Request $request): ?string
    {
        $remoteIp = $request->server('REMOTE_ADDR');
        $cfHeader = $request->header('CF-Connecting-IP');

        $isCloudflare = false;
        if ($remoteIp && filter_var($remoteIp, FILTER_VALIDATE_IP)) {
            foreach (self::CLOUDFLARE_CIDRS as $cidr) {
                if ($this->ipInCidr($remoteIp, $cidr)) {
                    $isCloudflare = true;
                    break;
                }
            }
        }

        return ($cfHeader && $isCloudflare) ? $cfHeader : $request->ip();
    }

    /**
     * Check whether an IP falls within a CIDR range.
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $mask = $bits < 32 ? (~0 << (32 - (int) $bits)) : ~0;

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
