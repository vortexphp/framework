<?php

declare(strict_types=1);

namespace Vortex\Console\Commands;

use Throwable;
use Vortex\Console\Command;
use Vortex\Console\Input;
use Vortex\Console\Term;
use Vortex\Database\Connection;
use Vortex\Database\Schema\SchemaMigrator;
use Vortex\Support\AppPaths;

final class MigrateDownCommand extends Command
{
    public function __construct(
        private readonly string $basePath,
    ) {
        parent::__construct($basePath);
    }

    public function name(): string
    {
        return 'migrate:down';
    }

    public function description(): string
    {
        return 'Rollback the last migration batch.';
    }

    protected function execute(Input $input): int
    {
        try {
            $paths = AppPaths::forBase($this->basePath);
        } catch (\InvalidArgumentException $e) {
            fwrite(STDERR, Term::style('1;31', 'Invalid config/paths.php:') . ' ' . $e->getMessage() . "\n");

            return 1;
        }

        try {
            $container = $this->app()->container();
            $migrator = new SchemaMigrator($this->basePath, $container->make(Connection::class));
            $rolledBack = $migrator->down();
            fwrite(STDERR, Term::style('1;32', 'OK') . ' — rolled back ' . $rolledBack . " migration(s)\n");
        } catch (Throwable $e) {
            fwrite(STDERR, Term::style('1;31', 'Rollback failed:') . ' ' . $e->getMessage() . "\n");

            return 1;
        }

        return 0;
    }

    protected function shouldBootApplication(): bool
    {
        return true;
    }
}
