<?php

declare(strict_types=1);

namespace Vortex\Http;

use Vortex\Config\Repository;

final class Session
{
    private static ?self $instance = null;

    private bool $started = false;

    public static function setInstance(self $session): void
    {
        self::$instance = $session;
    }

    private static function resolved(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Session is not initialized; call Session::setInstance() from bootstrap.');
        }

        return self::$instance;
    }

    public static function start(): void
    {
        self::resolved()->ensureStarted();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::resolved()->read($key, $default);
    }

    public static function put(string $key, mixed $value): void
    {
        self::resolved()->write($key, $value);
    }

    public static function forget(string $key): void
    {
        self::resolved()->remove($key);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        return self::resolved()->take($key, $default);
    }

    public static function regenerate(): void
    {
        self::resolved()->renewId();
    }

    /**
     * One-request flash: one argument reads and consumes; two arguments write.
     */
    public static function flash(string $key, mixed $value = null): mixed
    {
        $s = self::resolved();

        return func_num_args() === 1 ? $s->readFlash($key) : $s->writeFlash($key, $value);
    }

    public static function flushAuth(): void
    {
        self::resolved()->clearAuth();
    }

    public static function authUserId(): ?int
    {
        return self::resolved()->readAuthUserId();
    }

    private function ensureStarted(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;

            return;
        }

        $name = (string) Repository::get('session.name', 'pc_session');
        $lifetime = (int) Repository::get('session.lifetime', 0);
        $secure = (bool) Repository::get('session.secure', false);
        $samesite = (string) Repository::get('session.samesite', 'Lax');
        $sameSiteEnum = match (strtolower($samesite)) {
            'none' => 'None',
            'strict' => 'Strict',
            default => 'Lax',
        };

        session_name($name);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => $sameSiteEnum,
        ]);

        session_start();
        $this->started = true;
    }

    private function read(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();

        return $_SESSION[$key] ?? $default;
    }

    private function write(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION[$key] = $value;
    }

    private function remove(string $key): void
    {
        $this->ensureStarted();
        unset($_SESSION[$key]);
    }

    private function take(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        $v = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);

        return $v;
    }

    private function renewId(): void
    {
        $this->ensureStarted();
        session_regenerate_id(true);
    }

    private function readFlash(string $key): mixed
    {
        $this->ensureStarted();
        $bucket = '_flash';
        if (! isset($_SESSION[$bucket]) || ! is_array($_SESSION[$bucket])) {
            $_SESSION[$bucket] = [];
        }
        /** @var array<string, mixed> $flash */
        $flash = &$_SESSION[$bucket];
        $v = $flash[$key] ?? null;
        unset($flash[$key]);

        return $v;
    }

    private function writeFlash(string $key, mixed $value): mixed
    {
        $this->ensureStarted();
        $bucket = '_flash';
        if (! isset($_SESSION[$bucket]) || ! is_array($_SESSION[$bucket])) {
            $_SESSION[$bucket] = [];
        }
        /** @var array<string, mixed> $flash */
        $flash = &$_SESSION[$bucket];
        $flash[$key] = $value;

        return $value;
    }

    private function clearAuth(): void
    {
        $this->ensureStarted();
        $this->remove('auth_user_id');
        $this->renewId();
    }

    /**
     * Normalized logged-in user id from session, or null if missing or invalid.
     */
    private function readAuthUserId(): ?int
    {
        $this->ensureStarted();
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
