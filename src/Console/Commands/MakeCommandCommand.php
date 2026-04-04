<?php

declare(strict_types=1);

namespace Vortex\Console\Commands;

use Vortex\Console\Command;
use Vortex\Console\Input;

final class MakeCommandCommand extends Command
{
    public function __construct(
        private readonly string $basePath,
    ) {
        parent::__construct($basePath);
    }

    public function description(): string
    {
        return 'Scaffold a Command subclass under app/Console/Commands (register it from app/Routes/*Console.php).';
    }

    protected function execute(Input $input): int
    {
        $tokens = $input->tokens();
        if ($tokens === []) {
            $this->error('Usage: make:command <name> — e.g. make:command send-welcome or SendWelcome');

            return 1;
        }

        $raw = trim(implode(' ', $tokens));
        $base = $this->toPascalCase($raw);
        if ($base === '') {
            $this->error('Command name must contain letters or numbers.');

            return 1;
        }

        $className = str_ends_with($base, 'Command') ? $base : $base . 'Command';

        $relDir = 'app/Console/Commands';
        $dir = $this->basePath . '/' . $relDir;
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error('Cannot create directory: ' . $dir);

            return 1;
        }

        $file = $dir . '/' . $className . '.php';
        if (is_file($file)) {
            $this->error('File already exists: ' . $file);

            return 1;
        }

        $contents = $this->template($className);

        if (file_put_contents($file, $contents) === false) {
            $this->error('Could not write: ' . $file);

            return 1;
        }

        $this->info('Created ' . $file);
        $this->line('Register with: $app->register(new \\App\\Console\\Commands\\' . $className . '($app->basePath()));');

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

    private function template(string $className): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Console\\Commands;

use Vortex\\Console\\Command;
use Vortex\\Console\\Input;

final class {$className} extends Command
{
    public function __construct(string \$basePath)
    {
        parent::__construct(\$basePath);
    }

    public function description(): string
    {
        return 'TODO: one-line description';
    }

    protected function execute(Input \$input): int
    {
        \$this->info('{$className} ready.');

        return 0;
    }
}

PHP;
    }
}
