<?php

declare(strict_types=1);

namespace Vortex\Console;

use RuntimeException;

/**
 * Renders framework **`*.stub`** templates under **`Console/stubs/`** with {@code {{PLACEHOLDER}}} replaced by string values.
 */
final class Stub
{
    /**
     * @param array<string, string> $replacements keys match placeholder names (without {@code {{ }}})
     */
    public static function render(string $stubFileBasename, array $replacements): string
    {
        $path = __DIR__ . '/stubs/' . $stubFileBasename . '.stub';
        if (! is_file($path)) {
            throw new RuntimeException('Stub not found: ' . $stubFileBasename);
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Cannot read stub: ' . $path);
        }

        foreach ($replacements as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }
}
