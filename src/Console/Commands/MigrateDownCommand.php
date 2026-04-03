<?php

declare(strict_types=1);

namespace Vortex\Console\Commands;

use Throwable;
use Vortex\Console\Command;
use Vortex\Console\Input;
use Vortex\Console\Term;
use Vortex\Container;
use Vortex\Database\Connection;
use Vortex\Database\Schema\SchemaMigrator;

final class MigrateDownCommand implements Command
{
    public function __construct(
        private readonly string $basePath,
    ) {
    }

    public function name(): string
    {
        return 'migrate:down';
    }

    public function description(): string
    {
        return 'Rollback the last migration batch.';
    }

    public function run(Input $input): int
    {
        $startup = $this->basePath . '/startup/app.php';
        if (! is_file($startup)) {
            fwrite(STDERR, Term::style('1;31', 'Missing startup/app.php') . "\n");

            return 1;
        }

        require_once $this->basePath . '/vendor/autoload.php';

        try {
            /** @var Container $container */
            $container = require $startup;
            $migrator = new SchemaMigrator($this->basePath, $container->make(Connection::class));
            $rolledBack = $migrator->down();
            fwrite(STDERR, Term::style('1;32', 'OK') . ' — rolled back ' . $rolledBack . " migration(s)\n");
        } catch (Throwable $e) {
            fwrite(STDERR, Term::style('1;31', 'Rollback failed:') . ' ' . $e->getMessage() . "\n");

            return 1;
        }

        return 0;
    }
}
