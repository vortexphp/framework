<?php

declare(strict_types=1);

namespace Vortex\Files;

use Vortex\Contracts\PublicFilesystem;
use Vortex\Http\UploadedFile;

/**
 * {@code public/} disk: generic file ops plus {@see LocalPublicStorage} upload validation.
 */
final class LocalPublicFilesystemDriver implements PublicFilesystem
{
    private LocalFilesystemDriver $files;

    public function __construct()
    {
        $this->files = new LocalFilesystemDriver(LocalPublicStorage::publicRoot());
    }

    public function put(string $path, string $contents): void
    {
        $this->files->put($path, $contents);
    }

    public function append(string $path, string $contents): void
    {
        $this->files->append($path, $contents);
    }

    public function get(string $path): ?string
    {
        return $this->files->get($path);
    }

    public function exists(string $path): bool
    {
        return $this->files->exists($path);
    }

    public function delete(string $path): void
    {
        $this->files->delete($path);
    }

    /**
     * @param array<string, string> $mimeToExtension
     */
    public function storeUpload(
        UploadedFile $file,
        string $directoryRelativeToPublic,
        string $filenameStem,
        array $mimeToExtension,
        int $maxBytes,
    ): string {
        return LocalPublicStorage::storeUpload($file, $directoryRelativeToPublic, $filenameStem, $mimeToExtension, $maxBytes);
    }

    public function root(): string
    {
        return LocalPublicStorage::publicRoot();
    }
}
