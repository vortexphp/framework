<?php

declare(strict_types=1);

namespace Vortex\Console\Commands;

use InvalidArgumentException;
use Vortex\Console\Command;
use Vortex\Console\Input;
use Vortex\Console\Stub;
use Vortex\Support\AppPaths;

final class MakeControllerCommand extends Command
{
    public function description(): string
    {
        return 'Scaffold an invokable Controller under app/Http/Controllers (config/paths.php **controllers**). Wire it in app/Routes/*.php.';
    }

    protected function execute(Input $input): int
    {
        $args = $input->arguments();
        if ($args === []) {
            $this->error('Usage: make:controller <Name> — e.g. make:controller Post or post');

            return 1;
        }

        $raw = trim(implode(' ', $args));
        if (str_contains($raw, '\\')) {
            $this->error('Use a short class name only (e.g. Post). Namespace is App\\Http\\Controllers.');

            return 1;
        }

        $base = $this->toPascalCase($raw);
        if ($base === '') {
            $this->error('Controller name must contain letters or numbers.');

            return 1;
        }

        $className = str_ends_with($base, 'Controller') ? $base : $base . 'Controller';

        try {
            $paths = AppPaths::forBase($this->basePath());
        } catch (InvalidArgumentException $e) {
            $this->error('Invalid config/paths.php: ' . $e->getMessage());

            return 1;
        }

        $dir = $paths->controllersDirectory($this->basePath());
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error('Cannot create directory: ' . $dir);

            return 1;
        }

        $file = $dir . '/' . $className . '.php';
        if (is_file($file)) {
            $this->error('File already exists: ' . $file);

            return 1;
        }

        $contents = Stub::render('controller', [
            'NAMESPACE' => 'App\\Http\\Controllers',
            'CLASS' => $className,
        ]);

        if (file_put_contents($file, $contents) === false) {
            $this->error('Could not write: ' . $file);

            return 1;
        }

        $this->info('Created ' . $file);
        $fqcn = 'App\\Http\\Controllers\\' . $className;
        $this->line('Route example: Route::get(\'/\', \\' . $fqcn . '::class);');

        return 0;
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
}
