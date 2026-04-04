<?php

declare(strict_types=1);

namespace Vortex\Http;

use Closure;
use Vortex\Support\JsonHelp;

/**
 * Server-Sent Events framing ({@code text/event-stream}). Used from {@see Response::serverSentEvents()}; writes to the
 * output buffer and flushes so intermediaries can stream incrementally.
 */
final class SseEmitter
{
    /**
     * @param null|(Closure(string): void) $sink When set, framed chunks are passed here instead of {@code echo} (tests, non-HTTP reuse).
     */
    public function __construct(
        private ?Closure $sink = null,
    ) {
    }
    /**
     * Ignore-field line ({@code : ...}) — keeps connections alive through some proxies.
     */
    public function comment(string $text): void
    {
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $this->out(': ' . $line . "\n");
        }
        $this->flushHttp();
    }

    /**
     * One SSE event. {@code $data} may contain multiple lines; each is prefixed with {@code data: }.
     */
    public function message(?string $id, ?string $event, string $data): void
    {
        if ($id !== null && $id !== '') {
            $this->out('id: ' . $this->oneLine($id) . "\n");
        }
        if ($event !== null && $event !== '') {
            $this->out('event: ' . $this->oneLine($event) . "\n");
        }
        foreach (preg_split('/\r\n|\n|\r/', $data) ?: [] as $line) {
            $this->out('data: ' . $line . "\n");
        }
        $this->out("\n");
        $this->flushHttp();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function json(?string $id, ?string $event, array $payload): void
    {
        $this->message($id, $event, JsonHelp::encode($payload));
    }

    private function oneLine(string $value): string
    {
        return str_replace(["\r", "\n"], ' ', $value);
    }

    private function out(string $chunk): void
    {
        if ($this->sink !== null) {
            ($this->sink)($chunk);

            return;
        }
        echo $chunk;
    }

    private function flushHttp(): void
    {
        if ($this->sink !== null) {
            return;
        }
        if (function_exists('ob_get_level') && ob_get_level() > 0) {
            @ob_flush();
        }
        flush();
    }
}
