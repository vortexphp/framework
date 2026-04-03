<?php

declare(strict_types=1);

namespace Vortex\Files;

use Vortex\Contracts\Filesystem;

/**
 * No-op disk: writes are discarded, reads return empty; useful for tests or disabled exports.
 */
final class NullFilesystemDriver implements Filesystem
{
    public function root(): string
    {
        return '';
    }

    public function put(string $path, string $contents): void
    {
    }

    public function append(string $path, string $contents): void
    {
    }

    public function get(string $path): ?string
    {
        return null;
    }

    public function exists(string $path): bool
    {
        return false;
    }

    public function delete(string $path): void
    {
    }
}
