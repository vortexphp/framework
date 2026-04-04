<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Auth\Auth;
use Vortex\Auth\AuthorizationException;
use Vortex\Auth\Authenticatable;
use Vortex\Auth\Gate;
use Vortex\Container;
use Vortex\Http\Cookie;
use Vortex\Http\NullSessionStore;
use Vortex\Http\Session;
use Vortex\Http\SessionManager;

final class GateBook
{
}

final class GateBookPolicy
{
    public function update(mixed $user, GateBook $book): bool
    {
        return $user !== null;
    }
}

final class GateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Gate::resetForTesting();

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
        Gate::resetForTesting();
        Auth::resetForTesting();
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

    public function testDefinedAbilityUsesCurrentUser(): void
    {
        Gate::define('must-auth', static fn (mixed $user): bool => $user !== null);

        self::assertFalse(Gate::allows('must-auth'));

        Auth::resolveUserUsing(static fn (int $id): object => new class implements Authenticatable {
            public function authIdentifier(): int
            {
                return 1;
            }
        });
        Auth::loginUsingId(1);

        self::assertTrue(Gate::allows('must-auth'));
        self::assertFalse(Gate::denies('must-auth'));
    }

    public function testPolicyDelegatesToRegisteredPolicy(): void
    {
        Gate::policy(GateBook::class, GateBookPolicy::class);
        Auth::resolveUserUsing(static fn (int $id): object => new class implements Authenticatable {
            public function authIdentifier(): int
            {
                return 1;
            }
        });
        Auth::loginUsingId(1);

        self::assertTrue(Gate::allows('update', new GateBook()));
        Auth::logout();
        self::assertFalse(Gate::allows('update', new GateBook()));
    }

    public function testAuthorizeThrowsWhenDenied(): void
    {
        Gate::define('secret', static fn (mixed $_user): bool => false);

        $this->expectException(AuthorizationException::class);
        Gate::authorize('secret');
    }
}
