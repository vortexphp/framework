<?php

declare(strict_types=1);

namespace Vortex\Console;

use Vortex\Console\Commands\DbCheckCommand;
use Vortex\Console\Commands\MakeCommandCommand;
use Vortex\Console\Commands\MakeMigrationCommand;
use Vortex\Console\Commands\MakeModelCommand;
use Vortex\Console\Commands\MigrateCommand;
use Vortex\Console\Commands\DoctorCommand;
use Vortex\Console\Commands\MigrateDownCommand;
use Vortex\Console\Commands\QueueFailedCommand;
use Vortex\Console\Commands\QueueRetryCommand;
use Vortex\Console\Commands\QueueWorkCommand;
use Vortex\Console\Commands\ReplCommand;
use Vortex\Console\Commands\ScheduleRunCommand;
use Vortex\Console\Commands\ServeCommand;
use Vortex\Console\Commands\SmokeCommand;
use Vortex\Routing\RouteDiscovery;

final class ConsoleApplication
{
    /** @var array<string, Command> */
    private array $commands = [];

    public function __construct(
        private readonly string $basePath,
    ) {
    }

    public static function boot(string $basePath): self
    {
        $basePath = rtrim($basePath, '/');
        $app = new self($basePath);
        $app->register(new ServeCommand());
        $app->register(new DoctorCommand());
        $app->register(new SmokeCommand());
        $app->register(new DbCheckCommand());
        $app->register(new MigrateCommand());
        $app->register(new MigrateDownCommand());
        $app->register(new MakeMigrationCommand());
        $app->register(new MakeModelCommand());
        $app->register(new MakeCommandCommand());
        $app->register(new ReplCommand());
        $app->register(new QueueWorkCommand());
        $app->register(new QueueFailedCommand());
        $app->register(new QueueRetryCommand());
        $app->register(new ScheduleRunCommand());

        RouteDiscovery::loadConsoleRoutes($app, $basePath);

        return $app;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function register(Command $command): void
    {
        $command->setBasePath($this->basePath);
        $this->commands[$command->name()] = $command;
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $input = Input::fromArgv($argv);
        $name = $input->command();

        if ($name === null || $name === '' || $name === 'help') {
            $this->renderHelp($input);

            return 0;
        }

        if (! isset($this->commands[$name])) {
            fwrite(STDERR, Term::style('1;31', 'Unknown command:') . ' ' . $name . "\n\n");
            $this->renderHelp($input);

            return 1;
        }

        return $this->commands[$name]->run($input);
    }

    private function renderHelp(Input $input): void
    {
        $script = basename($input->script());
        fwrite(STDERR, "\n " . Term::style('1;36', 'Vortex') . Term::style('2', ' CLI') . "\n\n");
        foreach ($this->commands as $command) {
            fwrite(
                STDERR,
                ' '
                . Term::style('1;33', $command->name())
                . "\n"
                . Term::style('2', '     ')
                . $command->description()
                . "\n\n",
            );
        }
        fwrite(STDERR, Term::style('2', 'Run ') . Term::style('37', "php {$script} help") . Term::style('2', ' for this list.') . "\n\n");
    }
}
