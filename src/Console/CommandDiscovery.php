<?php

declare(strict_types=1);

namespace Vortex\Console;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Vortex\Support\AppPaths;

final class CommandDiscovery
{
    /**
     * Registers every concrete {@see Command} under {@see AppPaths::commandsDirectory()} (default **`app/Commands`**), recursively. Files must be named **`*.php`** and end with **`Command.php`**; the class FQCN uses the PSR-4 namespace for that directory (e.g. **`App\Commands\`**).
     */
    public static function registerAppCommands(ConsoleApplication $app): void
    {
        $basePath = $app->basePath();
        $paths = AppPaths::forBase($basePath);
        $dir = $paths->commandsDirectory($basePath);
        $prefix = $paths->commandsNamespace() . '\\';

        foreach (self::commandPhpFiles($dir) as $file) {
            $fqcn = self::classFromFile($dir, $file, $prefix);
            if ($fqcn === null || ! class_exists($fqcn, true)) {
                continue;
            }

            $ref = new ReflectionClass($fqcn);
            if ($ref->isAbstract() || ! $ref->isSubclassOf(Command::class)) {
                continue;
            }

            $app->register($ref->newInstance());
        }
    }

    /**
     * @return list<string>
     */
    private static function commandPhpFiles(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $flags = FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, $flags));
        $out = [];
        foreach ($it as $info) {
            if (! $info->isFile()) {
                continue;
            }
            $path = $info->getPathname();
            if (! str_ends_with($path, 'Command.php')) {
                continue;
            }
            $out[] = $path;
        }
        sort($out);

        return $out;
    }

    private static function classFromFile(string $commandsDir, string $absolutePath, string $prefix): ?string
    {
        $commandsDir = rtrim(str_replace('\\', '/', $commandsDir), '/');
        $absolutePath = str_replace('\\', '/', $absolutePath);
        if (! str_starts_with($absolutePath, $commandsDir . '/')) {
            return null;
        }

        $rel = substr($absolutePath, strlen($commandsDir) + 1);
        if ($rel === false || $rel === '' || ! str_ends_with($rel, '.php')) {
            return null;
        }

        $relClass = substr($rel, 0, -4);

        return $prefix . str_replace('/', '\\', $relClass);
    }
}
