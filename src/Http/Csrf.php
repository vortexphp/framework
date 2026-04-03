<?php

declare(strict_types=1);

namespace Vortex\Http;

final class Csrf
{
    private static ?self $instance = null;

    private const SESSION_KEY = '_csrf_token';

    public static function setInstance(self $csrf): void
    {
        self::$instance = $csrf;
    }

    private static function resolved(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Csrf is not initialized; call Csrf::setInstance() from bootstrap.');
        }

        return self::$instance;
    }

    public static function token(): string
    {
        return self::resolved()->issueToken();
    }

    public static function validate(): bool
    {
        return self::resolved()->checkRequest();
    }

    private function issueToken(): string
    {
        Session::start();
        if (! isset($_SESSION[self::SESSION_KEY]) || ! is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    private function checkRequest(): bool
    {
        Session::start();
        $expected = $_SESSION[self::SESSION_KEY] ?? '';
        if (! is_string($expected) || $expected === '') {
            return false;
        }

        $given = Request::input('_csrf');
        if (! is_string($given)) {
            return false;
        }

        return hash_equals($expected, $given);
    }
}
