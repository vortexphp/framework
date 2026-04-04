<?php

declare(strict_types=1);

namespace Vortex\Console\Commands;

use InvalidArgumentException;
use Vortex\Console\Command;
use Vortex\Console\Input;
use Vortex\Console\Stub;
use Vortex\Support\AppPaths;

final class MakeModelCommand extends Command
{
    public function description(): string
    {
        return 'Scaffold a Model under app/Models (config/paths.php **models**). Optional **--table=**; **-m** / **--migration** adds a create-table migration (id + **timestamps**).';
    }

    protected function execute(Input $input): int
    {
        $args = $input->arguments();
        if ($args === []) {
            $this->error('Usage: make:model <Name> [--table=table_name] [-m|--migration] — e.g. make:model Post -m');

            return 1;
        }

        $rawName = trim(implode(' ', $args));
        if (str_contains($rawName, '\\')) {
            $this->error('Use a short class name only (e.g. Post). Namespace is App\\Models.');

            return 1;
        }

        $base = $this->toPascalCase($rawName);
        if ($base === '') {
            $this->error('Model name must contain letters or numbers.');

            return 1;
        }

        try {
            $paths = AppPaths::forBase($this->basePath());
        } catch (InvalidArgumentException $e) {
            $this->error('Invalid config/paths.php: ' . $e->getMessage());

            return 1;
        }

        $dir = $paths->modelsDirectory($this->basePath());
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error('Cannot create directory: ' . $dir);

            return 1;
        }

        $file = $dir . '/' . $base . '.php';
        if (is_file($file)) {
            $this->error('File already exists: ' . $file);

            return 1;
        }

        $tableOpt = $input->option('table', '');
        $table = '';
        if (is_string($tableOpt)) {
            $table = trim($tableOpt);
        } elseif ($input->hasOption('table') && $tableOpt === true) {
            $this->error('Option --table requires a value (e.g. --table=posts or --table posts).');

            return 1;
        }
        try {
            $tableProperty = $table !== ''
                ? "    protected static ?string \$table = '" . $this->sanitizeTableName($table) . "';\n\n"
                : '';
            $migrationTable = $table !== '' ? $this->sanitizeTableName($table) : $this->inferTableName($base);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $contents = Stub::render('model', [
            'NAMESPACE' => 'App\\Models',
            'CLASS' => $base,
            'TABLE_PROPERTY' => $tableProperty,
        ]);

        if (file_put_contents($file, $contents) === false) {
            $this->error('Could not write: ' . $file);

            return 1;
        }

        $this->info('Created ' . $file);

        if ($input->flag('m') || $input->flag('migration')) {
            return $this->writeModelMigration($paths, $migrationTable);
        }

        return 0;
    }

    private function inferTableName(string $classBase): string
    {
        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $classBase));
        $plural = $this->pluralizeSnake($snake);

        return $this->sanitizeTableName($plural);
    }

    private function pluralizeSnake(string $snake): string
    {
        if ($snake !== '' && preg_match('/[^aeiou]y$/', $snake) === 1) {
            return substr($snake, 0, -1) . 'ies';
        }

        if (preg_match('/(s|x|z|ch|sh)$/', $snake) === 1) {
            return $snake . 'es';
        }

        return $snake . 's';
    }

    private function writeModelMigration(AppPaths $paths, string $table): int
    {
        $dir = $paths->migrationsDirectory($this->basePath());
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error('Cannot create migrations directory: ' . $dir);

            return 1;
        }

        $migrationStem = $this->normalizeMigrationStem('create_' . $table . '_table');
        $prefix = gmdate('Y_m_d_His');
        $migrationFile = $dir . '/' . $prefix . '_' . $migrationStem . '.php';
        if (is_file($migrationFile)) {
            $this->error('Migration file already exists: ' . $migrationFile);

            return 1;
        }

        $body = Stub::render('migration_model', [
            'TABLE' => $table,
        ]);
        if (file_put_contents($migrationFile, $body) === false) {
            $this->error('Could not write migration: ' . $migrationFile);

            return 1;
        }

        $this->info('Created ' . $migrationFile);

        return 0;
    }

    private function normalizeMigrationStem(string $raw): string
    {
        $raw = strtolower(trim($raw));
        $raw = preg_replace('/[^a-z0-9]+/', '_', $raw) ?? '';

        return trim($raw, '_');
    }

    private function toPascalCase(string $raw): string
    {
        $raw = preg_replace('/[^a-zA-Z0-9]+/', ' ', $raw) ?? '';
        $parts = preg_split('/\s+/', trim($raw)) ?: [];
        $out = '';
        foreach ($parts as $p) {
            if ($p === '') {
                continue;
            }
            $out .= ucfirst(strtolower($p));
        }

        return $out;
    }

    private function sanitizeTableName(string $table): string
    {
        if ($table === '' || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new InvalidArgumentException('Invalid --table name (use letters, numbers, underscore; must start with letter or _).');
        }

        return $table;
    }
}
