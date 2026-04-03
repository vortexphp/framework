<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Files\DiskManager;
use Vortex\Files\LocalFilesystemDriver;
use Vortex\Files\LocalPublicFilesystemDriver;
use Vortex\Files\LocalPublicStorage;
use Vortex\Files\NullFilesystemDriver;
use Vortex\Files\Storage;

final class StorageTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir() . '/vortex-storage-' . bin2hex(random_bytes(4));
        mkdir($this->base . '/public/uploads', 0777, true);
        mkdir($this->base . '/storage/app', 0777, true);
        LocalPublicStorage::setInstance(new LocalPublicStorage($this->base . '/public'));
        Storage::setBasePath($this->base);
        Storage::swapManagerForTesting(DiskManager::fromInstances('local', [
            'local' => new LocalFilesystemDriver($this->base . '/storage/app'),
            'public' => new LocalPublicFilesystemDriver(),
        ]));
    }

    protected function tearDown(): void
    {
        LocalPublicStorage::forgetInstance();
        Storage::resetForTesting();
        $this->rrmdir($this->base);
        parent::tearDown();
    }

    public function testPublicRootDelegates(): void
    {
        self::assertSame($this->base . '/public', Storage::publicRoot());
    }

    public function testLocalPutGetExistsDelete(): void
    {
        Storage::put('exports/a.txt', 'hello');
        self::assertTrue(Storage::exists('exports/a.txt'));
        self::assertSame('hello', Storage::get('exports/a.txt'));
        Storage::append('exports/a.txt', ' world');
        self::assertSame('hello world', Storage::get('exports/a.txt'));
        Storage::delete('exports/a.txt');
        self::assertFalse(Storage::exists('exports/a.txt'));
        self::assertNull(Storage::get('exports/a.txt'));
    }

    public function testLocalInvalidPathThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Storage::put('../escape', 'x');
    }

    public function testDiskNamed(): void
    {
        Storage::disk('local')->put('x.txt', '1');
        self::assertSame('1', Storage::disk('local')->get('x.txt'));
    }

    public function testNullDriver(): void
    {
        Storage::swapManagerForTesting(DiskManager::fromInstances('null', [
            'null' => new NullFilesystemDriver(),
        ]));
        Storage::disk('null')->put('any', 'data');
        self::assertNull(Storage::disk('null')->get('any'));
        self::assertFalse(Storage::disk('null')->exists('any'));
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $p = $dir . '/' . $item;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
