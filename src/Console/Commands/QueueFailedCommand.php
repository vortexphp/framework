<?php

declare(strict_types=1);

namespace Vortex\Console\Commands;

use Vortex\Console\Command;
use Vortex\Console\Input;
use Vortex\Console\Term;
use Vortex\Queue\FailedJobStore;

/**
 * Lists recent rows from the failed jobs table (newest first).
 *
 * Optional first token: limit (1–500, default 50).
 */
final class QueueFailedCommand extends Command
{
    public function name(): string
    {
        return 'queue:failed';
    }

    public function description(): string
    {
        return 'List recent permanently failed queue jobs (requires queue.failed_jobs_table).';
    }

    protected function execute(Input $input): int
    {
        $limit = 50;
        foreach ($input->arguments() as $t) {
            if (ctype_digit($t)) {
                $limit = max(1, min(500, (int) $t));
            }
        }

        /** @var FailedJobStore $store */
        $store = $this->app()->container()->make(FailedJobStore::class);
        if (! $store->isRecording()) {
            fwrite(STDERR, Term::style('1;33', 'Failed job storage is disabled') . " (set queue.failed_jobs_table in config).\n");

            return 0;
        }

        $rows = $store->recent($limit);
        if ($rows === []) {
            fwrite(STDERR, Term::style('2', 'No failed jobs.') . "\n");

            return 0;
        }

        fwrite(STDERR, "\n " . Term::style('1;36', 'Failed jobs') . Term::style('2', ' (newest first)') . "\n\n");
        foreach ($rows as $row) {
            $excerpt = $row['exception'];
            $firstLine = (string) (explode("\n", $excerpt, 2)[0] ?? $excerpt);
            if (strlen($firstLine) > 120) {
                $firstLine = substr($firstLine, 0, 117) . '...';
            }
            $when = date('Y-m-d H:i:s', $row['failed_at']);
            fwrite(
                STDERR,
                ' '
                . Term::style('1;33', '#' . (string) $row['id'])
                . '  '
                . Term::style('37', $row['queue'])
                . '  '
                . Term::style('2', $when)
                . "\n"
                . '     '
                . $firstLine
                . "\n\n",
            );
        }

        return 0;
    }

    protected function shouldBootApplication(): bool
    {
        return true;
    }
}
