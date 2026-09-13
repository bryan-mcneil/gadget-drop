<?php

namespace App\Support;

/**
 * Hostname to IP addresses, for HlsProxyService's public-address check. A class
 * rather than inline calls so feature tests can swap in fixed answers.
 */
class DnsResolver
{
    /**
     * @return list<string> IPv4 addresses first: shared hosting often has no outbound IPv6
     */
    public function resolve(string $host): array
    {
        $v4 = [];
        $v6 = [];

        // Silenced: a failed lookup raises a warning, and an empty answer is handled below.
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        foreach (is_array($records) ? $records : [] as $record) {
            if (isset($record['ip'])) {
                $v4[] = $record['ip'];
            } elseif (isset($record['ipv6'])) {
                $v6[] = $record['ipv6'];
            }
        }

        // dns_get_record() is unreliable on some platforms; fall back to the system resolver.
        if ($v4 === [] && $v6 === []) {
            $v4 = gethostbynamel($host) ?: [];
        }

        return array_values(array_unique([...$v4, ...$v6]));
    }
}
