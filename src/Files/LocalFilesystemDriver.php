<?php

declare(strict_types=1);

namespace Vortex\Files;

use InvalidArgumentException;
use RuntimeException;
use Vortex\Contracts\Filesystem;
use Vortex\Support\PathHelp;

/**
 * Local directory driver (single root, relative paths only, no {@code ..}).
 */
final class LocalFilesystemDriver implements Filesystem
{
    public function __construct(
        private readonly string $rootAbsolute,
    ) {
    }

    public function put(string $path, string $contents): void
    {
        $full = $this->prepareWritePath($path);
        if (file_put_contents($full, $contents) === false) {
            throw new RuntimeException('Cannot write file: ' . $full);
        }
    }

    public function append(string $path, string $contents): void
    {
        $full = $this->prepareWritePath($path);
        if (file_put_contents($full, $contents, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Cannot append to file: ' . $full);
        }
    }

    public function get(string $path): ?string
    {
        $full = $this->absoluteSafe($path);
        if (! is_file($full)) {
            return null;
        }
        if (! $this->fileIsInsideRoot($full)) {
            return null;
        }
        $c = file_get_contents($full);

        return $c === false ? null : $c;
    }

    public function exists(string $path): bool
    {
        try {
            $full = $this->absoluteSafe($path);
        } catch (InvalidArgumentException) {
            return false;
        }

        return is_file($full) && $this->fileIsInsideRoot($full);
    }

    public function delete(string $path): void
    {
        try {
            $full = $this->absoluteSafe($path);
        } catch (InvalidArgumentException) {
            return;
        }
        if (is_file($full) && $this->fileIsInsideRoot($full)) {
            @unlink($full);
        }
    }

    public function root(): string
    {
        return $this->rootAbsolute;
    }

    private function sanitizeRelative(string $relative): string
    {
        $relative = str_replace("\0", '', $relative);
        $relative = str_replace('\\', '/', $relative);
        $relative = trim($relative, '/');
        if ($relative === '' || str_contains($relative, '..')) {
            throw new InvalidArgumentException('Invalid storage path.');
        }

        return $relative;
    }

    private function absoluteSafe(string $relative): string
    {
        $relative = $this->sanitizeRelative($relative);

        return PathHelp::join($this->rootAbsolute, $relative);
    }

    private function prepareWritePath(string $relativePath): string
    {
        $full = $this->absoluteSafe($relativePath);
        $dir = dirname($full);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('Cannot create directory: ' . $dir);
        }

        $root = $this->rootAbsolute;
        if (! is_dir($root)) {
            @mkdir($root, 0775, true);
        }

        $rootReal = realpath($root);
        $dirReal = realpath($dir);
        if ($rootReal === false || $dirReal === false) {
            throw new RuntimeException('Invalid storage root.');
        }
        if (! PathHelp::isBelowBase($rootReal, $dirReal) && $dirReal !== $rootReal) {
            throw new RuntimeException('Path escapes storage root.');
        }

        return $full;
    }

    private function fileIsInsideRoot(string $absoluteFile): bool
    {
        $rootReal = realpath($this->rootAbsolute);
        $fileReal = realpath($absoluteFile);
        if ($rootReal === false || $fileReal === false) {
            return false;
        }

        return PathHelp::isBelowBase($rootReal, $fileReal) || $fileReal === $rootReal;
    }
}
