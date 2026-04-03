<?php

declare(strict_types=1);

namespace Vortex;

use Vortex\Cache\CacheFactory;
use Vortex\Config\Repository;
use Vortex\Contracts\Cache;
use Vortex\Contracts\Mailer;
use Vortex\Database\Connection;
use Vortex\Events\Dispatcher;
use Vortex\Events\DispatcherFactory;
use Vortex\Files\Storage;
use Vortex\Mail\MailFactory;
use Vortex\Http\Cookie;
use Vortex\Http\Request;
use Vortex\Routing\RouteDiscovery;
use Vortex\Routing\Router;
use Vortex\Support\Log;
use Vortex\View\Factory;
use Vortex\View\View;

final class Application
{
    private function __construct(
        private readonly string $basePath,
        private readonly Container $container,
    ) {
    }

    public static function boot(string $basePath): self
    {
        $basePath = rtrim($basePath, '/');
        Log::setBasePath($basePath);
        Storage::setBasePath($basePath);

        $container = new Container();
        $container->instance(Container::class, $container);
        $container->singleton(Repository::class, static fn (): Repository => new Repository($basePath . '/config'));
        Repository::setInstance($container->make(Repository::class));
        $container->singleton(Connection::class, static fn (): Connection => new Connection());
        $container->singleton(Cache::class, static fn (): Cache => CacheFactory::make($basePath));
        $container->singleton(Dispatcher::class, static fn (Container $c): Dispatcher => DispatcherFactory::make($c));
        $container->singleton(Mailer::class, static fn (): Mailer => MailFactory::make($basePath));
        $container->singleton(Factory::class, static function () use ($basePath): Factory {
            $debug = (bool) Repository::get('app.debug', false);

            return new Factory(
                $basePath . '/assets/views',
                $debug,
                $debug ? null : $basePath . '/storage/cache/twig',
            );
        });

        View::useFactory($container->make(Factory::class));

        $container->singleton(Router::class, Router::class);

        AppContext::set($container);

        $router = $container->make(Router::class);
        RouteDiscovery::loadHttpRoutes($router, $basePath);

        return new self($basePath, $container);
    }

    /**
     * @param list<class-string> $globalMiddleware
     */
    public function run(array $globalMiddleware = []): void
    {
        $request = Request::capture();
        Request::setCurrent($request);
        $response = $this->container->make(Router::class)->dispatch($request, $globalMiddleware);
        Cookie::flushQueued($response);
        $response->send();
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }
}
