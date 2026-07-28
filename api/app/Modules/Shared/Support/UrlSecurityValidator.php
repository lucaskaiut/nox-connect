<?php

namespace App\Modules\Shared\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Valida URLs outbound contra SSRF (localhost, RFC1918, link-local, metadata, non-HTTPS).
 */
final class UrlSecurityValidator
{
    /** @var list<string> */
    private const BLOCKED_HOSTS = [
        'localhost',
        'localhost.localdomain',
        'metadata.google.internal',
    ];

    public function assertSafe(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('URL inválida.');
        }

        if (strtolower($parts['scheme']) !== 'https') {
            throw new InvalidArgumentException('A URL do webhook deve usar HTTPS.');
        }

        $host = strtolower($parts['host']);

        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            throw new InvalidArgumentException('A URL do webhook aponta para um host não permitido.');
        }

        if ($this->isBlockedIp($host)) {
            throw new InvalidArgumentException('A URL do webhook aponta para um endereço IP não permitido.');
        }

        $resolved = $this->resolveHost($host);

        foreach ($resolved as $ip) {
            if ($this->isBlockedIp($ip)) {
                throw new InvalidArgumentException('A URL do webhook resolve para um endereço IP não permitido.');
            }
        }
    }

    /**
     * @return list<string>
     */
    public function resolveHost(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false || $records === []) {
            throw new InvalidArgumentException('Não foi possível resolver o host da URL do webhook.');
        }

        $ips = [];

        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $ips[] = $record['ip'];
            }

            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    public function isBlockedIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->isBlockedIpv4($ip);
        }

        return $this->isBlockedIpv6($ip);
    }

    private function isBlockedIpv4(string $ip): bool
    {
        $long = ip2long($ip);

        if ($long === false) {
            return true;
        }

        $ranges = [
            ['0.0.0.0', '0.255.255.255'],
            ['10.0.0.0', '10.255.255.255'],
            ['127.0.0.0', '127.255.255.255'],
            ['169.254.0.0', '169.254.255.255'],
            ['172.16.0.0', '172.31.255.255'],
            ['192.0.0.0', '192.0.0.255'],
            ['192.168.0.0', '192.168.255.255'],
            ['100.64.0.0', '100.127.255.255'],
        ];

        foreach ($ranges as [$start, $end]) {
            if ($long >= ip2long($start) && $long <= ip2long($end)) {
                return true;
            }
        }

        return false;
    }

    private function isBlockedIpv6(string $ip): bool
    {
        $normalized = strtolower($ip);

        if ($normalized === '::1') {
            return true;
        }

        if (Str::startsWith($normalized, 'fe80:') || Str::startsWith($normalized, 'fc') || Str::startsWith($normalized, 'fd')) {
            return true;
        }

        if (Str::startsWith($normalized, '::ffff:')) {
            $mapped = substr($normalized, 7);

            return $this->isBlockedIpv4($mapped);
        }

        return false;
    }
}
