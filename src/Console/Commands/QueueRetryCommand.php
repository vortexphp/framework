<?php

declare(strict_types=1);

namespace Vortex\Console\Commands;

use Throwable;
use Vortex\Console\Command;
use Vortex\Console\Input;
use Vortex\Console\Term;
use Vortex\Queue\Contracts\Job;
use Vortex\Queue\Contracts\QueueDriver;
use Vortex\Queue\FailedJobStore;

/**
 * Re-queue a failed job by id, or pass {@code all} to replay every stored failure.
 */
final class QueueRetryCommand extends Command
{
    public function name(): string
    {
        return 'queue:retry';
    }

    public function description(): string
    {
        return 'Push failed job payload(s) back onto the queue (id or "all").';
    }

    protected function execute(Input $input): int
    {
        $tokens = $input->arguments();
        if ($tokens === []) {
            fwrite(STDERR, Term::style('1;31', 'Usage:') . " queue:retry <id>|all\n");

            return 1;
        }

        /** @var FailedJobStore $store */
        $store = $this->app()->container()->make(FailedJobStore::class);
        if (! $store->isRecording()) {
            fwrite(STDERR, Term::style('1;31', 'Set queue.failed_jobs_table in config to use retry.') . "\n");

            return 1;
        }

        /** @var QueueDriver $queue */
        $queue = $this->app()->container()->make(QueueDriver::class);

        try {
            if ($tokens[0] === 'all') {
                return $this->retryAll($queue, $store);
            }

            if (! ctype_digit($tokens[0])) {
                fwrite(STDERR, Term::style('1;31', 'Invalid id; use a number or "all".') . "\n");

                return 1;
            }

            return $this->retryOne($queue, $store, (int) $tokens[0]);
        } catch (Throwable $e) {
            fwrite(STDERR, Term::style('1;31', 'Retry failed:') . ' ' . $e->getMessage() . "\n");

            return 1;
        }
    }

    private function retryOne(QueueDriver $queue, FailedJobStore $store, int $id): int
    {
        $row = $store->find($id);
        if ($row === null) {
            fwrite(STDERR, Term::style('1;31', "Failed job #{$id} not found.") . "\n");

            return 1;
        }

        if (! $this->payloadIsJob($row['payload'])) {
            fwrite(STDERR, Term::style('1;31', 'Payload is not a valid Job; refusing to enqueue.') . "\n");

            return 1;
        }

        $queue->pushSerialized($row['queue'], $row['payload']);
        $store->delete($id);
        fwrite(STDERR, Term::style('1;32', 'Re-queued') . " failed job #{$id} → `{$row['queue']}`\n");

        return 0;
    }

    private function retryAll(QueueDriver $queue, FailedJobStore $store): int
    {
        $rows = $store->allForRetry();
        if ($rows === []) {
            fwrite(STDERR, Term::style('2', 'No failed jobs to retry.') . "\n");

            return 0;
        }

        $n = 0;
        foreach ($rows as $row) {
            if (! $this->payloadIsJob($row['payload'])) {
                fwrite(STDERR, Term::style('1;33', 'Skip') . " #{$row['id']}: not a Job payload\n");
                continue;
            }
            $queue->pushSerialized($row['queue'], $row['payload']);
            $store->delete($row['id']);
            ++$n;
        }

        fwrite(STDERR, Term::style('1;32', "Re-queued {$n} job(s).") . "\n");

        return 0;
    }

    private function payloadIsJob(string $payload): bool
    {
        try {
            $obj = unserialize($payload, ['allowed_classes' => true]);
        } catch (Throwable) {
            return false;
        }

        return $obj instanceof Job;
    }

    protected function shouldBootApplication(): bool
    {
        return true;
    }
}
