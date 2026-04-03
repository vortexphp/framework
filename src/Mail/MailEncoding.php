<?php

declare(strict_types=1);

namespace Vortex\Mail;

/**
 * RFC 2047 / address header helpers (UTF-8).
 */
final class MailEncoding
{
    /**
     * @param array{0: string, 1?: string|null} $pair
     */
    public static function formatAddress(array $pair): string
    {
        $email = $pair[0];
        $name = isset($pair[1]) && is_string($pair[1]) && $pair[1] !== ''
            ? $pair[1]
            : null;
        if ($name === null) {
            return $email;
        }

        return sprintf('"%s" <%s>', self::escapeDisplayName($name), $email);
    }

    public static function encodeSubject(string $subject): string
    {
        if (preg_match('/[^\x20-\x7E]/', $subject) === 1) {
            return '=?UTF-8?B?' . base64_encode($subject) . '?=';
        }

        return $subject;
    }

    private static function escapeDisplayName(string $name): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $name);
    }
}
