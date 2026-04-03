<?php

declare(strict_types=1);

namespace Vortex\Http;

final class NativeSessionStore implements SessionStore
{
    private bool $started = false;

    public function __construct(
        private readonly string $name = 'vortex _session',
        private readonly int $lifetime = 0,
        private readonly bool $secure = false,
        private readonly string $sameSite = 'Lax',
    ) {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;

            return;
        }

        session_name($this->name);
        session_set_cookie_params([
            'lifetime' => $this->lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => Cookie::normalizedSameSite($this->sameSite),
        ]);

        session_start();
        $this->started = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $this->start();
        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);

        return $value;
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    public function flashGet(string $key): mixed
    {
        $this->start();
        $bucket = '_flash';
        if (! isset($_SESSION[$bucket]) || ! is_array($_SESSION[$bucket])) {
            $_SESSION[$bucket] = [];
        }
        /** @var array<string, mixed> $flash */
        $flash = &$_SESSION[$bucket];
        $value = $flash[$key] ?? null;
        unset($flash[$key]);

        return $value;
    }

    public function flashPut(string $key, mixed $value): mixed
    {
        $this->start();
        $bucket = '_flash';
        if (! isset($_SESSION[$bucket]) || ! is_array($_SESSION[$bucket])) {
            $_SESSION[$bucket] = [];
        }
        /** @var array<string, mixed> $flash */
        $flash = &$_SESSION[$bucket];
        $flash[$key] = $value;

        return $value;
    }

    public function flushAuth(): void
    {
        $this->start();
        $this->forget('auth_user_id');
        $this->regenerate();
    }

    public function authUserId(): ?int
    {
        $this->start();
        $uid = $_SESSION['auth_user_id'] ?? null;
        if (is_int($uid)) {
            return $uid;
        }
        if (is_string($uid) && ctype_digit($uid)) {
            return (int) $uid;
        }

        return null;
    }
}
