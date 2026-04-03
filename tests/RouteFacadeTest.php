<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Container;
use Vortex\Http\Response;
use Vortex\Routing\Route;
use Vortex\Routing\Router;
use RuntimeException;

final class RouteFacadeTest extends TestCase
{
    protected function setUp(): void
    {
        $prop = new \ReflectionProperty(Route::class, 'router');
        $prop->setValue(null, null);
    }

    public function testDelegatesToRouterAndChains(): void
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        $router = new Router($c);
        Route::useRouter($router);

        Route::get('/hit', static fn (): Response => Response::make('x'))
            ->post('/form', static fn (): Response => Response::make('p'));

        self::assertNotNull($router->match('GET', '/hit'));
        self::assertNotNull($router->match('POST', '/form'));
    }

    public function testWithoutUseRouterThrows(): void
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        new Router($c);

        $this->expectException(RuntimeException::class);
        Route::get('/nope', static fn (): Response => Response::make(''));
    }
}
