<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Cache\Cache;
use Vortex\Cache\FileCache;
use Vortex\Container;
use Vortex\Contracts\Cache as CacheContract;

final class CacheStaticFacadeTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/pc-cache-facade-' . bin2hex(random_bytes(4));
        $container = new Container();
        $container->instance(Container::class, $container);
        $container->singleton(CacheContract::class, fn (): FileCache => new FileCache($this->dir, 'f:'));
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
}
