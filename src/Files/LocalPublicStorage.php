<?php

declare(strict_types=1);

namespace Vortex\Files;

use InvalidArgumentException;
use Vortex\Http\UploadedFile;
use RuntimeException;

/**
 * Stores uploads under the web {@code public/} tree and returns a path relative to {@code public/}
 * (e.g. {@code uploads/docs/1-a1b2c3d4.pdf}) suitable for URLs {@code /uploads/...}.
 */
final class LocalPublicStorage
{
    private static ?self $instance = null;

    public function __construct(
        private readonly string $publicRoot,
    ) {
    }

    public static function setInstance(self $storage): void
    {
        self::$instance = $storage;
    }

    public static function forgetInstance(): void
    {
        self::$instance = null;
    }

    private static function resolved(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('LocalPublicStorage is not initialized; call LocalPublicStorage::setInstance() from bootstrap.');
        }

        return self::$instance;
    }

    public static function publicRoot(): string
    {
        return self::resolved()->rootPath();
    }

    /**
     * @param array<string, string> $mimeToExtension allowed MIME types => extension without a leading dot
     */
    public static function storeUpload(
        UploadedFile $file,
        string $directoryRelativeToPublic,
        string $filenameStem,
        array $mimeToExtension,
        int $maxBytes,
    ): string {
        return self::resolved()->performStoreUpload(
            $file,
            $directoryRelativeToPublic,
            $filenameStem,
            $mimeToExtension,
            $maxBytes,
        );
    }

    public static function deleteIfExists(?string $relativePath): void
    {
        self::resolved()->performDeleteIfExists($relativePath);
    }

    private function rootPath(): string
    {
        return $this->publicRoot;
    }

    /**
     * @param array<string, string> $mimeToExtension
     */
    private function performStoreUpload(
        UploadedFile $file,
        string $directoryRelativeToPublic,
        string $filenameStem,
        array $mimeToExtension,
        int $maxBytes,
    ): string {
        if ($mimeToExtension === []) {
            throw new InvalidArgumentException('mimeToExtension must not be empty.');
        }

        if (! $file->isValid()) {
            throw new RuntimeException($file->clientErrorMessage());
        }

        if ($file->size > $maxBytes) {
            throw new RuntimeException('upload.too_large');
        }

        $directoryRelativeToPublic = trim(str_replace("\0", '', $directoryRelativeToPublic), '/');
        if ($directoryRelativeToPublic === '' || str_contains($directoryRelativeToPublic, '..')) {
            throw new InvalidArgumentException('Invalid upload directory.');
        }

        $filenameStem = str_replace("\0", '', $filenameStem);
        if ($filenameStem === '' || str_contains($filenameStem, '/') || str_contains($filenameStem, '\\')) {
            throw new InvalidArgumentException('Invalid filename stem.');
        }

        $mime = $file->mimeFromContent();
        if ($mime === null || ! array_key_exists($mime, $mimeToExtension)) {
            throw new RuntimeException('upload.mime_not_allowed');
        }

        $ext = $mimeToExtension[$mime];
        if ($ext === '' || str_contains($ext, '/') || str_contains($ext, '\\') || str_contains($ext, '.')) {
            throw new InvalidArgumentException('Invalid extension mapping.');
        }

        $relative = $directoryRelativeToPublic . '/' . $filenameStem . '.' . $ext;
        $full = $this->publicRoot . '/' . $relative;

        $file->moveTo($full);

        return $relative;
    }

    /**
     * Delete a file relative to {@code public/} if it exists.
     */
    private function performDeleteIfExists(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }
        $relativePath = str_replace("\0", '', $relativePath);
        if (str_contains($relativePath, '..')) {
            return;
        }
        $full = $this->publicRoot . '/' . ltrim($relativePath, '/');
        $realPublic = realpath($this->publicRoot);
        $realFile = realpath($full);
        if ($realPublic === false || $realFile === false || ! str_starts_with($realFile, $realPublic)) {
            return;
        }
        if (is_file($realFile)) {
            @unlink($realFile);
        }
    }
}
