<?php

declare(strict_types=1);

namespace Vortex\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vortex\Files\DiskManager;
use Vortex\Files\LocalFilesystemDriver;

final class DiskManagerTest extends TestCase
{
    public function testFromConfigLocalDiskOnly(): void
    {
        $base = sys_get_temp_dir() . '/dm-' . bin2hex(random_bytes(3));
        mkdir($base . '/storage/app', 0777, true);
        $mgr = DiskManager::fromConfig($base, [
            'default' => 'local',
            'disks' => [
                'local' => ['driver' => 'local', 'root' => 'storage/app'],
            ],
        ]);
        self::assertInstanceOf(LocalFilesystemDriver::class, $mgr->disk('local'));
        self::assertSame($base . '/storage/app', $mgr->disk('local')->root());
    }

    public function testUnknownDriverThrows(): void
    {
        $mgr = DiskManager::fromConfig('/tmp/x', [
            'default' => 'x',
            'disks' => [
                'x' => ['driver' => 's3'],
            ],
        ]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown storage driver');
        $mgr->disk('x');
    }

    public function testMissingDefaultDiskThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Default storage disk');
        DiskManager::fromConfig('/tmp', [
            'default' => 'missing',
            'disks' => [
                'local' => ['driver' => 'local', 'root' => 'storage/app'],
            ],
        ]);
    }
}
