<?php

declare(strict_types=1);

namespace Vortex\Console\Commands;

use Vortex\Console\Command;
use Vortex\Console\Input;
use Vortex\Support\AppPaths;

final class MakeMigrationCommand extends Command
{
    public function __construct(
        private readonly string $basePath,
    ) {
        parent::__construct($basePath);
    }

    public function description(): string
    {
        return 'Create a new migration class under the configured migrations directory (config/paths.php).';
    }

    protected function execute(Input $input): int
    {
        $tokens = $input->tokens();
        if ($tokens === []) {
            $this->error('Usage: make:migration <name> — e.g. make:migration create_widgets_table');

            return 1;
        }

        $name = $this->normalizeMigrationName(implode(' ', $tokens));
        if ($name === '') {
            $this->error('Migration name must contain letters or numbers.');

            return 1;
        }

        try {
            $paths = AppPaths::forBase($this->basePath);
        } catch (\InvalidArgumentException $e) {
            $this->error('Invalid config/paths.php: ' . $e->getMessage());

            return 1;
        }

        $dir = $paths->migrationsDirectory($this->basePath);
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error('Cannot create migrations directory: ' . $dir);

            return 1;
        }

        $prefix = gmdate('Y_m_d_His');
        $file = $dir . '/' . $prefix . '_' . $name . '.php';
        if (is_file($file)) {
            $this->error('File already exists: ' . $file);

            return 1;
        }

        $contents = <<<PHP
<?php

declare(strict_types=1);

use Vortex\Database\Schema\Migration;
use Vortex\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};

PHP;

        if (file_put_contents($file, $contents) === false) {
            $this->error('Could not write: ' . $file);

            return 1;
        }

        $this->info('Created ' . $file);

        return 0;
    }

    private function normalizeMigrationName(string $raw): string
    {
        $raw = strtolower(trim($raw));
        $raw = preg_replace('/[^a-z0-9]+/', '_', $raw) ?? '';

        return trim($raw, '_');
    }
}
