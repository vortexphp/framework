<?php

declare(strict_types=1);

namespace Vortex\Http;

interface SessionStore
{
    public function start(): void;

    public function get(string $key, mixed $default = null): mixed;

    public function put(string $key, mixed $value): void;

    public function forget(string $key): void;

    public function pull(string $key, mixed $default = null): mixed;

    public function regenerate(): void;

    public function flashGet(string $key): mixed;

    public function flashPut(string $key, mixed $value): mixed;

    public function flushAuth(): void;

    public function authUserId(): ?int;
}
