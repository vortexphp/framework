<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Cache\FileCache;

final class FileCacheTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/vortex -file-cache-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
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

    public function testSetGetForget(): void
    {
        $c = new FileCache($this->dir, 't:');
        $c->set('a', ['x' => 1]);
        self::assertSame(['x' => 1], $c->get('a'));
        self::assertNull($c->get('missing', null));
        self::assertSame('d', $c->get('missing', 'd'));
        $c->forget('a');
        self::assertSame('d', $c->get('a', 'd'));
    }

    public function testCachesFalse(): void
    {
        $c = new FileCache($this->dir, 't:');
        $c->set('b', false);
        self::assertFalse($c->get('b', true));
    }

    public function testTtlExpires(): void
    {
        $c = new FileCache($this->dir, 't:');
        $c->set('k', 1, 1);
        self::assertSame(1, $c->get('k'));
        sleep(2);
        self::assertSame('gone', $c->get('k', 'gone'));
    }

    public function testRememberRunsOnce(): void
    {
        $c = new FileCache($this->dir, 't:');
        $n = 0;
        $a = $c->remember('r', null, function () use (&$n): int {
            $n++;

            return 7;
        });
        $b = $c->remember('r', null, function () use (&$n): int {
            $n++;

            return 9;
        });
        self::assertSame(7, $a);
        self::assertSame(7, $b);
        self::assertSame(1, $n);
    }

    public function testClear(): void
    {
        $c = new FileCache($this->dir, 't:');
        $c->set('x', 1);
        $c->set('y', 2);
        $c->clear();
        self::assertNull($c->get('x', null));
        self::assertNull($c->get('y', null));
    }

    public function testPrefixIsolatesKeys(): void
    {
        $a = new FileCache($this->dir, 'p1:');
        $b = new FileCache($this->dir, 'p2:');
        $a->set('same', 'one');
        $b->set('same', 'two');
        self::assertSame('one', $a->get('same'));
        self::assertSame('two', $b->get('same'));
    }

    public function testAddSetsOnlyWhenMissing(): void
    {
        $c = new FileCache($this->dir, 't:');
        self::assertTrue($c->add('nx', 'first', 60));
        self::assertFalse($c->add('nx', 'second', 60));
        self::assertSame('first', $c->get('nx'));
    }
}
