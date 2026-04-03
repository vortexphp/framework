<?php

declare(strict_types=1);

namespace Vortex\Http;

use Vortex\Support\JsonHelp;

final class Request
{
    private static ?self $current = null;

    /**
     * @param array<string, UploadedFile> $files
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        /** @var array<string, string> */
        public readonly array $query,
        /** @var array<string, mixed> */
        public readonly array $body,
        /** @var array<string, string> */
        public readonly array $headers,
        /** @var array<string, mixed> */
        public readonly array $server,
        public readonly array $files = [],
    ) {
    }

    public static function setCurrent(self $request): void
    {
        self::$current = $request;
    }

    public static function forgetCurrent(): void
    {
        self::$current = null;
    }

    public static function current(): self
    {
        if (self::$current === null) {
            throw new \RuntimeException('No active HTTP request; Request::setCurrent() was not called.');
        }

        return self::$current;
    }

    public static function method(): string
    {
        return self::current()->method;
    }

    public static function path(): string
    {
        return self::current()->path;
    }

    /**
     * @return array<string, string>
     */
    public static function query(): array
    {
        return self::current()->query;
    }

    /**
     * @return array<string, mixed>
     */
    public static function body(): array
    {
        return self::current()->body;
    }

    /**
     * @return array<string, string>
     */
    public static function headers(): array
    {
        return self::current()->headers;
    }

    /**
     * @return array<string, mixed>
     */
    public static function server(): array
    {
        return self::current()->server;
    }

    /**
     * @return array<string, UploadedFile>
     */
    public static function files(): array
    {
        return self::current()->files;
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        return self::current()->readInput($key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return self::current()->mergeInput();
    }

    public static function file(string $key): ?UploadedFile
    {
        return self::current()->lookupFile($key);
    }

    public static function header(string $name, ?string $default = null): ?string
    {
        return self::current()->readHeader($name, $default);
    }

    public static function wantsJson(): bool
    {
        return self::current()->detectWantsJson();
    }

    public static function isSecure(): bool
    {
        return self::current()->detectSecure();
    }

    /**
     * Normalize a path or URI path segment the same way as {@see capture()} (leading slash, trim trailing slash except root).
     */
    public static function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        if ($path !== '/' && str_ends_with($path, '/')) {
            return rtrim($path, '/');
        }

        return $path;
    }

    /**
     * Build a synthetic request (e.g. PHPUnit). Does not read superglobals.
     *
     * @param array<string, string> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, mixed> $server
     * @param array<string, UploadedFile> $files
     */
    public static function make(
        string $method,
        string $path,
        array $query = [],
        array $body = [],
        array $headers = [],
        array $server = [],
        array $files = [],
    ): self {
        $method = strtoupper($method);
        $path = self::normalizePath($path === '' ? '/' : $path);

        $server = array_merge([
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $path,
            'HTTP_HOST' => 'localhost',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '80',
        ], $server);

        return new self($method, $path, $query, $body, $headers, $server, $files);
    }

    public static function capture(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $rawPath = parse_url($uri, PHP_URL_PATH);
        $path = self::normalizePath(is_string($rawPath) && $rawPath !== '' ? $rawPath : '/');

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = (string) $value;
            }
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        $parsedBody = $_POST;

        if ($method !== 'GET' && $method !== 'HEAD' && is_string($contentType)
            && str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            $json = JsonHelp::tryDecodeArray($raw);
            if ($json !== null) {
                $parsedBody = $json;
            }
        }

        $files = [];
        foreach ($_FILES as $field => $info) {
            if (! is_array($info) || ! isset($info['name'])) {
                continue;
            }
            if (is_array($info['name'])) {
                continue;
            }
            $files[$field] = new UploadedFile(
                (string) $info['name'],
                (string) ($info['tmp_name'] ?? ''),
                (int) ($info['error'] ?? UPLOAD_ERR_NO_FILE),
                (int) ($info['size'] ?? 0),
            );
        }

        return new self(
            $method,
            $path,
            $_GET,
            $parsedBody,
            $headers,
            $_SERVER,
            $files,
        );
    }

    private function readInput(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Query string merged with body; body wins on duplicate keys.
     *
     * @return array<string, mixed>
     */
    private function mergeInput(): array
    {
        return array_merge($this->query, $this->body);
    }

    private function lookupFile(string $key): ?UploadedFile
    {
        return $this->files[$key] ?? null;
    }

    private function readHeader(string $name, ?string $default = null): ?string
    {
        $key = str_replace('-', ' ', $name);
        $key = str_replace(' ', '-', ucwords(strtolower($key)));

        return $this->headers[$key] ?? $default;
    }

    private function detectWantsJson(): bool
    {
        $accept = $this->readHeader('Accept') ?? '';

        return str_contains($accept, 'application/json');
    }

    private function detectSecure(): bool
    {
        $https = $this->server['HTTPS'] ?? '';

        return $https !== '' && $https !== 'off';
    }
}
