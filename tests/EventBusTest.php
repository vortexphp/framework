<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Container;
use Vortex\Events\Dispatcher;
use Vortex\Events\EventBus;
use Vortex\Tests\Fixtures\DemoEvent;
use Vortex\Tests\Fixtures\DemoListenerHandle;

final class EventBusTest extends TestCase
{
    protected function tearDown(): void
    {
        $ref = new \ReflectionClass(AppContext::class);
        $prop = $ref->getProperty('container');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
        DemoListenerHandle::reset();
        parent::tearDown();
    }

    public function testDispatchDelegatesToDispatcher(): void
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        $c->singleton(Dispatcher::class, static function (Container $container): Dispatcher {
            $d = new Dispatcher($container);
            $d->listen(DemoEvent::class, DemoListenerHandle::class);

            return $d;
        });
        AppContext::set($c);
        EventBus::dispatch(new DemoEvent('bus'));
        self::assertSame(['handle:bus'], DemoListenerHandle::$log);
    }
}
