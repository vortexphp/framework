<?php

declare(strict_types=1);

namespace Vortex\Http;

use Vortex\Support\JsonHelp;

final class Response
{
    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(
        private string $content = '',
        private int $status = 200,
        private array $headers = [],
    ) {
    }

    public static function make(string $content = '', int $status = 200, array $headers = []): self
    {
        return new self($content, $status, $headers);
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    public static function html(string $content = '', int $status = 200, array $headers = []): self
    {
        return new self($content, $status, $headers + [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $body = JsonHelp::encode($data);

        return new self($body, $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return new self('', $status, ['Location' => $to]);
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Append a {@code Set-Cookie} header (multiple cookies are allowed per response).
     */
    public function cookie(Cookie $cookie): self
    {
        $line = $cookie->toHeaderValue();
        $existing = $this->headers['Set-Cookie'] ?? null;
        if ($existing === null) {
            $this->headers['Set-Cookie'] = $line;
        } elseif (is_array($existing)) {
            $this->headers['Set-Cookie'] = [...$existing, $line];
        } else {
            $this->headers['Set-Cookie'] = [$existing, $line];
        }

        return $this;
    }

    public function status(int $code): self
    {
        $this->status = $code;

        return $this;
    }

    public function httpStatus(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->content;
    }

    /**
     * @return array<string, string|string[]>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Apply common security headers when not already set.
     */
    public function withSecurityHeaders(): self
    {
        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Frame-Options' => 'SAMEORIGIN',
        ];
        foreach ($defaults as $name => $value) {
            if (! array_key_exists($name, $this->headers)) {
                $this->headers[$name] = $value;
            }
        }

        return $this;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    header("{$name}: {$v}", false);
                }
            } else {
                header("{$name}: {$value}");
            }
        }
        echo $this->content;
    }
}
