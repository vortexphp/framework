<?php

declare(strict_types=1);

namespace Vortex;

use Psr\SimpleCache\CacheInterface as Psr16CacheInterface;
use Vortex\Cache\CacheManager;
use Vortex\Broadcasting\Contracts\Broadcaster;
use Vortex\Broadcasting\RedisBroadcaster;
use Vortex\Broadcasting\SyncBroadcaster;
use Vortex\Cache\Psr16Cache;
use Vortex\Config\Repository;
use Vortex\Contracts\Cache as CacheContract;
use Vortex\Contracts\Mailer;
use Vortex\Database\Connection;
use Vortex\Database\DatabaseManager;
use Vortex\Events\Dispatcher;
use Vortex\Events\DispatcherFactory;
use Vortex\Files\LocalPublicStorage;
use Vortex\Files\Storage;
use Vortex\Http\Csrf;
use Vortex\Http\ErrorRenderer;
use Vortex\Http\Cookie;
use Vortex\Http\Request;
use Vortex\Http\Session;
use Vortex\Http\SessionManager;
use Vortex\I18n\Translator;
use Vortex\Mail\MailFactory;
use Vortex\Queue\Contracts\QueueDriver;
use Vortex\Queue\DatabaseQueue;
use Vortex\Queue\FailedJobStore;
use Vortex\Queue\RedisQueue;
use Vortex\Support\PhpRedisConnect;
use Vortex\Routing\RouteDiscovery;
use Vortex\Routing\Router;
use Vortex\Schedule\Schedule;
use Vortex\Support\Env;
use Vortex\Support\Log;
use Vortex\View\Factory;
use Vortex\View\View;
use Twig\Extension\ExtensionInterface;

final class Application
{
    private function __construct(
        private readonly string $basePath,
        private readonly Container $container,
    ) {
    }

