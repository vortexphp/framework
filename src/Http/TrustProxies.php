<?php

declare(strict_types=1);

namespace Vortex\Http;

use Vortex\Support\Env;

/**
 * When the app sits behind a reverse proxy or CDN, mutates superglobals so
 * Request::capture() sees the original scheme and host (TLS termination).
 *
 * Configure TRUSTED_PROXIES in .env: comma-separated IPs, or * (only if PHP is
 * unreachable except through your proxy).
 */
final class TrustProxies
{
    public static function apply(): void
    {
        $raw = Env::get('TRUSTED_PROXIES', '');
        if ($raw === null || trim($raw) === '') {
            return;
        }

        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($remote === '' || ! self::isTrusted($remote, $raw)) {
            return;
        }

        $proto = self::firstForwardedValue($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
        if (strcasecmp($proto, 'https') === 0) {
            $_SERVER['HTTPS'] = 'on';
        } elseif (strcasecmp($proto, 'http') === 0) {
            unset($_SERVER['HTTPS']);
        }

        $host = self::firstForwardedValue($_SERVER['HTTP_X_FORWARDED_HOST'] ?? '');
        if ($host !== '') {
            $_SERVER['HTTP_HOST'] = $host;
            $_SERVER['SERVER_NAME'] = $host;
        }

        $port = self::firstForwardedValue($_SERVER['HTTP_X_FORWARDED_PORT'] ?? '');
        if ($port !== '' && ctype_digit($port)) {
            $_SERVER['SERVER_PORT'] = $port;
        }
    }

    private static function isTrusted(string $remoteAddr, string $configured): bool
    {
        $configured = trim($configured);
        if ($configured === '*') {
            return true;
        }

        foreach (explode(',', $configured) as $entry) {
            $ip = trim($entry);
            if ($ip !== '' && $ip === $remoteAddr) {
                return true;
            }
        }

        return false;
    }

    private static function firstForwardedValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $parts = explode(',', $value);

        return trim($parts[0]);
    }
}
