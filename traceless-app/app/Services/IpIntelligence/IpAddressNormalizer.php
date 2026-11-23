<?php

namespace App\Services\IpIntelligence;

/**
 * Normalizes visitor IP addresses into the cache key format.
 *
 * Only publicly routable addresses are analyzable. Private, loopback, link-local
 * and reserved ranges are rejected so a development session or a spoofed header
 * cannot queue provider calls or pollute the cache.
 */
class IpAddressNormalizer
{
    public static function normalize(string $ip): ?string
    {
        $ip = trim($ip);

        if ($ip === '') {
            return null;
        }

        // A zone identifier (fe80::1%eth0) is only valid for link-local names.
        if (str_contains($ip, '%')) {
            return null;
        }

        // IPv4-mapped IPv6 is rejected as reserved by PHP's filter; unwrap it.
        if (stripos($ip, '::ffff:') === 0) {
            $candidate = substr($ip, 7);

            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                $ip = $candidate;
            }
        }

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }

        $packed = @inet_pton($ip);

        if ($packed === false) {
            return null;
        }

        $normalized = @inet_ntop($packed);

        return $normalized === false ? null : $normalized;
    }

    public static function version(string $normalizedIp): int
    {
        return str_contains($normalizedIp, ':') ? 6 : 4;
    }
}
