<?php

declare(strict_types=1);

namespace Vortex\Files;

use InvalidArgumentException;
use RuntimeException;
use Vortex\Config\Repository;
use Vortex\Contracts\Filesystem;
use Vortex\Contracts\PublicFilesystem;
use Vortex\Http\UploadedFile;

/**
 * Facade for configured storage disks ({@see DiskManager}, {@code config/storage.php}).
 */
final class Storage
{
    private static ?string $basePath = null;

    private static ?DiskManager $manager = null;

    private static ?DiskManager $managerOverride = null;

    /**
     * Project root (directory containing {@code storage/}, {@code public/}, …).
     */
    public static function setBasePath(string $basePath): void
    {
        $basePath = rtrim($basePath, '/');
        if (self::$basePath !== $basePath) {
            self::$manager = null;
        }
        self::$basePath = $basePath;
    }

    /**
     * @internal
     */
    public static function swapManagerForTesting(?DiskManager $manager): void
    {
        self::$managerOverride = $manager;
        self::$manager = null;
    }

    /**
     * @internal
     */
    public static function resetForTesting(): void
    {
        self::$basePath = null;
        self::$manager = null;
        self::$managerOverride = null;
    }

    public static function disk(?string $name = null): Filesystem
    {
        return self::manager()->disk($name);
    }

    /**
     * @param array<string, string> $mimeToExtension
     */
    public static function storeUpload(
        UploadedFile $file,
        string $directoryRelativeToPublic,
        string $filenameStem,
        array $mimeToExtension,
        int $maxBytes,
    ): string {
        $disk = self::manager()->disk(self::uploadDiskName());
        if (! $disk instanceof PublicFilesystem) {
            throw new RuntimeException(
                'Disk [' . self::uploadDiskName() . '] must use driver local_public for storeUpload().',
            );
        }

        return $disk->storeUpload($file, $directoryRelativeToPublic, $filenameStem, $mimeToExtension, $maxBytes);
    }

    public static function deletePublic(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }
        self::manager()->disk(self::publicDiskName())->delete($relativePath);
    }

    public static function publicRoot(): string
    {
        $disk = self::manager()->disk(self::publicDiskName());
        if (! $disk instanceof PublicFilesystem) {
            throw new RuntimeException(
                'Disk [' . self::publicDiskName() . '] must use driver local_public for publicRoot().',
            );
        }

        return $disk->root();
    }

    public static function put(string $relativePath, string $contents): void
    {
        self::disk()->put($relativePath, $contents);
    }

    public static function append(string $relativePath, string $contents): void
    {
        self::disk()->append($relativePath, $contents);
    }

    public static function get(string $relativePath): ?string
    {
        return self::disk()->get($relativePath);
    }

    public static function exists(string $relativePath): bool
    {
        return self::disk()->exists($relativePath);
    }

    public static function delete(string $relativePath): void
    {
        self::disk()->delete($relativePath);
    }

    private static function manager(): DiskManager
    {
        if (self::$managerOverride !== null) {
            return self::$managerOverride;
        }

        if (self::$basePath === null || self::$basePath === '') {
            throw new RuntimeException('Storage::setBasePath() must be called from bootstrap.');
        }

        if (self::$manager === null) {
            self::$manager = DiskManager::fromRepository(self::$basePath);
        }

        return self::$manager;
    }

    private static function publicDiskName(): string
    {
        if (self::$managerOverride !== null) {
            return 'public';
        }

        return (string) Repository::get('storage.public_disk', 'public');
    }

    private static function uploadDiskName(): string
    {
        if (self::$managerOverride !== null) {
            return 'public';
        }

        return (string) Repository::get('storage.upload_disk', 'public');
    }
}
