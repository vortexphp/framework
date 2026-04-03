<?php

declare(strict_types=1);

namespace Vortex\Http;

final class NullSessionStore implements SessionStore
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function start(): void
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->data[$key] ?? $default;
        unset($this->data[$key]);

        return $value;
    }

    public function regenerate(): void
    {
    }

    public function flashGet(string $key): mixed
    {
        $bucket = '_flash';
        if (! isset($this->data[$bucket]) || ! is_array($this->data[$bucket])) {
            $this->data[$bucket] = [];
        }
        /** @var array<string, mixed> $flash */
        $flash = &$this->data[$bucket];
        $value = $flash[$key] ?? null;
        unset($flash[$key]);

        return $value;
    }

    public function flashPut(string $key, mixed $value): mixed
    {
        $bucket = '_flash';
        if (! isset($this->data[$bucket]) || ! is_array($this->data[$bucket])) {
            $this->data[$bucket] = [];
        }
        /** @var array<string, mixed> $flash */
        $flash = &$this->data[$bucket];
        $flash[$key] = $value;

        return $value;
    }

    public function flushAuth(): void
    {
        $this->forget('auth_user_id');
    }

    public function authUserId(): ?int
    {
        $uid = $this->data['auth_user_id'] ?? null;
        if (is_int($uid)) {
            return $uid;
        }
        if (is_string($uid) && ctype_digit($uid)) {
            return (int) $uid;
        }

        return null;
    }
}
