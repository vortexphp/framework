<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Console\Command;
use Vortex\Console\ConsoleApplication;
use Vortex\Console\Input;
use Vortex\Container;
use Vortex\Http\Response;
use Vortex\Routing\RouteDiscovery;
use Vortex\Routing\Router;

final class RouteDiscoveryTest extends TestCase
{
    public function testLoadHttpRoutesSkipsConsoleSuffixAndSortsFiles(): void
    {
        $base = $this->tempProjectRoot();
        $routes = $base . '/app/Routes';
        mkdir($routes, 0777, true);

        file_put_contents(
            $routes . '/Zed.php',
            <<<'PHP'
<?php
declare(strict_types=1);
use Vortex\Http\Response;
use Vortex\Routing\Route;
Route::get('/zed', static fn (): Response => Response::make('zed'));
PHP,
        );
        file_put_contents(
            $routes . '/Alpha.php',
            <<<'PHP'
<?php
declare(strict_types=1);
use Vortex\Http\Response;
use Vortex\Routing\Route;
Route::get('/alpha', static fn (): Response => Response::make('alpha'));
PHP,
        );
        file_put_contents(
            $routes . '/SkipConsole.php',
            <<<'PHP'
<?php
declare(strict_types=1);
use Vortex\Routing\Route;
throw new \RuntimeException('HTTP loader must not require *Console.php files');
PHP,
        );

        try {
            $c = new Container();
            $c->instance(Container::class, $c);
            $router = new Router($c);
            RouteDiscovery::loadHttpRoutes($router, $base);

            self::assertNotNull($router->match('GET', '/alpha'));
            self::assertNotNull($router->match('GET', '/zed'));
        } finally {
            $this->removeTree($base);
        }
    }

    public function testLoadConsoleRoutesLoadsStarConsolePhp(): void
    {
        $base = $this->tempProjectRoot();
        $routes = $base . '/app/Routes';
        mkdir($routes, 0777, true);

        file_put_contents(
            $routes . '/Console.php',
            <<<'PHP'
<?php
declare(strict_types=1);
use Vortex\Console\Command;
use Vortex\Console\ConsoleApplication;
use Vortex\Console\Input;
return static function (ConsoleApplication $app): void {
    $app->register(new class implements Command {
        public function name(): string
        {
            return 'route-discovery-fixture';
        }

        public function description(): string
        {
            return 'test';
        }

        public function run(Input $input): int
        {
            return 42;
        }
    });
};
PHP,
        );

        try {
            $app = ConsoleApplication::boot($base);
            self::assertSame(42, $app->run(['php', 'route-discovery-fixture']));
        } finally {
            $this->removeTree($base);
        }
    }

    public function testHttpRouteFileMayReturnUnusedValue(): void
    {
        $base = $this->tempProjectRoot();
        $routes = $base . '/app/Routes';
        mkdir($routes, 0777, true);
        file_put_contents(
            $routes . '/Bad.php',
            <<<'PHP'
<?php
declare(strict_types=1);
use Vortex\Http\Response;
use Vortex\Routing\Route;
Route::get('/ok', static fn (): Response => Response::make('ok'));
return 1;
PHP,
        );

        try {
            $c = new Container();
            $c->instance(Container::class, $c);
            $router = new Router($c);
            RouteDiscovery::loadHttpRoutes($router, $base);
            self::assertNotNull($router->match('GET', '/ok'));
        } finally {
            $this->removeTree($base);
        }
    }

    private function tempProjectRoot(): string
    {
        $base = sys_get_temp_dir() . '/vortex _route_discovery_' . bin2hex(random_bytes(8));
        if (is_dir($base)) {
            $this->removeTree($base);
        }

        return $base;
    }

    private function removeTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            $path = $file->getPathname();
            $file->isDir() ? @rmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
