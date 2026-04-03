<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Container;
use Vortex\Http\NullSessionStore;
use Vortex\Http\Session;
use Vortex\Http\SessionManager;

final class SessionStaticFacadeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $container = new Container();
        $container->instance(Container::class, $container);
        $container->singleton(SessionManager::class, static fn (): SessionManager => SessionManager::fromInstances('null', [
            'null' => new NullSessionStore(),
        ]));
        $container->singleton(Session::class, static fn (Container $c): Session => new Session($c->make(SessionManager::class)->store()));
        Session::setInstance($container->make(Session::class));
        AppContext::set($container);
    }

    protected function tearDown(): void
    {
        $refApp = new \ReflectionClass(AppContext::class);
        $propApp = $refApp->getProperty('container');
        $propApp->setAccessible(true);
        $propApp->setValue(null, null);

        $refSession = new \ReflectionClass(Session::class);
        $propSession = $refSession->getProperty('instance');
        $propSession->setAccessible(true);
        $propSession->setValue(null, null);

        parent::tearDown();
    }

    public function testFacadeDelegatesToDefaultStore(): void
    {
        Session::put('a', 7);
        self::assertSame(7, Session::get('a'));
        self::assertSame(7, Session::pull('a'));
        self::assertNull(Session::get('a'));
    }

    public function testStoreResolvesNamedStoreFromManager(): void
    {
        $store = Session::store('null');
        self::assertInstanceOf(NullSessionStore::class, $store);
        $store->put('x', 'ok');
        self::assertSame('ok', $store->get('x'));
        self::assertSame('ok', Session::get('x'));
    }

    public function testFlashRoundtrip(): void
    {
        Session::flash('k', 'v');
        self::assertSame('v', Session::flash('k'));
        self::assertNull(Session::flash('k'));
    }

    public function testFlashManyConsumesRequestedKeys(): void
    {
        Session::flashPutMany([
            'errors' => ['email' => 'invalid'],
            'status' => 'ok',
        ]);

        $values = Session::flashMany(['errors', 'status', 'missing']);

        self::assertSame(['email' => 'invalid'], $values['errors']);
        self::assertSame('ok', $values['status']);
        self::assertNull($values['missing']);
        self::assertNull(Session::flash('errors'));
        self::assertNull(Session::flash('status'));
    }
}
