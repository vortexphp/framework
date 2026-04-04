<?php

declare(strict_types=1);

namespace Vortex\Support;

use InvalidArgumentException;
use Memcached;

/**
 * Build a {@see Memcached} client from driver config arrays (cache memcached store).
 */
final class PhpMemcachedConnect
{
    /**
     * @param array<string, mixed> $cfg
     */
    public static function connect(array $cfg): Memcached
    {
        if (! class_exists(Memcached::class)) {
            throw new InvalidArgumentException('The memcached driver requires the memcached extension (ext-memcached).');
        }

        $persistentId = isset($cfg['persistent_id']) && is_string($cfg['persistent_id']) && $cfg['persistent_id'] !== ''
            ? $cfg['persistent_id']
            : '';

        $memcached = $persistentId !== '' ? new Memcached($persistentId) : new Memcached();
        if ($memcached->getServerList() !== []) {
            self::applySasl($memcached, $cfg);

            return $memcached;
        }

        $servers = $cfg['servers'] ?? null;
        if (is_array($servers) && $servers !== []) {
            $norm = [];
            foreach ($servers as $row) {
                if (! is_array($row) || ! isset($row[0]) || ! is_string($row[0]) || $row[0] === '') {
                    continue;
                }
                $port = isset($row[1]) && is_numeric($row[1]) ? (int) $row[1] : 11211;
                $weight = isset($row[2]) && is_numeric($row[2]) ? (int) $row[2] : 0;
                $norm[] = [$row[0], $port, $weight];
            }
            if ($norm !== []) {
                if ($memcached->addServers($norm) === false) {
                    throw new InvalidArgumentException('Memcached addServers failed.');
                }
            }
        }

        if ($memcached->getServerList() === []) {
            $host = isset($cfg['host']) && is_string($cfg['host']) && $cfg['host'] !== '' ? $cfg['host'] : '127.0.0.1';
            $port = isset($cfg['port']) && is_numeric($cfg['port']) ? (int) $cfg['port'] : 11211;
            if ($memcached->addServer($host, $port) === false) {
                throw new InvalidArgumentException("Memcached addServer failed for {$host}:{$port}.");
            }
        }

        self::applySasl($memcached, $cfg);

        return $memcached;
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private static function applySasl(Memcached $memcached, array $cfg): void
    {
        $user = $cfg['sasl_user'] ?? $cfg['username'] ?? null;
        $pass = $cfg['sasl_password'] ?? $cfg['password'] ?? null;
        if (is_string($user) && $user !== '' && is_string($pass)) {
            $memcached->setSaslAuthData($user, $pass);
        }
    }
}
