<?php

declare(strict_types=1);

namespace Vortex\Console\Commands;

use Vortex\Console\Command;
use Vortex\Console\Input;
use Vortex\Console\Term;
use Vortex\Container;
use Vortex\Database\Connection;
use Vortex\Database\Schema\SchemaMigrator;
use Throwable;

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
        return 'Run pending database migration classes (db/migrations/*.php).';
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
            $ran = $migrator->up();
            fwrite(STDERR, Term::style('1;32', 'OK') . ' — applied ' . $ran . " migration(s)\n");
        } catch (Throwable $e) {
            fwrite(STDERR, Term::style('1;31', 'Migrate failed:') . ' ' . $e->getMessage() . "\n");

            return 1;
        }

        return 0;
    }
}
