<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Application;
use Vortex\Config\Repository;
use Vortex\Console\Command;
use Vortex\Console\ConsoleApplication;
use Vortex\Console\Input;
use Vortex\Container;
use Vortex\Package\Package;
use Vortex\Package\PackageRegistry;

final class PackageTest extends TestCase
{
    public function testRegisterRunsBeforeBootAndBootRunsAfterRoutes(): void
    {
        $base = $this->tempProjectRoot();
        $this->writeMinimalProjectLayout($base);
        file_put_contents(
            $base . '/config/app.php',
            "<?php\nreturn ['debug' => true, 'packages' => [\\Vortex\\Tests\\SpyPackage::class]];\n",
        );
        file_put_contents(
            $base . '/routes/web.php',
            "<?php\nuse Vortex\\Http\\Response;\nuse Vortex\\Routing\\Route;\nRoute::get('/app', static fn (): Response => Response::make('app'));\n",
        );

        SpyPackage::$log = [];
        Application::boot($base);
        self::assertSame(['register', 'boot'], SpyPackage::$log);
    }

    public function testConsoleHookRunsBeforeAppConsoleRoutes(): void
    {
        $base = $this->tempProjectRoot();
        mkdir($base . '/config', 0777, true);
        file_put_contents($base . '/config/app.php', "<?php\nreturn ['packages' => [\\Vortex\\Tests\\SpyPackage::class]];\n");
        mkdir($base . '/routes', 0777, true);
        file_put_contents(
            $base . '/routes/console.php',
            <<<'PHP'
<?php
declare(strict_types=1);
use Vortex\Tests\SpyPackage;
use Vortex\Tests\EchoCommand;
use Vortex\Vortex;

Vortex::command(EchoCommand::class);
SpyPackage::$log[] = 'after_console_routes';
PHP,
        );

        SpyPackage::$log = [];
        ConsoleApplication::boot($base);

        self::assertSame(['console', 'after_console_routes'], SpyPackage::$log);
    }

    public function testDispatchConsoleWithoutRoutesDirectory(): void
    {
        $base = $this->tempProjectRoot();
        mkdir($base . '/config', 0777, true);
        file_put_contents($base . '/config/app.php', "<?php\nreturn ['packages' => [\\Vortex\\Tests\\SpyPackage::class]];\n");

        SpyPackage::$log = [];
        ConsoleApplication::boot($base);

        self::assertSame(['console'], SpyPackage::$log);
    }

    public function testPackageRegistryRejectsNonPackageClass(): void
    {
        $base = $this->tempProjectRoot();
        mkdir($base . '/config', 0777, true);
        file_put_contents($base . '/config/app.php', "<?php\nreturn ['packages' => [\\stdClass::class]];\n");
        Repository::setInstance(new Repository($base . '/config'));

        try {
            $this->expectException(\InvalidArgumentException::class);
            PackageRegistry::register(new Container(), $base);
        } finally {
            Repository::forgetInstance();
        }
    }

    private function tempProjectRoot(): string
    {
        $base = sys_get_temp_dir() . '/vortex_pkg_' . bin2hex(random_bytes(4));
        if (! mkdir($base, 0777, true) && ! is_dir($base)) {
            self::fail('temp dir');
        }

        return $base;
    }

    private function writeMinimalProjectLayout(string $base): void
    {
        mkdir($base . '/config', 0777, true);
        file_put_contents(
            $base . '/config/database.php',
            <<<'PHP'
<?php
return [
    'default' => 'default',
    'connections' => [
        'default' => ['driver' => 'sqlite', 'database' => ':memory:'],
    ],
];
PHP,
        );
        file_put_contents($base . '/config/cache.php', "<?php\nreturn [];\n");
        file_put_contents(
            $base . '/config/session.php',
            <<<'PHP'
<?php
return [
    'default' => 'native',
    'stores' => [
        'native' => [
            'driver' => 'native',
            'name' => 'test_sess',
            'lifetime' => 120,
            'secure' => false,
            'samesite' => 'Lax',
        ],
        'null' => ['driver' => 'null'],
    ],
];
PHP,
        );
        mkdir($base . '/routes', 0777, true);
        mkdir($base . '/lang', 0777, true);
        mkdir($base . '/resources/views', 0777, true);
        mkdir($base . '/storage/cache/twig', 0777, true);
    }
}

final class SpyPackage extends Package
{
    /** @var list<string> */
    public static array $log = [];

    public function register(Container $container, string $basePath): void
    {
        self::$log[] = 'register';
    }

    public function boot(Container $container, string $basePath): void
    {
        self::$log[] = 'boot';
    }

    public function console(ConsoleApplication $app, string $basePath): void
    {
        self::$log[] = 'console';
    }
}

final class EchoCommand extends Command
{
    public function name(): string
    {
        return 'echo_reg';
    }

    public function description(): string
    {
        return 'test';
    }

    protected function execute(Input $input): int
    {
        return 0;
    }
}
