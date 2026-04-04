<?php

declare(strict_types=1);

namespace Vortex\Console\Commands;

use Vortex\Console\Command;
use Vortex\Console\Input;
use Vortex\Console\Term;

final class ServeCommand extends Command
{
    public function name(): string
    {
        return 'serve';
    }

    public function description(): string
    {
        return 'PHP built-in server for /public (default 127.0.0.1:8080; +1 if port busy). '
            . 'No front-controller rewrite: only real files and / index work. '
            . '[--host=HOST] [--port=PORT] or [HOST] [PORT].';
    }

    protected function execute(Input $input): int
    {
        $host = (string) $input->option('host', '127.0.0.1');
        $port = 8080;
        $portOpt = $input->option('port');
        if (is_string($portOpt) && $portOpt !== '') {
            $port = (int) $portOpt;
        }

        $positional = $input->arguments();
        if (isset($positional[0])) {
            $host = $positional[0];
        }
        if (isset($positional[1])) {
            $port = (int) $positional[1];
        }

        if ($port < 1 || $port > 65535) {
            fwrite(STDERR, Term::style('1;31', 'Error:') . ' Invalid port.' . "\n");

            return 1;
        }

        $public = $this->basePath() . '/public';
        if (! is_dir($public)) {
            fwrite(STDERR, Term::style('1;31', 'Error:') . ' Public directory not found:' . "\n  " . $public . "\n");

            return 1;
        }

        $requestedPort = $port;
        while (! self::tcpPortFree($host, $port)) {
            if ($port >= 65535) {
                fwrite(STDERR, Term::style('1;31', 'Error:') . ' No free TCP port up to 65535.' . "\n");

                return 1;
            }
            $port++;
        }

        if ($port !== $requestedPort) {
            fwrite(
                STDERR,
                Term::style('2', "Port {$requestedPort} in use, using {$port}.") . "\n",
            );
        }

        $addr = "{$host}:{$port}";
        $url = 'http://' . $addr;
        fwrite(
            STDERR,
            "\n "
            . Term::style('1;36', 'Vortex')
            . Term::style('2', '  ·  ')
            . Term::style('1;32', $url)
            . "\n "
            . Term::style('2', 'root')
            . '  '
            . Term::style('37', $public)
            . "\n\n",
        );

        $php = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';
        passthru(
            escapeshellarg($php)
            . ' -S ' . escapeshellarg($addr)
            . ' -t ' . escapeshellarg($public),
            $code,
        );

        return (int) ($code ?? 0);
    }

    private static function tcpPortFree(string $host, int $port): bool
    {
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_server(
            'tcp://' . $host . ':' . $port,
            $errno,
            $errstr,
        );

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }
}
