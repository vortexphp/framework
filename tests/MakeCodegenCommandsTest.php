<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Console\Commands\MakeCommandCommand;
use Vortex\Console\Commands\MakeControllerCommand;
use Vortex\Console\Commands\MakeMigrationCommand;
use Vortex\Console\Commands\MakeModelCommand;
use Vortex\Console\Input;
use Vortex\Database\Schema\Migration;

final class MakeCodegenCommandsTest extends TestCase
{
    private string $base = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir() . '/vortex-make-' . bin2hex(random_bytes(4));
        mkdir($this->base . '/database/migrations', 0700, true);
        mkdir($this->base . '/config', 0700, true);
        file_put_contents($this->base . '/config/paths.php', "<?php\nreturn [];\n");
    }

    protected function tearDown(): void
    {
        if ($this->base !== '' && is_dir($this->base)) {
            foreach (glob($this->base . '/database/migrations/*.php') ?: [] as $f) {
                unlink($f);
            }
            @rmdir($this->base . '/database/migrations');
            @rmdir($this->base . '/database');
            foreach (glob($this->base . '/app/Commands/*.php') ?: [] as $f) {
                unlink($f);
            }
            @rmdir($this->base . '/app/Commands');
            foreach (glob($this->base . '/app/Models/*.php') ?: [] as $f) {
                unlink($f);
            }
            @rmdir($this->base . '/app/Models');
            foreach (glob($this->base . '/app/Controllers/*.php') ?: [] as $f) {
                unlink($f);
            }
            @rmdir($this->base . '/app/Controllers');
            @rmdir($this->base . '/app');
            if (is_file($this->base . '/config/paths.php')) {
                unlink($this->base . '/config/paths.php');
            }
            @rmdir($this->base . '/config');
            @rmdir($this->base);
        }
        parent::tearDown();
    }

    public function testMakeMigrationWritesRunnableClass(): void
    {
        $cmd = new MakeMigrationCommand();
        $cmd->setBasePath($this->base);
        self::assertSame(0, $cmd->run(Input::fromArgv(['vortex', 'make:migration', 'demo_table'])));

        $files = glob($this->base . '/database/migrations/*_demo_table.php') ?: [];
        self::assertCount(1, $files);
        $migration = require $files[0];
        self::assertInstanceOf(Migration::class, $migration);
        $src = (string) file_get_contents($files[0]);
        self::assertStringContainsString('declare(strict_types=1);', $src);
    }

    public function testMakeMigrationRequiresName(): void
    {
        $cmd = new MakeMigrationCommand();
        $cmd->setBasePath($this->base);
        self::assertSame(1, $cmd->run(Input::fromArgv(['vortex', 'make:migration'])));
    }

    public function testMakeCommandScaffoldsFile(): void
    {
        $cmd = new MakeCommandCommand();
        $cmd->setBasePath($this->base);
        self::assertSame(0, $cmd->run(Input::fromArgv(['vortex', 'make:command', 'demo-widget'])));

        $file = $this->base . '/app/Commands/DemoWidgetCommand.php';
        self::assertFileExists($file);
        self::assertStringContainsString('namespace App\\Commands', (string) file_get_contents($file));
        self::assertStringContainsString('final class DemoWidgetCommand', (string) file_get_contents($file));
    }

    public function testMakeControllerScaffoldsInvokableController(): void
    {
        $cmd = new MakeControllerCommand();
        $cmd->setBasePath($this->base);
        self::assertSame(0, $cmd->run(Input::fromArgv(['vortex', 'make:controller', 'home'])));

        $file = $this->base . '/app/Controllers/HomeController.php';
        self::assertFileExists($file);
        $src = (string) file_get_contents($file);
        self::assertStringContainsString('namespace App\\Controllers', $src);
        self::assertStringContainsString('final class HomeController extends Controller', $src);
        self::assertStringContainsString('function __invoke(Request $request): Response', $src);
    }

    public function testMakeModelScaffoldsFile(): void
    {
        $cmd = new MakeModelCommand();
        $cmd->setBasePath($this->base);
        self::assertSame(0, $cmd->run(Input::fromArgv(['vortex', 'make:model', 'Kitten'])));

        $file = $this->base . '/app/Models/Kitten.php';
        self::assertFileExists($file);
        $src = (string) file_get_contents($file);
        self::assertStringContainsString('namespace App\\Models', $src);
        self::assertStringContainsString('final class Kitten extends Model', $src);
        self::assertStringNotContainsString('$table', $src);
    }

    public function testMakeModelWithTableOption(): void
    {
        $cmd = new MakeModelCommand();
        $cmd->setBasePath($this->base);
        self::assertSame(0, $cmd->run(Input::fromArgv(['vortex', 'make:model', 'Puppy', '--table=puppies'])));

        $src = (string) file_get_contents($this->base . '/app/Models/Puppy.php');
        self::assertStringContainsString("\$table = 'puppies'", $src);
    }

    public function testMakeModelWithSpacedTableOption(): void
    {
        $cmd = new MakeModelCommand();
        $cmd->setBasePath($this->base);
        self::assertSame(0, $cmd->run(Input::fromArgv(['vortex', 'make:model', 'Fish', '--table', 'fish'])));

        $src = (string) file_get_contents($this->base . '/app/Models/Fish.php');
        self::assertStringContainsString("\$table = 'fish'", $src);
    }

    public function testMakeModelWithMigrationFlagCreatesTableMigration(): void
    {
        $cmd = new MakeModelCommand();
        $cmd->setBasePath($this->base);
        self::assertSame(0, $cmd->run(Input::fromArgv(['vortex', 'make:model', 'Post', '-m'])));

        self::assertFileExists($this->base . '/app/Models/Post.php');
        $files = glob($this->base . '/database/migrations/*_create_posts_table.php') ?: [];
        self::assertCount(1, $files);
        $migration = require $files[0];
        self::assertInstanceOf(Migration::class, $migration);
        $msrc = (string) file_get_contents($files[0]);
        self::assertStringContainsString("Schema::create('posts'", $msrc);
        self::assertStringContainsString('->id()', $msrc);
        self::assertStringContainsString('->timestamps()', $msrc);
        self::assertStringContainsString("Schema::dropIfExists('posts')", $msrc);
    }

    public function testMakeModelWithLongMigrationFlagUsesTableOptionForMigration(): void
    {
        $cmd = new MakeModelCommand();
        $cmd->setBasePath($this->base);
        self::assertSame(0, $cmd->run(Input::fromArgv(['vortex', 'make:model', 'Article', '--table=entries', '--migration'])));

        $files = glob($this->base . '/database/migrations/*_create_entries_table.php') ?: [];
        self::assertCount(1, $files);
        self::assertStringContainsString("Schema::create('entries'", (string) file_get_contents($files[0]));
    }
}
