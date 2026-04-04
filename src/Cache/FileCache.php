<?php

declare(strict_types=1);

namespace Vortex\Cache;

use JsonException;
use Vortex\Contracts\Cache;
use RuntimeException;

/**
 * File-backed cache: one `.cache` file per key (SHA-256 of prefixed key). Values are PHP-serialized.
 * Suitable for single-server deployments; use shared storage or a remote driver if you scale horizontally.
 */
final class FileCache implements Cache
{
    public function __construct(
        private readonly string $directory,
        private readonly string $prefix = '',
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $raw = $this->readRaw($key);
        if ($raw === null) {
            return $default;
        }

        return $raw['value'];
    }

    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        $expiresAt = $ttlSeconds === null ? null : time() + max(0, $ttlSeconds);
        $this->write($key, $value, $expiresAt);
    }

    public function add(string $key, mixed $value, int $ttlSeconds): bool
    {
        if ($this->readRaw($key) !== null) {
            return false;
        }

        $this->set($key, $value, max(1, $ttlSeconds));

        return true;
    }

    public function forget(string $key): void
    {
        $path = $this->path($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function clear(): void
    {
        if (! is_dir($this->directory)) {
            return;
        }
        foreach (glob($this->directory . '/*.cache') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    public function remember(string $key, ?int $ttlSeconds, callable $callback): mixed
    {
        $raw = $this->readRaw($key);
        if ($raw !== null) {
            return $raw['value'];
        }

        $value = $callback();
        $this->set($key, $value, $ttlSeconds);

        return $value;
    }

    /**
     * @return array{value: mixed}|null
     */
    private function readRaw(string $key): ?array
    {
        $path = $this->path($key);
        if (! is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false || $contents === '') {
            @unlink($path);

            return null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            @unlink($path);

            return null;
        }

        if (! is_array($decoded) || ! array_key_exists('e', $decoded) || ! array_key_exists('p', $decoded)) {
            @unlink($path);

            return null;
        }

        $expiresAt = $decoded['e'];
        if ($expiresAt !== null && (! is_int($expiresAt) || time() >= $expiresAt)) {
            @unlink($path);

            return null;
        }

        $payload = $decoded['p'];
        if (! is_string($payload)) {
            @unlink($path);

            return null;
        }

        $binary = base64_decode($payload, true);
        if ($binary === false) {
            @unlink($path);

            return null;
        }

        $decoded = @unserialize($binary, ['allowed_classes' => true]);
        if (! is_array($decoded) || ! array_key_exists('v', $decoded)) {
            @unlink($path);

            return null;
        }

        return ['value' => $decoded['v']];
    }

    /**
     * @param ?int $expiresAt Unix timestamp or null (no expiry)
     */
    private function write(string $key, mixed $value, ?int $expiresAt): void
    {
        $this->ensureDirectory();
        $serialized = serialize(['v' => $value]);
        $encoded = base64_encode($serialized);
        $json = json_encode(['e' => $expiresAt, 'p' => $encoded], JSON_THROW_ON_ERROR);
        $path = $this->path($key);
        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new RuntimeException("Could not write cache file: {$path}");
        }
    }

    private function ensureDirectory(): void
    {
        if (is_dir($this->directory)) {
            return;
        }
        if (! mkdir($this->directory, 0775, true) && ! is_dir($this->directory)) {
            throw new RuntimeException("Could not create cache directory: {$this->directory}");
        }
    }

    private function path(string $key): string
    {
        $hash = hash('sha256', $this->prefix . $key);

        return $this->directory . '/' . $hash . '.cache';
    }
}
