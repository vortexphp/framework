<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Support\FilesConfigUploadRoots;

final class FilesConfigUploadRootsTest extends TestCase
{
    public function testCollectSkipsScalarsAndMaxUploadBytes(): void
    {
        $config = [
            'max_upload_bytes' => 100,
            'avatar' => [
                'directory' => 'uploads/avatars',
                'mime_extensions' => [],
            ],
            'note' => 'skip',
            0 => ['directory' => 'bad'],
        ];

        $got = FilesConfigUploadRoots::collect($config);

        self::assertSame([['profile' => 'avatar', 'relative' => 'uploads/avatars']], $got);
    }

    public function testAbsolutePath(): void
    {
        self::assertSame(
            '/var/www/uploads',
            FilesConfigUploadRoots::absolutePath('/app', '/var/www/uploads'),
        );
        self::assertSame(
            '/app/public/uploads/x',
            FilesConfigUploadRoots::absolutePath('/app', 'uploads/x'),
        );
    }
}
