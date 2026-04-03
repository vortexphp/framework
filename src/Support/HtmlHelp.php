<?php

declare(strict_types=1);

namespace Vortex\Support;

/**
 * HTML beyond escaping ({@see e()}): allowlisted tags and plain excerpts.
 */
final class HtmlHelp
{
    /**
     * @param list<string> $allowedTags tag names without brackets, e.g. {@code ['p','br','a']}
     */
    public static function stripTags(string $html, array $allowedTags = []): string
    {
        if ($allowedTags === []) {
            return strip_tags($html);
        }

        $allowed = '<' . implode('><', array_map(static fn (string $t): string => strtolower(trim($t)), $allowedTags)) . '>';

        return strip_tags($html, $allowed);
    }

    /**
     * Strip tags, collapse whitespace, then truncate (uses {@see StringHelp::limit}).
     */
    public static function excerpt(string $html, int $limit, string $end = '...'): string
    {
        $plain = self::stripTags($html);
        $plain = StringHelp::squish($plain);

        return StringHelp::limit($plain, $limit, $end);
    }
}
