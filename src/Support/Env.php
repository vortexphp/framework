<?php

declare(strict_types=1);

namespace Vortex\Support;

final class Env
{
    public static function load(string $path): void
    {
        if (! is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (! str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if ($name === '') {
                continue;
            }

            if (str_starts_with($value, '"') && str_ends_with($value, '"') && strlen($value) >= 2) {
                $value = stripcslashes(substr($value, 1, -1));
            } elseif (str_starts_with($value, "'") && str_ends_with($value, "'") && strlen($value) >= 2) {
                $value = substr($value, 1, -1);
            }

            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($v === false || $v === null || $v === '') {
            return $default;
        }

        return (string) $v;
    }
}
