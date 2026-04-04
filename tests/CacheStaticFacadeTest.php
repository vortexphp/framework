<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Cache\Cache;
use Vortex\Cache\CacheManager;
use Vortex\Cache\FileCache;
use Vortex\Container;
use Vortex\Contracts\Cache as CacheContract;

final class CacheStaticFacadeTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/vortex -cache-facade-' . bin2hex(random_bytes(4));
        $container = new Container();
        $container->instance(Container::class, $container);
        $manager = CacheManager::fromInstances('file', [
            'file' => new FileCache($this->dir, 'f:'),
        ]);
        $container->singleton(CacheManager::class, static fn (): CacheManager => $manager);
        $container->singleton(CacheContract::class, static fn (Container $c): CacheContract => $c->make(CacheManager::class)->store());
        AppContext::set($container);
    }

    protected function tearDown(): void
    {
        $ref = new \ReflectionClass(AppContext::class);
        $prop = $ref->getProperty('container');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
        if ($this->dir !== '' && is_dir($this->dir)) {
            foreach (glob($this->dir . '/*') ?: [] as $f) {
                if (is_file($f)) {
                    unlink($f);
                }
            }
            rmdir($this->dir);
        }
        parent::tearDown();
    }

    public function testRememberDelegatesToSameStore(): void
    {
        $n = 0;
        $a = Cache::remember('k', null, function () use (&$n): int {
            $n++;

            return 5;
        });
        $b = Cache::remember('k', null, function () use (&$n): int {
            $n++;

            return 9;
        });
        self::assertSame(5, $a);
        self::assertSame(5, $b);
        self::assertSame(1, $n);
    }

    public function testAddDelegatesToStore(): void
    {
        self::assertTrue(Cache::add('flag', 1, 30));
        self::assertFalse(Cache::add('flag', 2, 30));
        self::assertSame(1, Cache::get('flag'));
    }
}
