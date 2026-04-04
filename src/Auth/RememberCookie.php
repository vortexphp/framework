<?php

declare(strict_types=1);

namespace Vortex\Auth;

use JsonException;
use Vortex\Crypto\Crypt;
use Vortex\Http\Cookie;

/**
 * Stateless signed “remember me” cookie using {@see Crypt} (requires {@code APP_KEY}).
 */
final class RememberCookie
{
    public static function cookieName(): string
    {
        return AuthConfig::rememberCookieName();
    }

    public static function queue(int $userId): void
    {
        $seconds = AuthConfig::rememberSeconds();
        $exp = time() + $seconds;
        $json = json_encode(['id' => $userId, 'exp' => $exp], JSON_THROW_ON_ERROR);
        $value = base64_encode($json) . '.' . Crypt::hash($json);
        Cookie::queue(self::buildCookie($value, $seconds));
    }

    public static function forget(): void
    {
        Cookie::queue(self::buildCookie('', 0));
    }

    /**
     * @return positive-int|null
     */
    public static function validate(string $rawValue): ?int
    {
        $rawValue = trim($rawValue);
        if ($rawValue === '' || ! str_contains($rawValue, '.')) {
            return null;
        }

        $dot = strrpos($rawValue, '.');
        if ($dot === false || $dot < 1) {
            return null;
        }

        $b64 = substr($rawValue, 0, $dot);
        $sig = substr($rawValue, $dot + 1);
        $json = base64_decode($b64, true);
        if ($json === false || $json === '') {
            return null;
        }

        if (! Crypt::verify($json, $sig)) {
            return null;
        }

        try {
            /** @var mixed $data */
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        $id = $data['id'] ?? null;
        $exp = $data['exp'] ?? null;
        if (! is_int($id) && ! (is_string($id) && ctype_digit((string) $id))) {
            return null;
        }

        $id = (int) $id;
        if ($id < 1) {
            return null;
        }

        if (! is_int($exp) || $exp < time()) {
            return null;
        }

        return $id;
    }

    private static function buildCookie(string $value, int $maxAge): Cookie
    {
        $secure = AuthConfig::cookieSecure();
        $sameSite = AuthConfig::cookieSameSite();

        return new Cookie(
            self::cookieName(),
            $value,
            '/',
            '',
            $maxAge > 0 ? $maxAge : 0,
            null,
            $secure,
            true,
            $sameSite,
        );
    }
}
