<?php

declare(strict_types=1);

namespace Vortex\Console\Commands;

use Throwable;
use Vortex\Config\Repository;
use Vortex\Console\Command;
use Vortex\Console\Input;
use Vortex\Console\Term;
use Vortex\Queue\Contracts\Job;
use Vortex\Queue\Contracts\QueueDriver;
use Vortex\Queue\FailedJobStore;
use Vortex\Queue\Queue;
use Vortex\Support\Log;

/**
 * Polls the configured queue driver (SQL or Redis), runs one job at a time. Pass {@code once} for a single iteration.
 *
 * Tokens: optional queue name (default from config {@code queue.default}), and/or {@code once}.
 */
final class QueueWorkCommand extends Command
{
    public function __construct(
        private readonly string $basePath,
    ) {
        parent::__construct($basePath);
    }

    public function name(): string
    {
        return 'queue:work';
    }

    public function description(): string
    {
        return 'Run queued jobs from the configured driver (database or Redis; pass "once" for one batch).';
    }

    protected function execute(Input $input): int
    {
        $tokens = $input->tokens();
        $once = in_array('once', $tokens, true);
        $queue = Queue::defaultQueue();
        foreach ($tokens as $t) {
            if ($t === 'once') {
                continue;
            }
            $queue = $t;
            break;
        }

        $container = $this->app()->container();
        /** @var QueueDriver $driver */
        $driver = $container->make(QueueDriver::class);
        /** @var FailedJobStore $failedJobs */
        $failedJobs = $container->make(FailedJobStore::class);

        $maxTries = (int) Repository::get('queue.tries', 3);
        $maxTries = max(1, $maxTries);
        $staleReserve = (int) Repository::get('queue.stale_reserve_seconds', 300);
        $staleReserve = max(1, $staleReserve);
        $idleMs = (int) Repository::get('queue.idle_sleep_ms', 1000);
        $idleMs = max(0, $idleMs);

        while (true) {
            $reserved = $driver->reserve($queue, $staleReserve);
            if ($reserved === null) {
                if ($once) {
                    return 0;
                }
                if ($idleMs > 0) {
                    usleep($idleMs * 1000);
                }
                continue;
            }

            try {
                $job = unserialize($reserved->payload, ['allowed_classes' => true]);
                if (! $job instanceof Job) {
                    Log::error('Discarded queue row: payload is not a Job instance.', [
                        'id' => $reserved->id,
                        'type' => is_object($job) ? $job::class : gettype($job),
                    ]);
                    $driver->delete($reserved);
                } else {
                    $job->handle();
                    $driver->delete($reserved);
                    fwrite(STDERR, Term::style('1;32', 'Processed job') . " #{$reserved->id}\n");
                }
            } catch (Throwable $e) {
                Log::exception($e);
                $attempts = $reserved->attempts + 1;
                if ($attempts >= $maxTries) {
                    $driver->delete($reserved);
                    if ($failedJobs->isRecording()) {
                        try {
                            $failedJobs->record($queue, $reserved->payload, $e);
                        } catch (Throwable $storeError) {
                            Log::error('Could not record failed job: ' . $storeError->getMessage());
                        }
                    }
                    fwrite(
                        STDERR,
                        Term::style('1;31', 'Permanent failure') . " job #{$reserved->id} after {$maxTries} attempt(s): {$e->getMessage()}\n",
                    );
                } else {
                    $delay = min(120, 10 * $attempts);
                    $driver->release($reserved, $attempts, $delay);
                    fwrite(
                        STDERR,
                        Term::style('1;33', 'Released job') . " #{$reserved->id} (attempt {$attempts}/{$maxTries}, retry in {$delay}s)\n",
                    );
                }
            }

            if ($once) {
                return 0;
            }
        }
    }

    protected function shouldBootApplication(): bool
    {
        return true;
    }
}
