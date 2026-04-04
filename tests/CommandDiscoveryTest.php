<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Console\Command;
use Vortex\Console\CommandDiscovery;
use Vortex\Console\ConsoleApplication;

final class CommandDiscoveryTest extends TestCase
{
    private string $base = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir() . '/vortex-cmd-disc-' . bin2hex(random_bytes(4));
        mkdir($this->base . '/config', 0700, true);
        file_put_contents($this->base . '/config/paths.php', "<?php\nreturn [];\n");
        mkdir($this->base . '/app/Commands/Nested', 0700, true);
    }

    protected function tearDown(): void
    {
        if ($this->base !== '' && is_dir($this->base)) {
            $this->deleteTree($this->base . '/src');
            $this->deleteTree($this->base . '/app');
            if (is_file($this->base . '/config/paths.php')) {
                unlink($this->base . '/config/paths.php');
            }
            @rmdir($this->base . '/config');
            @rmdir($this->base);
        }
        parent::tearDown();
    }

    private function deleteTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    public function testDiscoversCommandsRecursively(): void
    {
        $this->registerAppAutoloader();

        file_put_contents(
            $this->base . '/app/Commands/PingCommand.php',
            <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Commands;
use Vortex\Console\Command;
use Vortex\Console\Input;
final class PingCommand extends Command
{
    public function description(): string { return 'ping'; }
    protected function execute(Input $input): int { return 0; }
}
PHP,
        );

        file_put_contents(
            $this->base . '/app/Commands/Nested/PongCommand.php',
            <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Commands\Nested;
use Vortex\Console\Command;
use Vortex\Console\Input;
final class PongCommand extends Command
{
    public function description(): string { return 'pong'; }
    protected function execute(Input $input): int { return 0; }
}
PHP,
        );

        $app = new ConsoleApplication($this->base);
        CommandDiscovery::registerAppCommands($app);

        self::assertSame(0, $app->run(['x', 'ping']));
        self::assertSame(0, $app->run(['x', 'pong']));
    }

    public function testSkipsAbstractCommandClasses(): void
    {
        $this->registerAppAutoloader();

        file_put_contents(
            $this->base . '/app/Commands/BaseJobCommand.php',
            <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Commands;
use Vortex\Console\Command;
use Vortex\Console\Input;
abstract class BaseJobCommand extends Command
{
    public function description(): string { return 'base'; }
    protected function execute(Input $input): int { return 0; }
}
PHP,
        );

        $app = new ConsoleApplication($this->base);
        CommandDiscovery::registerAppCommands($app);

        self::assertSame(1, $app->run(['x', 'base:job']));
    }

    public function testUsesCommandsPathFromConfig(): void
    {
        file_put_contents(
            $this->base . '/config/paths.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['commands' => 'app/Cli'];\n",
        );
        mkdir($this->base . '/app/Cli', 0700, true);
        $this->registerAppAutoloaderForAlternateCommandsDir($this->base . '/app/Cli');

        file_put_contents(
            $this->base . '/app/Cli/OtherCommand.php',
            <<<'PHP'
<?php
declare(strict_types=1);
namespace App\Cli;
use Vortex\Console\Command;
use Vortex\Console\Input;
final class OtherCommand extends Command
{
    public function description(): string { return 'other'; }
    protected function execute(Input $input): int { return 0; }
}
PHP,
        );

        $app = new ConsoleApplication($this->base);
        CommandDiscovery::registerAppCommands($app);

        self::assertSame(0, $app->run(['x', 'other']));
    }

    private function registerAppAutoloader(): void
    {
        $base = $this->base;
        spl_autoload_register(static function (string $class) use ($base): bool {
            if (! str_starts_with($class, 'App\\')) {
                return false;
            }
            $rel = str_replace('\\', '/', substr($class, strlen('App\\'))) . '.php';
            $file = $base . '/app/' . $rel;
            if (! is_file($file)) {
                return false;
            }
            require $file;

            return true;
        });
    }

    private function registerAppAutoloaderForAlternateCommandsDir(string $commandsRoot): void
    {
        $base = $this->base;
        $prefix = 'App\\Cli\\';
        spl_autoload_register(static function (string $class) use ($base, $commandsRoot, $prefix): bool {
            if (! str_starts_with($class, 'App\\')) {
                return false;
            }
            if (str_starts_with($class, $prefix)) {
                $rel = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                $file = $commandsRoot . '/' . $rel;
                if (is_file($file)) {
                    require $file;

                    return true;
                }
            }
            $rel = str_replace('\\', '/', substr($class, strlen('App\\'))) . '.php';
            $file = $base . '/app/' . $rel;
            if (! is_file($file)) {
                return false;
            }
            require $file;

            return true;
        });
    }
}
