<?php

declare(strict_types=1);

namespace Vortex\Http;

use LogicException;
use Vortex\Support\JsonHelp;
use Vortex\Validation\ValidationResult;

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

    /**
     * JSON API success envelope: {@code { "ok": true, "data": ... }}.
     */
    public static function apiOk(mixed $data, int $status = 200): self
    {
        return self::json(['ok' => true, 'data' => $data], $status);
    }

    /**
     * JSON API error envelope (always JSON). Use from API routes that always return JSON.
     *
     * @param array<string, mixed> $extra
     */
    public static function apiError(int $status, string $error, string $message, array $extra = []): self
    {
        return self::json(array_merge([
            'ok' => false,
            'error' => $error,
            'message' => $message,
        ], $extra), $status);
    }

    /**
     * JSON API response for a failed {@see ValidationResult} ({@code error: validation_failed}, {@code errors: field => message}).
     *
     * @throws LogicException When the result has no errors (caller should guard with {@see ValidationResult::failed()}).
     */
    public static function validationFailed(
        ValidationResult $result,
        string $message = 'Validation failed',
        int $status = 422,
    ): self {
        if (! $result->failed()) {
            throw new LogicException('validationFailed() requires a failed ValidationResult.');
        }

        return self::apiError($status, 'validation_failed', $message, [
            'errors' => $result->errors(),
        ]);
    }

    /**
     * Return an error response in HTML or JSON based on current request expectations.
     *
     * @param array<string, mixed> $jsonPayload
     */
    public static function error(int $status, string $message, array $jsonPayload = []): self
    {
        if (self::expectsJson()) {
            $error = $jsonPayload['error'] ?? 'http_error';
            $rest = $jsonPayload;
            unset($rest['error']);

            return self::json(array_merge([
                'ok' => false,
                'error' => $error,
                'message' => $message,
            ], $rest), $status);
        }

        return self::html($message, $status);
    }

    /**
     * @param array<string, mixed> $jsonPayload
     */
    public static function notFound(string $message = 'Not Found', array $jsonPayload = []): self
    {
        return self::error(404, $message, array_merge(['error' => 'not_found'], $jsonPayload));
    }

    /**
     * @param array<string, mixed> $jsonPayload
     */
    public static function forbidden(string $message = 'Forbidden', array $jsonPayload = []): self
    {
        return self::error(403, $message, array_merge(['error' => 'forbidden'], $jsonPayload));
    }

    /**
     * @param array<string, mixed> $jsonPayload
     */
    public static function unauthorized(string $message = 'Unauthorized', array $jsonPayload = []): self
    {
        return self::error(401, $message, array_merge(['error' => 'unauthorized'], $jsonPayload));
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return new self('', $status, ['Location' => $to]);
    }

    public function with(string $key, mixed $value): self
    {
        Session::flash($key, $value);

        return $this;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function withMany(array $values): self
    {
        foreach ($values as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            Session::flash($key, $value);
        }

        return $this;
    }

    /**
     * @param array<string, mixed> $errors
     */
    public function withErrors(array $errors, string $key = 'errors'): self
    {
        return $this->with($key, $errors);
    }

    /**
     * @param array<string, mixed>|null $input
     */
    public function withInput(?array $input = null): self
    {
        return $this->with('old', $input ?? Request::all());
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

    private static function expectsJson(): bool
    {
        try {
            return Request::wantsJson();
        } catch (\RuntimeException) {
            return false;
        }
    }
}
