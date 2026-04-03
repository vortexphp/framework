<?php

declare(strict_types=1);

namespace Vortex\Http;

use finfo;

final class UploadedFile
{
    public function __construct(
        public readonly string $originalName,
        public readonly string $tmpPath,
        public readonly int $error,
        public readonly int $size,
    ) {
    }

    /**
     * Client sent a file for this field (may still have failed upload).
     */
    public function wasAttempted(): bool
    {
        return $this->error !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Non-empty filename in the request (after {@see UPLOAD_ERR_NO_FILE} excluded).
     */
    public function hasFile(): bool
    {
        return $this->wasAttempted() && $this->originalName !== '';
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK
            && $this->tmpPath !== ''
            && is_uploaded_file($this->tmpPath);
    }

    /** Translation key (e.g. {@code upload.too_large}). */
    public function clientErrorMessage(): string
    {
        return match ($this->error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'upload.too_large',
            UPLOAD_ERR_PARTIAL => 'upload.partial',
            UPLOAD_ERR_NO_FILE => 'upload.none',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'upload.server',
            default => 'upload.invalid',
        };
    }

    /**
     * MIME from temp file contents (not trusted client type).
     */
    public function mimeFromContent(): ?string
    {
        if (! $this->isValid() || ! is_readable($this->tmpPath)) {
            return null;
        }
        $f = new finfo(FILEINFO_MIME_TYPE);
        $m = $f->file($this->tmpPath);

        return is_string($m) ? $m : null;
    }

    public function moveTo(string $destinationPath): void
    {
        if (! $this->isValid()) {
            throw new \RuntimeException('Cannot move invalid upload.');
        }
        $dir = dirname($destinationPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (! move_uploaded_file($this->tmpPath, $destinationPath)) {
            throw new \RuntimeException('Failed to store uploaded file.');
        }
        @chmod($destinationPath, 0644);
    }
}
