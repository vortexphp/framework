<?php

declare(strict_types=1);

namespace Vortex\Tests;

use Closure;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vortex\Container;
use Vortex\Contracts\Middleware;
use Vortex\Http\Controller;
use Vortex\Http\ErrorRenderer;
use Vortex\Http\Request;
use Vortex\Http\Response;
use Vortex\Routing\Route;
use Vortex\Routing\Router;

final class RouterControllerConventionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new \ReflectionProperty(Route::class, 'router'))->setValue(null, null);
    }

    protected function tearDown(): void
    {
        Request::forgetCurrent();
        parent::tearDown();
    }

    public function testInvokableControllerClassNameAsAction(): void
    {
        $c = $this->routerContainer();
        $router = new Router($c);
        $router->get('/api/ping', InvokablePingController::class);

        $response = $router->dispatch(Request::make('GET', '/api/ping'));
        self::assertSame(200, $response->httpStatus());
        self::assertSame('{"ok":true}', $response->body());
    }

    public function testControllerHelpersJson(): void
    {
        $c = $this->routerContainer();
        $router = new Router($c);
        $router->get('/h', [PlainController::class, 'show']);

        $response = $router->dispatch(Request::make('GET', '/h'));
        self::assertSame('{"via":"controller"}', $response->body());
    }

    public function testMiddlewareChainOnRoute(): void
    {
        $c = $this->routerContainer();
        $router = new Router($c);
        $router->get('/m', static fn (): Response => Response::make('body'))
            ->middleware([TagMiddleware::class]);

        TagMiddleware::$headerValue = 'route-mw';
        $response = $router->dispatch(Request::make('GET', '/m'));
        self::assertSame('route-mw', $response->headers()['X-Tagged'] ?? null);
    }

    public function testMiddlewareWithoutRouteThrows(): void
    {
        $c = $this->routerContainer();
        $router = new Router($c);

        $this->expectException(RuntimeException::class);
        $router->middleware(TagMiddleware::class);
    }

    private function routerContainer(): Container
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        $c->singleton(ErrorRenderer::class, static fn (): ErrorRenderer => new ErrorRenderer());

        return $c;
    }
}

final class InvokablePingController extends Controller
{
    public function __invoke(): Response
    {
        return $this->json(['ok' => true]);
    }
}

final class PlainController extends Controller
{
    public function show(): Response
    {
        return $this->json(['via' => 'controller']);
    }
}

final class TagMiddleware implements Middleware
{
    public static string $headerValue = '1';

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request)->header('X-Tagged', self::$headerValue);
    }
}
