<?php

namespace Tests\Unit;

use App\Support\PublicAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PublicAddressTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function publicAddresses(): array
    {
        return [
            'IPv4' => ['93.184.216.34'],
            'IPv4 just past 172.16/12' => ['172.32.0.1'],
            'IPv6' => ['2606:4700:4700::1111'],
            'NAT64 of a public IPv4' => ['64:ff9b::5db8:d822'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function nonPublicAddresses(): array
    {
        return [
            'loopback' => ['127.0.0.1'],
            'private 10/8' => ['10.1.2.3'],
            'private 172.16/12' => ['172.20.0.1'],
            'private 192.168/16' => ['192.168.0.10'],
            'link-local cloud metadata' => ['169.254.169.254'],
            'carrier-grade NAT' => ['100.64.0.1'],
            'this network' => ['0.0.0.0'],
            'broadcast' => ['255.255.255.255'],
            'multicast' => ['224.0.0.251'],
            'benchmarking' => ['198.18.0.1'],
            'IPv6 loopback' => ['::1'],
            'IPv6 unspecified' => ['::'],
            'IPv6 unique local' => ['fd00::1'],
            'IPv6 link-local' => ['fe80::1'],
            'IPv6 multicast' => ['ff02::1'],
            'IPv4-mapped loopback' => ['::ffff:127.0.0.1'],
            'IPv4-mapped private' => ['::ffff:10.0.0.1'],
            'NAT64 of loopback' => ['64:ff9b::7f00:1'],
            '6to4 of loopback' => ['2002:7f00:1::1'],
            'a hostname' => ['cdn.example.com'],
            'empty' => [''],
        ];
    }

    #[DataProvider('publicAddresses')]
    public function test_public_addresses_are_fetchable(string $ip): void
    {
        $this->assertTrue(PublicAddress::isPublic($ip));
    }

    #[DataProvider('nonPublicAddresses')]
    public function test_everything_else_is_refused(string $ip): void
    {
        $this->assertFalse(PublicAddress::isPublic($ip));
    }
}
