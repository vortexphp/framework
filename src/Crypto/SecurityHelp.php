<?php

declare(strict_types=1);

namespace Vortex\Crypto;

/**
 * Short reminders for developers: which Crypto API to use. Printed by {@code php power doctor}.
 */
final class SecurityHelp
{
    /**
     * @return list<string>
     */
    public static function namespaceGuide(): array
    {
        return [
            'Password::hash / Password::verify — user passwords (slow salted hashes via password_hash).',
            'Crypt::hash / Crypt::verify — HMAC with APP_KEY for tokens, signed payloads, tamper checks — not for passwords.',
        ];
    }
}
