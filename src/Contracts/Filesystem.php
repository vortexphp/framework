<?php

declare(strict_types=1);

namespace Vortex\Contracts;

/**
 * Driver for a single storage disk (local path, null, …).
 */
interface Filesystem
{
    /**
     * Absolute filesystem root for this disk (empty for {@see \Vortex\Files\NullFilesystemDriver}).
     */
    public function root(): string;

    public function put(string $path, string $contents): void;

    public function append(string $path, string $contents): void;

    public function get(string $path): ?string;

    public function exists(string $path): bool;

    public function delete(string $path): void;
}
