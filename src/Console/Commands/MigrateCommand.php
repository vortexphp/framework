<?php

declare(strict_types=1);

namespace Vortex\Console\Commands;

use Vortex\Console\Command;
use Vortex\Console\Input;
use Vortex\Console\Term;
use Vortex\Container;
use Vortex\Database\Connection;
use Throwable;

/**
 * Executes {@see database/schema.sql} against the configured PDO connection (SQLite-friendly).
 */
final class MigrateCommand implements Command
{
    public function __construct(
        private readonly string $basePath,
    ) {
    }

    public function name(): string
    {
        return 'migrate';
    }

    public function description(): string
    {
        return 'Run database/schema.sql (CREATE TABLE IF NOT EXISTS, etc.).';
    }

    public function run(Input $input): int
    {
        $path = $this->basePath . '/database/schema.sql';
        if (! is_file($path)) {
            fwrite(STDERR, Term::style('1;31', 'Missing') . ' database/schema.sql' . "\n");

            return 1;
        }

        $sql = file_get_contents($path);
        if ($sql === false || trim($sql) === '') {
            fwrite(STDERR, Term::style('1;31', 'Empty or unreadable') . ' database/schema.sql' . "\n");

            return 1;
        }

        $bootstrap = $this->basePath . '/bootstrap/app.php';
        if (! is_file($bootstrap)) {
            fwrite(STDERR, Term::style('1;31', 'Missing bootstrap/app.php') . "\n");

            return 1;
        }

        require_once $this->basePath . '/vendor/autoload.php';

        try {
            /** @var Container $container */
            $container = require $bootstrap;
            $pdo = $container->make(Connection::class)->pdo();
            $pdo->exec($sql);

            $patches = glob($this->basePath . '/database/patches/*.sql') ?: [];
            sort($patches, SORT_STRING);
            foreach ($patches as $patchPath) {
                $patchSql = file_get_contents($patchPath);
                if ($patchSql === false || trim($patchSql) === '') {
                    continue;
                }
                try {
                    $pdo->exec($patchSql);
                } catch (Throwable $e) {
                    $msg = $e->getMessage();
                    if (stripos($msg, 'duplicate column') !== false) {
                        continue;
                    }
                    throw $e;
                }
            }
        } catch (Throwable $e) {
            fwrite(STDERR, Term::style('1;31', 'Migrate failed:') . ' ' . $e->getMessage() . "\n");

            return 1;
        }

        fwrite(STDERR, Term::style('1;32', 'OK') . ' — schema.sql + database/patches/*.sql' . "\n");

        return 0;
    }
}
