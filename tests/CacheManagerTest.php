<?php

declare(strict_types=1);

namespace Vortex\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vortex\Cache\CacheManager;
use Vortex\Cache\NullCache;
use Vortex\Config\Repository;

final class CacheManagerTest extends TestCase
{
    private string $configDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configDir = sys_get_temp_dir() . '/vortex -cache-mgr-' . bin2hex(random_bytes(4));
        mkdir($this->configDir, 0700, true);
    }

    protected function tearDown(): void
    {
        Repository::forgetInstance();
        if ($this->configDir !== '' && is_dir($this->configDir)) {
            foreach (glob($this->configDir . '/*.php') ?: [] as $f) {
                unlink($f);
            }
            rmdir($this->configDir);
        }
        parent::tearDown();
    }

    public function testStoreNamedNullVsFile(): void
    {
        $path = sys_get_temp_dir() . '/vortex -cm-data-' . bin2hex(random_bytes(4));
        mkdir($path, 0700, true);
        try {
            $mgr = CacheManager::fromConfig('/tmp/base', [
                'default' => 'file',
                'stores' => [
                    'file' => ['driver' => 'file', 'path' => $path, 'prefix' => 't:'],
                    'null' => ['driver' => 'null'],
                ],
            ]);
            $mgr->store('null')->set('k', 1, null);
            self::assertNull($mgr->store('null')->get('k', null));
            $mgr->store('file')->set('k', 2, null);
            self::assertSame(2, $mgr->store('file')->get('k'));
        } finally {
            foreach (glob($path . '/*') ?: [] as $f) {
                if (is_file($f)) {
                    unlink($f);
                }
            }
            @rmdir($path);
        }
    }

    public function testUnknownDriverThrowsWhenResolvingStore(): void
    {
        $mgr = CacheManager::fromConfig('/tmp', [
            'default' => 'x',
            'stores' => [
                'x' => ['driver' => 'redis'],
            ],
        ]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown cache driver');
        $mgr->store('x');
    }

    public function testFromInstances(): void
    {
        $a = new NullCache();
        $mgr = CacheManager::fromInstances('main', ['main' => $a]);
        self::assertSame($a, $mgr->store());
        self::assertSame($a, $mgr->store('main'));
    }
}
