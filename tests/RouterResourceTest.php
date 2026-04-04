<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Container;
use Vortex\Http\ErrorRenderer;
use Vortex\Http\Request;
use Vortex\Http\Response;
use Vortex\I18n\Translator;
use Vortex\Routing\Route;
use Vortex\Routing\Router;

final class RouterResourceTest extends TestCase
{
    private string $configDir = '';

    protected function setUp(): void
    {
        (new \ReflectionProperty(Route::class, 'router'))->setValue(null, null);

        $this->configDir = sys_get_temp_dir() . '/vortex-route-res-' . bin2hex(random_bytes(4));
        mkdir($this->configDir . '/lang', 0700, true);
        file_put_contents(
            $this->configDir . '/lang/en.php',
            <<<'PHP'
<?php
return ['errors' => ['json' => ['not_found' => 'Not found'], '404' => ['title' => 'N', 'body' => 'B']]];
PHP,
        );
        Translator::setInstance(new Translator($this->configDir . '/lang', 'en', 'en', ['en']));
    }

    protected function tearDown(): void
    {
        Translator::forgetInstance();
        if ($this->configDir !== '' && is_file($this->configDir . '/lang/en.php')) {
            unlink($this->configDir . '/lang/en.php');
            rmdir($this->configDir . '/lang');
            rmdir($this->configDir);
        }
        (new \ReflectionProperty(Route::class, 'router'))->setValue(null, null);
        Request::forgetCurrent();
        parent::tearDown();
    }

    public function testApiStyleResourceOmitsCreateAndEdit(): void
    {
        $router = $this->photosRouter();

        self::assertSame('index', $router->dispatch(Request::make('GET', '/photos'), [])->body());
        self::assertSame('store', $router->dispatch(Request::make('POST', '/photos'), [])->body());
        self::assertSame('show:7', $router->dispatch(Request::make('GET', '/photos/7'), [])->body());
        self::assertSame('update:7', $router->dispatch(Request::make('PUT', '/photos/7'), [])->body());
        self::assertSame('update:7', $router->dispatch(Request::make('PATCH', '/photos/7'), [])->body());
        self::assertSame('destroy:7', $router->dispatch(Request::make('DELETE', '/photos/7'), [])->body());

        // Without a `create` route, `/photos/create` is handled by `show` with id `create`.
        self::assertSame('show:create', $router->dispatch(Request::make('GET', '/photos/create'), [])->body());
    }

    public function testFullResourceMatchesCreateLiteralBeforeParameterizedShow(): void
    {
        $router = $this->photosRouter(['except' => []]);

        self::assertSame('create', $router->dispatch(Request::make('GET', '/photos/create'), [])->body());
        self::assertSame('show:real-id', $router->dispatch(Request::make('GET', '/photos/real-id'), [])->body());
    }

    public function testFullResourceEditRoute(): void
    {
        $router = $this->photosRouter(['except' => []]);
        self::assertSame('edit:9', $router->dispatch(Request::make('GET', '/photos/9/edit'), [])->body());
    }

    public function testNamedPathsAndPrefix(): void
    {
        $router = $this->photosRouter(['names' => 'v1']);

        self::assertSame('/photos/5', $router->path('v1.photos.show', ['photo' => '5']));
        self::assertSame('/photos', $router->path('v1.photos.index'));
    }

    public function testNestedUriUsesLastSegmentForParameterName(): void
    {
        $router = $this->newRouter();
        Route::useRouter($router);
        $router->resource('admin/photos', StubPhotosController::class, ['names' => false]);

        self::assertSame('show:3', $router->dispatch(Request::make('GET', '/admin/photos/3'), [])->body());
    }

    public function testOnlyRestrictsActions(): void
    {
        $router = $this->newRouter();
        Route::useRouter($router);
        $router->resource('courses', StubPhotosController::class, ['only' => ['index']]);

        self::assertSame('index', $router->dispatch(Request::make('GET', '/courses'), [])->body());
        $miss = $router->dispatch(Request::make('GET', '/courses/1', [], [], ['Accept' => 'application/json']), []);
        self::assertSame(404, $miss->httpStatus());
    }

    public function testCategoriesResourceSingularizesIes(): void
    {
        $router = $this->newRouter();
        Route::useRouter($router);
        $router->resource('categories', StubCategoryController::class, ['only' => ['show']]);

        self::assertSame('cat:12', $router->dispatch(Request::make('GET', '/categories/12'), [])->body());
    }

    public function testMiddlewareAppliedToResourceRoutes(): void
    {
        $router = $this->newRouter();
        Route::useRouter($router);
        $router->resource('items', StubPhotosController::class, [
            'only' => ['index'],
            'middleware' => [ResourceTestMiddleware::class],
        ]);

        self::assertSame('mw:index', $router->dispatch(Request::make('GET', '/items'), [])->body());
    }

    /**
     * @param array<string, mixed> $resourceOptions
     */
    private function photosRouter(array $resourceOptions = []): Router
    {
        $router = $this->newRouter();
        Route::useRouter($router);
        $router->resource('photos', StubPhotosController::class, $resourceOptions);

        return $router;
    }

    private function newRouter(): Router
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        $c->singleton(ErrorRenderer::class, static fn (): ErrorRenderer => new ErrorRenderer());

        return new Router($c);
    }
}

final class StubPhotosController
{
    public function index(): Response
    {
        return Response::make('index');
    }

    public function create(): Response
    {
        return Response::make('create');
    }

    public function store(): Response
    {
        return Response::make('store');
    }

    public function show(string $photo): Response
    {
        return Response::make('show:' . $photo);
    }

    public function edit(string $photo): Response
    {
        return Response::make('edit:' . $photo);
    }

    public function update(string $photo): Response
    {
        return Response::make('update:' . $photo);
    }

    public function destroy(string $photo): Response
    {
        return Response::make('destroy:' . $photo);
    }
}

final class StubCategoryController
{
    public function show(string $category): Response
    {
        return Response::make('cat:' . $category);
    }
}

final class ResourceTestMiddleware
{
    public function handle(Request $req, callable $next): Response
    {
        return Response::make('mw:' . $next($req)->body());
    }
}