    /**
     * @param null|callable(Container, string): void $configure
     */
    public static function boot(string $basePath, ?callable $configure = null): self
    {
        $basePath = rtrim($basePath, '/');
        Log::setBasePath($basePath);
        Storage::setBasePath($basePath);
        Env::load($basePath . '/.env');

        $container = new Container();
        $container->instance(Container::class, $container);
        $container->singleton(Repository::class, static fn (): Repository => new Repository($basePath . '/config'));
        Repository::setInstance($container->make(Repository::class));
        $container->singleton(DatabaseManager::class, static fn (): DatabaseManager => DatabaseManager::fromRepository());
        $container->singleton(Connection::class, static fn (Container $c): Connection => $c->make(DatabaseManager::class)->connection());
        $container->singleton(DatabaseQueue::class, static function (Container $c): DatabaseQueue {
            $table = Repository::get('queue.table', 'jobs');

            return new DatabaseQueue(
                $c->make(Connection::class),
                is_string($table) && $table !== '' ? $table : 'jobs',
            );
        });
        $container->singleton(QueueDriver::class, static function (Container $c): QueueDriver {
            $name = Repository::get('queue.driver', 'database');
            if ($name === 'redis') {
                /** @var array<string, mixed> $redisCfg */
                $redisCfg = Repository::get('queue.redis', []);
                if (! is_array($redisCfg)) {
                    $redisCfg = [];
                }
                $p = $redisCfg['prefix'] ?? 'vortex:q:';
                $prefix = is_string($p) && $p !== '' ? $p : 'vortex:q:';

                return new RedisQueue(PhpRedisConnect::connect($redisCfg), $prefix);
            }

            return $c->make(DatabaseQueue::class);
        });
        $container->singleton(FailedJobStore::class, static function (Container $c): FailedJobStore {
            $t = Repository::get('queue.failed_jobs_table', 'failed_jobs');
            if ($t === false || $t === '' || $t === null) {
                return new FailedJobStore($c->make(Connection::class), '');
            }

            return new FailedJobStore($c->make(Connection::class), is_string($t) ? $t : 'failed_jobs');
        });
        $container->singleton(CacheManager::class, static fn (): CacheManager => CacheManager::fromRepository($basePath));
        $container->singleton(CacheContract::class, static fn (Container $c): CacheContract => $c->make(CacheManager::class)->store());
        $container->singleton(Psr16CacheInterface::class, static fn (Container $c): Psr16CacheInterface => new Psr16Cache($c->make(CacheContract::class)));
        $container->singleton(Dispatcher::class, static fn (Container $c): Dispatcher => DispatcherFactory::make($c));
        $container->singleton(SyncBroadcaster::class, static fn (): SyncBroadcaster => new SyncBroadcaster());
        $container->singleton(Broadcaster::class, static function (Container $c): Broadcaster {
            $driver = Repository::get('broadcasting.driver', 'sync');
            if ($driver !== 'redis') {
                return $c->make(SyncBroadcaster::class);
            }

            /** @var array<string, mixed> $redisCfg */
            $redisCfg = Repository::get('broadcasting.redis', []);
            if (! is_array($redisCfg)) {
                $redisCfg = [];
            }
            $p = $redisCfg['prefix'] ?? 'vortex:broadcast:';
            $prefix = is_string($p) && $p !== '' ? $p : 'vortex:broadcast:';

            return new RedisBroadcaster(PhpRedisConnect::connect($redisCfg), $prefix, $c->make(SyncBroadcaster::class));
        });
        $container->singleton(Mailer::class, static fn (): Mailer => MailFactory::make($basePath));
        $container->singleton(SessionManager::class, static fn (): SessionManager => SessionManager::fromRepository());
        $container->singleton(Session::class, static fn (Container $c): Session => new Session($c->make(SessionManager::class)->store()));
        Session::setInstance($container->make(Session::class));
        $container->singleton(Csrf::class, static fn (): Csrf => new Csrf());
        Csrf::setInstance($container->make(Csrf::class));
        $container->singleton(LocalPublicStorage::class, static function () use ($basePath): LocalPublicStorage {
            return new LocalPublicStorage($basePath . '/public');
        });
        LocalPublicStorage::setInstance($container->make(LocalPublicStorage::class));
        $container->singleton(Translator::class, static function () use ($basePath): Translator {
            $supported = Repository::get('app.locales', ['en', 'bg']);
            if (! is_array($supported)) {
                $supported = ['en', 'bg'];
            }
            /** @var list<string> $supported */
            $supported = array_values(array_filter(array_map(strval(...), $supported)));

            return new Translator(
                $basePath . '/lang',
                (string) Repository::get('app.locale', 'en'),
                (string) Repository::get('app.fallback_locale', 'en'),
                $supported,
            );
        });
        Translator::setInstance($container->make(Translator::class));
        $container->singleton(Factory::class, static function () use ($basePath): Factory {
            $debug = (bool) Repository::get('app.debug', false);
            $factory = new Factory(
                $basePath . '/assets/views',
                $debug,
                $debug ? null : $basePath . '/storage/cache/twig',
            );

            $configuredExtensions = Repository::get('app.twig_extensions', []);
            if (is_array($configuredExtensions)) {
                foreach ($configuredExtensions as $extensionClass) {
                    if (! is_string($extensionClass) || $extensionClass === '' || ! class_exists($extensionClass)) {
                        continue;
                    }

                    $extension = new $extensionClass();
                    if (! $extension instanceof ExtensionInterface) {
                        continue;
                    }

                    $factory->addExtension($extension);
                }
            }

            return $factory;
        });

        View::useFactory($container->make(Factory::class));
        View::share('appName', (string) Repository::get('app.name', 'App'));

        $container->singleton(Router::class, Router::class);
        $container->singleton(ErrorRenderer::class, static fn (): ErrorRenderer => new ErrorRenderer());

        Schedule::clear();
        Schedule::loadFromRepository();

        if ($configure !== null) {
            $configure($container, $basePath);
        }

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
