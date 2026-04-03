<?php

declare(strict_types=1);

use Vortex\I18n\Translator;

/**
 * Translate a line (dot key). Requires {@see Translator::setInstance()} from bootstrap.
 *
 * @param array<string, string|int|float> $replace
 */
function trans(string $key, array $replace = []): string
{
    return Translator::get($key, $replace);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * URL path for a file stored under {@code public/} (leading slash).
 */
function public_url(string $relative): string
{
    return '/' . ltrim(str_replace("\0", '', $relative), '/');
}
