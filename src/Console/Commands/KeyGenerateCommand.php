<?php

declare(strict_types=1);

namespace Vortex\Console\Commands;

use Vortex\Console\Command;
use Vortex\Console\Input;

/**
 * Writes {@code APP_KEY} to {@code .env} using the same {@code base64:} format as {@see \Vortex\Crypto\Crypt}.
 */
final class KeyGenerateCommand extends Command
{
    public function name(): string
    {
        return 'key:generate';
    }

    public function description(): string
    {
        return 'Set APP_KEY in .env (base64-encoded 32 random bytes). Use --show to print a key without writing .env.';
    }

    protected function execute(Input $input): int
    {
        $key = 'base64:' . base64_encode(random_bytes(32));

        if ($input->flag('show')) {
            fwrite(STDOUT, $key . "\n");

            return 0;
        }

        $envPath = $this->basePath() . '/.env';
        if (! is_file($envPath)) {
            $this->error('No .env file found. Copy .env.example to .env first.');

            return 1;
        }

        $contents = file_get_contents($envPath);
        if ($contents === false) {
            $this->error('Could not read .env.');

            return 1;
        }

        $pattern = '/^APP_KEY=.*$/m';
        $line = 'APP_KEY=' . $key;
        if (preg_match($pattern, $contents) === 1) {
            $updated = preg_replace($pattern, $line, $contents, 1);
        } else {
            $trimmed = rtrim($contents, "\r\n");
            $suffix = $trimmed === '' ? '' : "\n";
            $updated = $trimmed . $suffix . $line . "\n";
        }

        if (! is_string($updated)) {
            $this->error('Could not update .env.');

            return 1;
        }

        if (file_put_contents($envPath, $updated, LOCK_EX) === false) {
            $this->error('Could not write .env (check permissions).');

            return 1;
        }

        $this->info('APP_KEY set in .env.');

        return 0;
    }
}
