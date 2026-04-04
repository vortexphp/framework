<?php

declare(strict_types=1);

namespace Vortex\Console\Commands;

use Throwable;
use Vortex\Application;
use Vortex\Config\Repository;
use Vortex\Console\Command;
use Vortex\Console\Input;
use Vortex\Console\Term;
use Vortex\Container;

/**
 * Interactive eval loop for debugging (not production). Variables {@code $app}, {@code $c}, optional {@code $container} alias.
 */
class ReplCommand extends Command
{
    public function name(): string
    {
        return 'repl';
    }

    public function description(): string
    {
        return 'PHP read-eval loop: $app (Application), $c (Container). Requires app.debug=true or --force. exit / quit to stop. Lines try as expressions first; use trailing ; for statements.';
    }

    protected function shouldBootApplication(): bool
    {
        return true;
    }

    protected function execute(Input $input): int
    {
        if ($this->guard($input) !== 0) {
            return 1;
        }

        $app = $this->app();
        $c = $app->container();
        $container = $c;

        $this->line();
        $this->line(' ' . Term::style('1;36', 'Vortex REPL') . Term::style('2', ' — ') . ' $app, $c, $container');
        $this->line(' ' . Term::style('2', 'Type exit or quit to leave.'));
        $this->line();

        while (true) {
            $line = $this->readLine();
            if ($line === false) {
                break;
            }
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if ($line === 'exit' || $line === 'quit') {
                break;
            }

            $this->evalLine($line, $app, $c, $container);
        }

        $this->line();

        return 0;
    }

    protected function readLine(): string|false
    {
        if (function_exists('readline')) {
            $line = readline('vortex> ');
            if (is_string($line) && $line !== '') {
                readline_add_history($line);
            }

            return $line;
        }

        fwrite(STDERR, 'vortex> ');

        return fgets(STDIN);
    }

    private function guard(Input $input): int
    {
        $force = in_array('--force', $input->tokens(), true);
        $debug = (bool) Repository::get('app.debug', false);
        if (! $debug && ! $force) {
            $this->error('REPL disabled: enable app.debug in config/app.php or pass --force (still unsafe on shared servers).');

            return 1;
        }

        return 0;
    }

    private function evalLine(string $line, Application $app, Container $c, Container $container): void
    {
        try {
            $ret = eval('return ' . $line . ';');
            $this->printResult($ret);
        } catch (Throwable $first) {
            if (! $first instanceof \ParseError) {
                $this->error($first->getMessage());
                $this->line(Term::style('2', '  in eval (expression mode)'));

                return;
            }
            try {
                $code = $line;
                if ($code !== '' && ! str_ends_with(rtrim($code), ';')) {
                    $code .= ';';
                }
                eval($code);
            } catch (Throwable $second) {
                $this->error($second->getMessage());
            }
        }
    }

    private function printResult(mixed $value): void
    {
        if ($value === null) {
            $this->line(Term::style('2', ' → null'));

            return;
        }
        $this->line(print_r($value, true));
    }
}
