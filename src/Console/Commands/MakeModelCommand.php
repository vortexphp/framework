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
        return 'Scaffold a Model under app/Models (config/paths.php **models**). Optional **--table=** override.';
    }

    protected function execute(Input $input): int
    {
        $tokens = $input->tokens();
        if ($tokens === []) {
            $this->error('Usage: make:model <Name> [--table=table_name] — e.g. make:model Post --table=posts');

            return 1;
        }

        ['args' => $args, 'options' => $options] = $this->parseTokens($tokens);
        if ($args === []) {
            $this->error('Model name required.');

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

        $table = isset($options['table']) && is_string($options['table']) ? trim($options['table']) : '';
        try {
            $tableProperty = $table !== ''
                ? "    protected static ?string \$table = '" . $this->sanitizeTableName($table) . "';\n\n"
                : '';
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

        return 0;
    }

    /**
     * @param list<string> $tokens
     * @return array{args: list<string>, options: array<string, string>}
     */
    private function parseTokens(array $tokens): array
    {
        $args = [];
        $options = [];
        foreach ($tokens as $t) {
            if (str_starts_with($t, '--') && str_contains($t, '=')) {
                $eq = strpos($t, '=');
                if ($eq !== false) {
                    $k = substr($t, 2, $eq - 2);
                    $options[$k] = substr($t, $eq + 1);
                }

                continue;
            }
            if (str_starts_with($t, '--')) {
                continue;
            }
            $args[] = $t;
        }

        return ['args' => $args, 'options' => $options];
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
