<?php

declare(strict_types=1);

namespace Vortex\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vortex\Container;
use Vortex\Http\Response;
use Vortex\Routing\Route;
use Vortex\Routing\Router;

final class RouterNamedRouteTest extends TestCase
{
    protected function setUp(): void
    {
        $prop = new \ReflectionProperty(Route::class, 'router');
        $prop->setValue(null, null);
    }

    public function testPathEncodesSegments(): void
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        $router = new Router($c);
        Route::useRouter($router);

        Route::get('/blog/{slug}', static fn (): Response => Response::make(''))->name('blog.show');

        self::assertSame('/blog/hello%20world', $router->path('blog.show', ['slug' => 'hello world']));
    }

    public function testGreedySegmentNotEncoded(): void
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        $router = new Router($c);
        Route::useRouter($router);

        Route::get('/docs/{path...}', static fn (): Response => Response::make(''))->name('docs.show');

        self::assertSame('/docs/framework/http', $router->path('docs.show', ['path' => 'framework/http']));
    }

    public function testInterpolatePatternStatic(): void
    {
        self::assertSame('/a/b%2Fc', Router::interpolatePattern('/a/{x}', ['x' => 'b/c']));
    }

    public function testDuplicateNameThrows(): void
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        $router = new Router($c);
        Route::useRouter($router);

        Route::get('/a', static fn (): Response => Response::make(''))->name('dup');
        $this->expectException(RuntimeException::class);
        Route::get('/b', static fn (): Response => Response::make(''))->name('dup');
    }

    public function testUnknownNameThrows(): void
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        $router = new Router($c);

        $this->expectException(InvalidArgumentException::class);
        $router->path('missing');
    }

    public function testMissingParamThrows(): void
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        $router = new Router($c);
        Route::useRouter($router);

        Route::get('/blog/{slug}', static fn (): Response => Response::make(''))->name('blog.show');

        $this->expectException(InvalidArgumentException::class);
        $router->path('blog.show', []);
    }
}
