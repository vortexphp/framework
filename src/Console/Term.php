<?php

declare(strict_types=1);

namespace Vortex\Console;

final class Term
{
    /**
     * @param non-empty-string $seq SGR parameter(s), e.g. "1;32"
     */
    public static function style(string $seq, string $text): string
    {
        if (! self::stderrColorEnabled()) {
            return $text;
        }

        return "\033[{$seq}m{$text}\033[0m";
    }

    public static function stderrColorEnabled(): bool
    {
        $noColor = getenv('NO_COLOR');
        if ($noColor !== false && $noColor !== '') {
            return false;
        }

        if (getenv('TERM') === 'dumb') {
            return false;
        }

        return function_exists('stream_isatty') && @stream_isatty(STDERR);
    }
}
