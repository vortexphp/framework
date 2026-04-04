<?php

declare(strict_types=1);

namespace Vortex\Http;

use Vortex\Support\JsonHelp;
use Vortex\Support\JsonShape;
use Vortex\Validation\Validator;

final class Request
{
    private static ?self $current = null;

    /**
     * @param array<string, UploadedFile> $files
     * @param array<string, string> $cookies Parsed {@code Cookie} header (see {@see Cookie::parseRequestHeader()}).
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
        public readonly array $cookies = [],
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

    public static function cookie(string $name, ?string $default = null): ?string
    {
        return self::current()->readCookie($name, $default);
    }

    public static function cookies(): array
    {
        return self::current()->cookies;
    }

    public static function header(string $name, ?string $default = null): ?string
    {
        return self::current()->readHeader($name, $default);
    }

    public static function wantsJson(): bool
    {
        return self::current()->detectWantsJson();
    }

    /**
     * Run {@see Validator::make} on query string merged with body (body wins on key conflicts).
     * Returns {@see Response::validationFailed()} when invalid, or {@code null} when valid.
     *
     * @param array<string, string|\Vortex\Validation\Rule> $rules
     * @param array<string, string> $messages
     * @param array<string, string> $attributes
     */
    public function validationResponse(array $rules, array $messages = [], array $attributes = []): ?Response
    {
        $result = Validator::make($this->mergeInput(), $rules, $messages, $attributes);
        if (! $result->failed()) {
            return null;
        }

        return Response::validationFailed($result);
    }

    /**
     * Like {@see validationResponse()} but uses only the request body (ignores query string).
     *
     * @param array<string, string|\Vortex\Validation\Rule> $rules
     * @param array<string, string> $messages
     * @param array<string, string> $attributes
     */
    public function bodyValidationResponse(array $rules, array $messages = [], array $attributes = []): ?Response
    {
        $result = Validator::make($this->body, $rules, $messages, $attributes);
        if (! $result->failed()) {
            return null;
        }

        return Response::validationFailed($result);
    }

    /**
     * Validate {@see $this->body} with {@see JsonShape}; returns {@see Response::validationFailed()} or {@code null}.
     *
     * @param array<string, string> $shape
     */
    public function bodyShapeResponse(array $shape): ?Response
    {
        $result = JsonShape::validate($this->body, $shape);
        if (! $result->failed()) {
            return null;
        }

        return Response::validationFailed($result);
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
     * Split {@code /v1/...} style prefix (case-insensitive {@code v} + digits) from the rest of the path.
     *
     * @return array{0: ?string, 1: string} Numeric version without {@code v}, then inner path (normalized, {@code /} if empty).
     */
    public static function splitVersionedPath(string $path): array
    {
        $path = self::normalizePath($path);
        if (preg_match('#^/v(\d+)(/.*)?$#i', $path, $m) !== 1) {
            return [null, $path];
        }
        $version = $m[1];
        $tail = $m[2] ?? '';
        if ($tail === '' || $tail === '/') {
            return [$version, '/'];
        }

        return [$version, self::normalizePath($tail)];
    }

    /**
     * Version string from {@code Accept-Version} or {@code X-Api-Version} headers (first non-empty wins). Trimming only; no path parsing.
     */
    public function apiVersionFromHeaders(): ?string
    {
        foreach (['Accept-Version', 'X-Api-Version'] as $header) {
            $v = $this->readHeader($header);
            if ($v !== null && ($v = trim($v)) !== '') {
                return $v;
            }
        }

        return null;
    }

    /**
     * Header-derived version if set, otherwise numeric segment from {@see splitVersionedPath()} on this request path.
     */
    public function resolvedApiVersion(): ?string
    {
        $fromHeader = $this->apiVersionFromHeaders();
        if ($fromHeader !== null) {
            return $fromHeader;
        }

        [$fromPath] = self::splitVersionedPath($this->path);

        return $fromPath;
    }

    /**
     * Compare to an expected label such as {@code "1"} or {@code "v2"} (leading {@code v} ignored on both sides).
     */
    public function matchesApiVersion(string $expected): bool
    {
        $resolved = $this->resolvedApiVersion();
        if ($resolved === null) {
            return false;
        }
        $a = preg_replace('/^v/i', '', $resolved);
        $b = preg_replace('/^v/i', '', trim($expected));

        return $a !== '' && $b !== '' && $a === $b;
    }

    /**
     * Clone this request with a different path (e.g. after stripping {@code /v1} in middleware).
     */
    public function withPath(string $path): self
    {
        $path = self::normalizePath($path === '' ? '/' : $path);
        $server = $this->server;
        $server['REQUEST_URI'] = $path;

        return new self(
            $this->method,
            $path,
            $this->query,
            $this->body,
            $this->headers,
            $server,
            $this->files,
            $this->cookies,
        );
    }

    /**
     * Build a synthetic request (e.g. PHPUnit). Does not read superglobals.
     *
     * @param array<string, string> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, mixed> $server
     * @param array<string, UploadedFile> $files
     * @param array<string, string> $cookies
     */
    public static function make(
        string $method,
        string $path,
        array $query = [],
        array $body = [],
        array $headers = [],
        array $server = [],
        array $files = [],
        array $cookies = [],
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

        return new self($method, $path, $query, $body, $headers, $server, $files, $cookies);
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

        $cookieLine = $headers['Cookie'] ?? '';
        $cookies = is_string($cookieLine) && $cookieLine !== ''
            ? Cookie::parseRequestHeader($cookieLine)
            : [];

        return new self(
            $method,
            $path,
            $_GET,
            $parsedBody,
            $headers,
            $_SERVER,
            $files,
            $cookies,
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

    private function readCookie(string $name, ?string $default = null): ?string
    {
        if (! array_key_exists($name, $this->cookies)) {
            return $default;
        }

        return $this->cookies[$name];
    }

    private function detectWantsJson(): bool
    {
        $accept = strtolower($this->readHeader('Accept') ?? '');
        if ($accept !== '' && str_contains($accept, 'application/json')) {
            return true;
        }

        $requestedWith = strtolower($this->readHeader('X-Requested-With') ?? '');

        return $requestedWith === 'xmlhttprequest';
    }

    private function detectSecure(): bool
    {
        $https = $this->server['HTTPS'] ?? '';

        return $https !== '' && $https !== 'off';
    }
}
