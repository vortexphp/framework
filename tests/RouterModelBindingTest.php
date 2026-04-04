<?php

declare(strict_types=1);

namespace Vortex\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Config\Repository;
use Vortex\Container;
use Vortex\Database\Connection;
use Vortex\Database\DatabaseManager;
use Vortex\Database\Model;
use Vortex\Http\ErrorRenderer;
use Vortex\Http\Request;
use Vortex\Http\Response;
use Vortex\I18n\Translator;
use Vortex\Routing\Route;
use Vortex\Routing\Router;

final class RouterModelBindingTest extends TestCase
{
    private string $configDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        (new \ReflectionProperty(Route::class, 'router'))->setValue(null, null);

        Model::forgetRegisteredObservers();
        Model::forgetAllGlobalScopesForTesting();

        $this->configDir = sys_get_temp_dir() . '/vortex-route-bind-' . bin2hex(random_bytes(4));
        mkdir($this->configDir, 0700, true);
        file_put_contents($this->configDir . '/database.php', <<<'PHP'
<?php
return [
    'default' => 'default',
    'connections' => [
        'default' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'host' => '127.0.0.1',
            'port' => '3306',
            'username' => '',
            'password' => '',
        ],
    ],
];
PHP
        );
        mkdir($this->configDir . '/lang', 0700, true);
        file_put_contents(
            $this->configDir . '/lang/en.php',
            <<<'PHP'
<?php
return [
    'errors' => [
        'json' => ['not_found' => 'Not found'],
        '404' => ['title' => 'Not Found', 'body' => 'The page was not found.'],
    ],
];
PHP,
        );
        Translator::setInstance(new Translator($this->configDir . '/lang', 'en', 'en', ['en']));
        Repository::setInstance(new Repository($this->configDir));

        $container = new Container();
        $container->instance(Container::class, $container);
        $container->singleton(DatabaseManager::class, static fn (): DatabaseManager => DatabaseManager::fromRepository());
        $container->singleton(Connection::class, static fn (Container $c): Connection => $c->make(DatabaseManager::class)->connection());
        AppContext::set($container);

        RouteBindPost::connection()->execute(
            'CREATE TABLE route_bind_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, slug TEXT NOT NULL)',
        );
        RouteBindPost::create(['title' => 'Hello', 'slug' => 'hello']);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Route::class, 'router'))->setValue(null, null);
        Request::forgetCurrent();
        Translator::forgetInstance();
        $ref = new \ReflectionClass(AppContext::class);
        $p = $ref->getProperty('container');
        $p->setAccessible(true);
        $p->setValue(null, null);
        Repository::forgetInstance();
        if ($this->configDir !== '') {
            $lang = $this->configDir . '/lang/en.php';
            if (is_file($lang)) {
                unlink($lang);
            }
            if (is_dir($this->configDir . '/lang')) {
                rmdir($this->configDir . '/lang');
            }
            if (is_file($this->configDir . '/database.php')) {
                unlink($this->configDir . '/database.php');
            }
            if (is_dir($this->configDir)) {
                rmdir($this->configDir);
            }
        }
        parent::tearDown();
    }

    public function testModelBindingInjectsInstance(): void
    {
        $container = AppContext::container();
        $container->singleton(ErrorRenderer::class, static fn (): ErrorRenderer => new ErrorRenderer());

        $router = new Router($container);
        Route::useRouter($router);
        $router->model('post', RouteBindPost::class);
        Route::get('/posts/{post}', static fn (RouteBindPost $post): Response => Response::json(['title' => $post->title]));

        $response = $router->dispatch($this->req('GET', '/posts/1'), []);

        self::assertSame(200, $response->httpStatus());
        self::assertStringContainsString('Hello', $response->body());
    }

    public function testModelBindingBySlugColumn(): void
    {
        $container = AppContext::container();
        $container->singleton(ErrorRenderer::class, static fn (): ErrorRenderer => new ErrorRenderer());

        $router = new Router($container);
        Route::useRouter($router);
        $router->model('post', RouteBindPost::class, 'slug');
        Route::get('/p/{post}', static fn (RouteBindPost $post): Response => Response::json(['slug' => $post->slug]));

        $response = $router->dispatch($this->req('GET', '/p/hello'), []);

        self::assertSame(200, $response->httpStatus());
        self::assertStringContainsString('hello', $response->body());
    }

    public function testModelMissingReturns404(): void
    {
        $container = AppContext::container();
        $container->singleton(ErrorRenderer::class, static fn (): ErrorRenderer => new ErrorRenderer());

        $router = new Router($container);
        Route::useRouter($router);
        $router->model('post', RouteBindPost::class);
        Route::get('/posts/{post}', static fn (): Response => Response::make('unexpected', 500));

        $response = $router->dispatch($this->req('GET', '/posts/9999'), []);

        self::assertSame(404, $response->httpStatus());
    }

    public function testClosureBinding(): void
    {
        $container = AppContext::container();
        $container->singleton(ErrorRenderer::class, static fn (): ErrorRenderer => new ErrorRenderer());

        $router = new Router($container);
        Route::useRouter($router);
        $router->bind('token', static fn (string $v): ?array => $v === 'ok' ? ['token' => $v] : null);
        Route::get('/t/{token}', static fn (array $token): Response => Response::json($token));

        $good = $router->dispatch($this->req('GET', '/t/ok'), []);
        self::assertSame(200, $good->httpStatus());

        $bad = $router->dispatch($this->req('GET', '/t/nope'), []);
        self::assertSame(404, $bad->httpStatus());
    }

    public function testModelBindingRequiresModelSubclass(): void
    {
        $container = new Container();
        $container->instance(Container::class, $container);

        $router = new Router($container);
        $this->expectException(InvalidArgumentException::class);
        $router->model('x', \stdClass::class);
    }

    private function req(string $method, string $path): Request
    {
        return new Request($method, $path, [], [], [], []);
    }
}

final class RouteBindPost extends Model
{
    protected static ?string $table = 'route_bind_posts';

    /** @var list<string> */
    protected static array $fillable = ['title', 'slug'];

    protected static bool $timestamps = false;
}
