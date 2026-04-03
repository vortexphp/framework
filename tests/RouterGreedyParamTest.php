<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Container;
use Vortex\Routing\Route;
use Vortex\Routing\Router;

final class RouterGreedyParamTest extends TestCase
{
    public function testGreedyParamCapturesMultipleSegments(): void
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        $router = new Router($c);
        Route::useRouter($router);
        Route::get('/docs', static fn (): string => 'index');
        Route::get('/docs/{path...}', static fn (string $path): string => $path);

        $m = $router->match('GET', '/docs');
        self::assertNotNull($m);
        self::assertSame('index', $router->dispatch($this->minimalRequest('GET', '/docs'), [])->body());

        $m = $router->match('GET', '/docs/a/b');
        self::assertNotNull($m);
        self::assertSame('a/b', $m['params']['path']);
    }

    public function testGreedyParamCanBeCombinedWithSingleSegment(): void
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        $router = new Router($c);
        Route::useRouter($router);
        Route::get('/files/{id}/{rest...}', static fn (string $id, string $rest): string => $id . ':' . $rest);

        $m = $router->match('GET', '/files/12/a/b');
        self::assertNotNull($m);
        self::assertSame('12', $m['params']['id']);
        self::assertSame('a/b', $m['params']['rest']);
    }

    /**
     * Minimal request for dispatch (only path + method used by Router here).
     */
    private function minimalRequest(string $method, string $path): \Vortex\Http\Request
    {
        return new \Vortex\Http\Request($method, $path, [], [], [], []);
    }
}
