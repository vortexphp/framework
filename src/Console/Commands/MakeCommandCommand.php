<?php

declare(strict_types=1);

namespace Vortex\Console\Commands;

use Vortex\Console\Command;
use Vortex\Console\Input;
use Vortex\Console\Stub;

final class MakeCommandCommand extends Command
{
    public function description(): string
    {
        return 'Scaffold a Command subclass under app/Console/Commands (register it from app/Routes/*Console.php).';
    }

    protected function execute(Input $input): int
    {
        $args = $input->arguments();
        if ($args === []) {
            $this->error('Usage: make:command <name> — e.g. make:command send-welcome or SendWelcome');

            return 1;
        }

        $raw = trim(implode(' ', $args));
        $base = $this->toPascalCase($raw);
        if ($base === '') {
            $this->error('Command name must contain letters or numbers.');

            return 1;
        }

        $className = str_ends_with($base, 'Command') ? $base : $base . 'Command';

        $relDir = 'app/Console/Commands';
        $dir = $this->basePath() . '/' . $relDir;
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error('Cannot create directory: ' . $dir);

            return 1;
        }

        $file = $dir . '/' . $className . '.php';
        if (is_file($file)) {
            $this->error('File already exists: ' . $file);

            return 1;
        }

        $contents = Stub::render('command', [
            'CLASS' => $className,
        ]);

        if (file_put_contents($file, $contents) === false) {
            $this->error('Could not write: ' . $file);

            return 1;
        }

        $this->info('Created ' . $file);
        $this->line('Register with: $app->register(new \\App\\Console\\Commands\\' . $className . '());');

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
