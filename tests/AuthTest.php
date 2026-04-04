<?php

declare(strict_types=1);

namespace Vortex\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Auth\Auth;
use Vortex\Auth\Authenticatable;
use Vortex\Auth\Gate;
use Vortex\Container;
use Vortex\Http\Cookie;
use Vortex\Http\NullSessionStore;
use Vortex\Http\Session;
use Vortex\Http\SessionManager;

final class AuthTest extends TestCase
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
        Auth::resetForTesting();
        Gate::resetForTesting();
        Cookie::resetQueue();

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

    public function testGuestUntilLogin(): void
    {
        self::assertTrue(Auth::guest());
        self::assertFalse(Auth::check());
        self::assertNull(Auth::id());
        self::assertNull(Auth::user());
    }

    public function testLoginUsingIdAndLogout(): void
    {
        Auth::loginUsingId(9);
        self::assertTrue(Auth::check());
        self::assertFalse(Auth::guest());
        self::assertSame(9, Auth::id());

        Auth::logout();
        self::assertTrue(Auth::guest());
        self::assertNull(Auth::id());
    }

    public function testLoginUsingIdRejectsNonPositiveId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Auth::loginUsingId(0);
    }

    public function testLoginWithAuthenticatable(): void
    {
        $user = new class implements Authenticatable {
            public function authIdentifier(): int
            {
                return 3;
            }
        };

        Auth::login($user);
        self::assertSame(3, Auth::id());
    }

    public function testUserResolvedWhenCallbackRegistered(): void
    {
        Auth::resolveUserUsing(static fn (int $id): array => ['id' => $id, 'name' => 'A']);
        Auth::loginUsingId(2);

        self::assertSame(['id' => 2, 'name' => 'A'], Auth::user());
    }
}
