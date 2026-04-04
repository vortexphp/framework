<?php

declare(strict_types=1);

namespace Vortex\Console\Commands;

use Vortex\Console\Command;
use Vortex\Console\Input;
use Vortex\Console\Term;
use Vortex\Database\Connection;
use Vortex\Support\AppPaths;
use Throwable;

/**
 * Verifies PDO connectivity using config from .env / config/database.php (checklist §6).
 */
final class DbCheckCommand extends Command
{
    public function name(): string
    {
        return 'db-check';
    }

    public function description(): string
    {
        return 'Run SELECT 1 through the app database connection (.env + config/database.php).';
    }

    protected function execute(Input $input): int
    {
        $base = $this->basePath();

        try {
            $paths = AppPaths::forBase($base);
        } catch (\InvalidArgumentException $e) {
            fwrite(STDERR, Term::style('1;31', 'Invalid config/paths.php:') . ' ' . $e->getMessage() . "\n");

            return 1;
        }

        try {
            $container = $this->app()->container();
            $connection = $container->make(Connection::class);
            $connection->selectOne('SELECT 1 AS ok');
        } catch (Throwable $e) {
            fwrite(STDERR, Term::style('1;31', 'Database check failed:') . ' ' . $e->getMessage() . "\n");

            return 1;
        }

        fwrite(STDERR, Term::style('1;32', 'Database connection OK') . " (SELECT 1)\n");

        return 0;
    }

    protected function shouldBootApplication(): bool
    {
        return true;
    }
}
