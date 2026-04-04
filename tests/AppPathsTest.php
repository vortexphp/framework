<?php

declare(strict_types=1);

namespace Vortex\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vortex\Support\AppPaths;

final class AppPathsTest extends TestCase
{
    public function testDefaultsWhenConfigMissing(): void
    {
        $base = sys_get_temp_dir() . '/vortex-paths-' . bin2hex(random_bytes(4));
        mkdir($base, 0700, true);
        try {
            $paths = AppPaths::forBase($base);
            self::assertSame('db/migrations', $paths->migrationsRelative());
            self::assertSame('app/Models', $paths->modelsRelative());
            self::assertSame('app/Http/Controllers', $paths->controllersRelative());
            self::assertSame('app/Console/Commands', $paths->commandsRelative());
            self::assertStringEndsWith('/db/migrations', str_replace('\\', '/', $paths->migrationsDirectory($base)));
            self::assertStringEndsWith('/app/Models', str_replace('\\', '/', $paths->modelsDirectory($base)));
            self::assertStringEndsWith('/app/Http/Controllers', str_replace('\\', '/', $paths->controllersDirectory($base)));
            self::assertStringEndsWith('/app/Console/Commands', str_replace('\\', '/', $paths->commandsDirectory($base)));
        } finally {
            @rmdir($base);
        }
    }

    public function testCustomValuesFromConfig(): void
    {
        $base = sys_get_temp_dir() . '/vortex-paths-cfg-' . bin2hex(random_bytes(4));
        mkdir($base . '/config', 0700, true);
        file_put_contents(
            $base . '/config/paths.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n    'migrations' => 'schema/migrations',\n    'models' => 'src/Domain/Models',\n    'controllers' => 'src/Http/Controllers',\n    'commands' => 'src/Cli',\n];\n",
        );
        try {
            $paths = AppPaths::forBase($base);
            self::assertSame('schema/migrations', $paths->migrationsRelative());
            self::assertSame('src/Domain/Models', $paths->modelsRelative());
            self::assertSame('src/Http/Controllers', $paths->controllersRelative());
            self::assertSame('src/Cli', $paths->commandsRelative());
        } finally {
            unlink($base . '/config/paths.php');
            @rmdir($base . '/config');
            @rmdir($base);
        }
    }

    public function testRejectsParentSegments(): void
    {
        $base = sys_get_temp_dir() . '/vortex-paths-bad-' . bin2hex(random_bytes(4));
        mkdir($base . '/config', 0700, true);
        file_put_contents($base . '/config/paths.php', "<?php\n\nreturn ['migrations' => '../etc/passwd'];\n");
        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage("config/paths.php [migrations]");
            AppPaths::forBase($base);
        } finally {
            unlink($base . '/config/paths.php');
            @rmdir($base . '/config');
            @rmdir($base);
        }
    }
}
