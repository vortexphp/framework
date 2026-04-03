<?php

declare(strict_types=1);

namespace Vortex\Contracts;

use Vortex\Http\UploadedFile;

/**
 * Public web-root disk with validated uploads ({@see LocalPublicStorage} rules).
 */
interface PublicFilesystem extends Filesystem
{
    /**
     * @param array<string, string> $mimeToExtension
     */
    public function storeUpload(
        UploadedFile $file,
        string $directoryRelativeToPublic,
        string $filenameStem,
        array $mimeToExtension,
        int $maxBytes,
    ): string;
}
