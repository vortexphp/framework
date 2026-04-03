<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Container;
use Vortex\Events\Dispatcher;
use Vortex\Tests\Fixtures\DemoEvent;
use Vortex\Tests\Fixtures\DemoListenerHandle;
use Vortex\Tests\Fixtures\DemoListenerInvoke;
use Vortex\Tests\Fixtures\BadListener;
use Vortex\Tests\Fixtures\DemoListenerSecond;
use TypeError;

final class DispatcherTest extends TestCase
{
    protected function tearDown(): void
    {
        DemoListenerHandle::reset();
        DemoListenerInvoke::reset();
        parent::tearDown();
    }

    public function testInvokesHandleMethod(): void
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        $d = new Dispatcher($c);
        $d->listen(DemoEvent::class, DemoListenerHandle::class);
        $d->dispatch(new DemoEvent('a'));
        self::assertSame(['handle:a'], DemoListenerHandle::$log);
    }

    public function testInvokesInvoke(): void
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        $d = new Dispatcher($c);
        $d->listen(DemoEvent::class, DemoListenerInvoke::class);
        $d->dispatch(new DemoEvent('b'));
        self::assertSame(['invoke:b'], DemoListenerInvoke::$log);
    }

    public function testClosureListener(): void
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        $d = new Dispatcher($c);
        $d->listen(DemoEvent::class, static function (DemoEvent $e): void {
            DemoListenerHandle::$log[] = 'fn:' . $e->payload;
        });
        $d->dispatch(new DemoEvent('c'));
        self::assertSame(['fn:c'], DemoListenerHandle::$log);
    }

    public function testListenerOrder(): void
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        $d = new Dispatcher($c);
        $d->listen(DemoEvent::class, DemoListenerHandle::class);
        $d->listen(DemoEvent::class, DemoListenerSecond::class);
        $d->dispatch(new DemoEvent('z'));
        self::assertSame(['handle:z', 'second:z'], DemoListenerHandle::$log);
    }

    public function testInvalidListenerThrows(): void
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        $d = new Dispatcher($c);
        $d->listen(DemoEvent::class, BadListener::class);
        $this->expectException(TypeError::class);
        $d->dispatch(new DemoEvent());
    }
}
