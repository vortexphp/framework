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
        return 'Run SELECT 1 through the app database connection (uses startup/app.php + .env).';
    }

    public function run(Input $input): int
    {
        $base = $this->basePath;
        $startup = $base . '/startup/app.php';
        if (! is_file($startup)) {
            fwrite(STDERR, Term::style('1;31', 'Missing startup/app.php') . "\n");

            return 1;
        }

        require_once $base . '/vendor/autoload.php';

        try {
            /** @var Container $container */
            $container = require $startup;
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
