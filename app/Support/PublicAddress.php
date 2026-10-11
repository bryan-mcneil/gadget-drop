<?php

namespace App\Support;

/**
 * Is an IP address one the server may fetch from on a URL someone else chose?
 * Pure (no facades) so it is unit-tested without booting Laravel. Used by
 * HlsProxyService, which fetches whatever a third-party playlist names.
 */
class PublicAddress
{
    public static function isPublic(string $ip): bool
    {
        $binary = filter_var($ip, FILTER_VALIDATE_IP) ? inet_pton($ip) : false;

        if ($binary === false) {
            return false;
        }

        // Private (RFC 1918, fc00::/7), reserved (loopback, link-local including the
        // 169.254.169.254 cloud metadata address, 0/8, 240/4) and everything else
        // IANA marks as not globally reachable (100.64/10, 198.18/15, ::ffff:0:0/96...).
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE)) {
            return false;
        }

        if (strlen($binary) === 4) {
            return (ord($binary[0]) & 0xF0) !== 0xE0; // 224.0.0.0/4 multicast, absent from PHP's lists
        }

        if ($binary[0] === "\xFF") {
            return false; // ff00::/8 multicast
        }

        // IPv6 forms that carry an IPv4 address must pass the IPv4 rules as well.
        $embedded = match (true) {
            str_starts_with($binary, "\x00\x64\xFF\x9B".str_repeat("\x00", 8)) => substr($binary, 12, 4), // 64:ff9b::/96 NAT64
            str_starts_with($binary, "\x20\x02") => substr($binary, 2, 4), // 2002::/16 6to4
            str_starts_with($binary, str_repeat("\x00", 10)) => substr($binary, 12, 4), // IPv4-compatible and IPv4-mapped
            default => null,
        };

        return $embedded === null || self::isPublic((string) inet_ntop($embedded));
    }
}
