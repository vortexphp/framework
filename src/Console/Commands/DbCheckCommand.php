<?php

declare(strict_types=1);

namespace Vortex\Console\Commands;

use Vortex\Application;
use Vortex\Console\Command;
use Vortex\Console\Input;
use Vortex\Console\Term;
use Vortex\Database\Connection;
use Vortex\Support\AppPaths;
use Throwable;

/**
 * Verifies PDO connectivity using config from .env / config/database.php (checklist §6).
 */
final class DbCheckCommand implements Command
{
    public function __construct(
        private readonly string $basePath,
    ) {
    }

    public function name(): string
    {
        return 'db-check';
    }

    public function description(): string
    {
        return 'Run SELECT 1 through the app database connection (.env + config/database.php).';
    }

    public function run(Input $input): int
    {
        $base = $this->basePath;

        require_once $base . '/vendor/autoload.php';

        try {
            $paths = AppPaths::forBase($base);
        } catch (\InvalidArgumentException $e) {
            fwrite(STDERR, Term::style('1;31', 'Invalid config/paths.php:') . ' ' . $e->getMessage() . "\n");

            return 1;
        }

        try {
            $container = Application::boot($base)->container();
            $connection = $container->make(Connection::class);
            $connection->selectOne('SELECT 1 AS ok');
        } catch (Throwable $e) {
            fwrite(STDERR, Term::style('1;31', 'Database check failed:') . ' ' . $e->getMessage() . "\n");

            return 1;
        }

        fwrite(STDERR, Term::style('1;32', 'Database connection OK') . " (SELECT 1)\n");

        return 0;
    }
}
