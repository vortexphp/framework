<?php

declare(strict_types=1);

namespace Vortex\Support;

use InvalidArgumentException;
use Redis;
use RuntimeException;

/**
 * Shared phpredis ({@see Redis}) connection from driver config arrays (cache Redis store, queue.redis, etc.).
 */
final class PhpRedisConnect
{
    /**
     * @param array<string, mixed> $cfg
     */
    public static function connect(array $cfg): Redis
    {
        if (! class_exists(Redis::class)) {
            throw new InvalidArgumentException('The redis driver requires the phpredis extension (ext-redis).');
        }

        $redis = new Redis();
        $host = isset($cfg['host']) && is_string($cfg['host']) && $cfg['host'] !== '' ? $cfg['host'] : '127.0.0.1';
        $port = isset($cfg['port']) && is_numeric($cfg['port']) ? (int) $cfg['port'] : 6379;
        $timeout = isset($cfg['timeout']) && is_numeric($cfg['timeout']) ? (float) $cfg['timeout'] : 0.0;
        $persistent = (bool) ($cfg['persistent'] ?? false);

        if ($persistent) {
            if (! $redis->pconnect($host, $port, $timeout)) {
                throw new RuntimeException('Redis pconnect failed for ' . $host . ':' . $port . '.');
            }
        } elseif (! $redis->connect($host, $port, $timeout)) {
            throw new RuntimeException('Redis connect failed for ' . $host . ':' . $port . '.');
        }

        $readTimeout = $cfg['read_timeout'] ?? null;
        if (is_numeric($readTimeout)) {
            $redis->setOption(Redis::OPT_READ_TIMEOUT, (float) $readTimeout);
        }

        $password = $cfg['password'] ?? $cfg['auth'] ?? null;
        if (is_string($password) && $password !== '') {
            if ($redis->auth($password) === false) {
                throw new RuntimeException('Redis AUTH failed.');
            }
        }

        $db = 0;
        if (isset($cfg['database']) && is_numeric($cfg['database'])) {
            $db = (int) $cfg['database'];
        } elseif (isset($cfg['db']) && is_numeric($cfg['db'])) {
            $db = (int) $cfg['db'];
        }
        if ($db !== 0 && $redis->select($db) === false) {
            throw new RuntimeException('Redis SELECT failed for database ' . $db . '.');
        }

        return $redis;
    }
}
