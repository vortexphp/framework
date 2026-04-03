<?php

declare(strict_types=1);

namespace Vortex\Crypto;

use Vortex\Support\Env;

/**
 * Keyed hashing (HMAC) and verification using {@code APP_KEY} from the environment.
 *
 * Set {@code APP_KEY} in {@code .env}. Recommended: {@code base64:} plus 32 random bytes
 * ({@code php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"}).
 *
 * For **user passwords**, use {@see Password} instead ({@see password_hash()}), not HMAC.
 *
 * @see Password
 * @see SecurityHelp::namespaceGuide()
 */
final class Crypt
{
    /**
     * HMAC of {@code $value} with the application secret. Returns lowercase hex.
     *
     * @param non-empty-string $algorithm Any algorithm name accepted by {@see hash_hmac()}
     */
    public static function hmac(string $value, string $algorithm = 'sha256'): string
    {
        $key = self::rawKey();

        return bin2hex(hash_hmac($algorithm, $value, $key, true));
    }

    /**
     * Same as {@see self::hmac()} with SHA-256.
     */
    public static function hash(string $value): string
    {
        return self::hmac($value, 'sha256');
    }

    /**
     * Constant-time check that {@code $hexMac} is the HMAC hex for {@code $value}.
     *
     * @param non-empty-string $algorithm Must match the algorithm used when creating {@code $hexMac}
     */
    public static function verify(string $value, string $hexMac, string $algorithm = 'sha256'): bool
    {
        if ($hexMac === '' || ! self::isHexEvenLength($hexMac)) {
            return false;
        }

        $expectedBin = @hex2bin($hexMac);
        if ($expectedBin === false) {
            return false;
        }

        $key = self::rawKey();
        $actualBin = hash_hmac($algorithm, $value, $key, true);

        return hash_equals($actualBin, $expectedBin);
    }

    /**
     * Raw secret bytes derived from {@code APP_KEY}.
     *
     * @internal
     */
    public static function rawKey(): string
    {
        $key = Env::get('APP_KEY');
        if ($key === null || $key === '') {
            throw new \RuntimeException(
                'APP_KEY is not set. Define APP_KEY in .env (see .env.example).',
            );
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded === false || $decoded === '') {
                throw new \RuntimeException('APP_KEY is invalid: base64 payload could not be decoded.');
            }

            return $decoded;
        }

        return $key;
    }

    private static function isHexEvenLength(string $hex): bool
    {
        $len = strlen($hex);

        return $len > 0 && ($len & 1) === 0 && ctype_xdigit($hex);
    }
}
