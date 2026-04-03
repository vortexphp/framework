<?php

declare(strict_types=1);

namespace Vortex\Crypto;

/**
 * One-way hashing for **user passwords** (and similar secrets at rest).
 *
 * Uses PHP’s {@see password_hash()} with {@see PASSWORD_DEFAULT} (bcrypt or Argon2id, per PHP build):
 * per-password salt, adaptive cost — the right tool for credentials.
 *
 * Do **not** use this for keyed MACs or signed tokens. For HMAC with {@code APP_KEY}, use {@see Crypt}.
 *
 * @see Crypt
 * @see SecurityHelp::namespaceGuide()
 */
final class Password
{
    public static function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    public static function verify(string $plain, string $hash): bool
    {
        return $hash !== '' && password_verify($plain, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return $hash !== '' && password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
}
