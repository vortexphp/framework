<?php

declare(strict_types=1);

namespace Vortex\Console\Commands;

use Vortex\Console\Command;
use Vortex\Console\Input;
use Vortex\Console\Term;
use Vortex\Schedule\Schedule;

/**
 * Intended for system cron once per minute: runs handlers whose cron expression matches the current minute.
 */
final class ScheduleRunCommand extends Command
{
    public function __construct(
        private readonly string $basePath,
    ) {
        parent::__construct($basePath);
    }

    public function name(): string
    {
        return 'schedule:run';
    }

    public function description(): string
    {
        return 'Run scheduled tasks due at the current time (call from cron, e.g. each minute).';
    }

    protected function execute(Input $input): int
    {
        $ran = Schedule::runDue();
        fwrite(STDERR, Term::style('2', 'Ran ') . Term::style('1;32', (string) $ran) . Term::style('2', ' scheduled task(s).') . "\n");

        return 0;
    }

    protected function shouldBootApplication(): bool
    {
        return true;
    }
}
