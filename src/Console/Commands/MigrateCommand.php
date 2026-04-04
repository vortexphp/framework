<?php

declare(strict_types=1);

namespace Vortex\Console\Commands;

use Vortex\Console\Command;
use Vortex\Console\Input;
use Vortex\Console\Term;
use Vortex\Database\Connection;
use Vortex\Database\Schema\SchemaMigrator;
use Vortex\Support\AppPaths;
use Throwable;

final class MigrateCommand extends Command
{
    public function name(): string
    {
        return 'migrate';
    }

    public function description(): string
    {
        return 'Run pending migration classes (directory from config/paths.php migrations key, default database/migrations/*.php).';
    }

    protected function execute(Input $input): int
    {
        try {
            $paths = AppPaths::forBase($this->basePath());
        } catch (\InvalidArgumentException $e) {
            fwrite(STDERR, Term::style('1;31', 'Invalid config/paths.php:') . ' ' . $e->getMessage() . "\n");

            return 1;
        }

        try {
            $container = $this->app()->container();
            $migrator = new SchemaMigrator($this->basePath(), $container->make(Connection::class));
            $ran = $migrator->up();
            fwrite(STDERR, Term::style('1;32', 'OK') . ' — applied ' . $ran . " migration(s)\n");
        } catch (Throwable $e) {
            fwrite(STDERR, Term::style('1;31', 'Migrate failed:') . ' ' . $e->getMessage() . "\n");

            return 1;
        }

        return 0;
    }

    protected function shouldBootApplication(): bool
    {
        return true;
    }
}
