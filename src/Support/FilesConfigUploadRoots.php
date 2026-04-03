<?php

declare(strict_types=1);

namespace Vortex\Support;

/**
 * Interprets {@code config/files.php} upload profiles for {@see \Vortex\Console\Commands\DoctorCommand}.
 *
 * Any top-level key whose value is an array with non-empty string {@code directory} is treated as an
 * upload root: relative segments are joined under {@code public/}; absolute paths must still resolve under {@code public/}.
 */
final class FilesConfigUploadRoots
{
    /**
     * @param array<string, mixed> $config
     *
     * @return list<array{profile: string, relative: string}>
     */
    public static function collect(array $config): array
    {
        $out = [];
        foreach ($config as $key => $value) {
            if (! is_string($key) || ! is_array($value)) {
                continue;
            }
            if ($key === 'max_upload_bytes') {
                continue;
            }
            $dir = $value['directory'] ?? null;
            if (! is_string($dir) || $dir === '') {
                continue;
            }
            $out[] = ['profile' => $key, 'relative' => $dir];
        }

        return $out;
    }

    public static function absolutePath(string $basePath, string $directoryFromConfig): string
    {
        if (str_starts_with($directoryFromConfig, '/')) {
            return $directoryFromConfig;
        }

        return PathHelp::join($basePath . '/public', $directoryFromConfig);
    }
}
