<?php

declare(strict_types=1);

namespace Vortex\Console\Commands;

use Vortex\Console\Command;
use Vortex\Console\Input;
use Vortex\Console\Term;
use Vortex\Support\Env;

/**
 * HTTP smoke checks for CI or post-deploy (§4 / §8). Uses allow_url_fopen HTTP stream.
 */
final class SmokeCommand implements Command
{
    public function name(): string
    {
        return 'smoke';
    }

    public function description(): string
    {
        return 'GET /health and / on a base URL (arg or APP_URL or http://127.0.0.1:8080). Needs allow_url_fopen.';
    }

    public function run(Input $input): int
    {
        $tokens = $input->tokens();
        $base = isset($tokens[0]) ? (string) $tokens[0] : (string) (Env::get('APP_URL') ?? 'http://127.0.0.1:8080');
        $base = rtrim($base, '/');

        if (! filter_var($base, FILTER_VALIDATE_URL)) {
            fwrite(STDERR, Term::style('1;31', 'Invalid base URL:') . ' ' . $base . "\n");

            return 1;
        }

        if (! filter_var((string) ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            fwrite(STDERR, Term::style('1;31', 'allow_url_fopen is disabled; enable it or use curl in CI.') . "\n");

            return 1;
        }

        fwrite(STDERR, "\n " . Term::style('1;36', 'Smoke') . Term::style('2', ' — ') . $base . "\n\n");

        $failed = false;
        $failed = ! $this->checkHealth($base) || $failed;
        $failed = ! $this->checkHome($base) || $failed;

        fwrite(STDERR, "\n");

        if ($failed) {
            fwrite(STDERR, Term::style('1;31', 'Smoke checks failed.') . "\n\n");

            return 1;
        }

        fwrite(STDERR, Term::style('1;32', 'Smoke checks passed.') . "\n\n");

        return 0;
    }

    private function checkHealth(string $base): bool
    {
        $url = $base . '/health';
        [$status, $body] = $this->get($url);
        $ok = $status === 200 && str_contains($body, '"ok"');
        $this->line($ok, "GET /health → {$status}" . ($ok ? '' : ' (expected JSON with ok)'));

        return $ok;
    }

    private function checkHome(string $base): bool
    {
        $url = $base . '/';
        [$status, $body] = $this->get($url);
        $ok = $status === 200 && strlen($body) > 50;
        $this->line($ok, "GET / → {$status}" . ($ok ? '' : ' (expected HTML body)'));

        return $ok;
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function get(string $url): array
    {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 10,
                'ignore_errors' => true,
                'header' => "Accept: application/json, text/html, */*;q=0.1\r\n",
            ],
        ]);

        $body = @file_get_contents($url, false, $ctx);
        $status = 0;
        if (isset($http_response_header) && isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }

        return [$status, $body === false ? '' : (string) $body];
    }

    private function line(bool $ok, string $message): void
    {
        $mark = $ok ? Term::style('1;32', '✓') : Term::style('1;31', '✗');
        fwrite(STDERR, " {$mark}  {$message}\n");
    }
}
