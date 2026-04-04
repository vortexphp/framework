<?php

declare(strict_types=1);

namespace Vortex\Testing;

use JsonException;
use Vortex\Application;
use Vortex\Container;
use Vortex\Http\ErrorRenderer;
use Vortex\Http\Kernel;
use Vortex\Http\Request;
use Vortex\Http\Response;
use Vortex\Http\UploadedFile;
use Vortex\Support\JsonHelp;

/**
 * Boots the app once, then dispatches synthetic {@see Request} instances through {@see Kernel}
 * (same pipeline as production, without {@see Request::capture()} or {@see TrustProxies::apply()} on the edge).
 *
 * Use {@see resetRequestContext()} in test {@code tearDown()} because {@see Kernel::handle()} sets {@see Request::setCurrent()}.
 */
final class KernelBrowser
{
    public function __construct(
        private readonly Application $application,
        private readonly Kernel $kernel,
    ) {
    }

    /**
     * @param null|callable(Container, string): void $configure Passed to {@see Application::boot()}.
     */
    public static function boot(string $basePath, ?callable $configure = null): self
    {
        $app = Application::boot($basePath, $configure);
        $container = $app->container();
        if (! $container->has(ErrorRenderer::class)) {
            $container->singleton(ErrorRenderer::class, static fn (): ErrorRenderer => new ErrorRenderer());
        }

        return new self($app, new Kernel($container));
    }

    public function application(): Application
    {
        return $this->application;
    }

    public function container(): Container
    {
        return $this->application->container();
    }

    public function kernel(): Kernel
    {
        return $this->kernel;
    }

    public function handle(Request $request): Response
    {
        return $this->kernel->handle($request);
    }

    public static function resetRequestContext(): void
    {
        Request::forgetCurrent();
    }

    public function get(string $path, array $query = [], array $headers = []): Response
    {
        return $this->handle(Request::make('GET', $path, $query, [], $headers));
    }

    public function post(string $path, array $body = [], array $headers = []): Response
    {
        return $this->handle(Request::make('POST', $path, [], $body, $headers));
    }

    /**
     * POST with JSON-oriented headers; {@see Request::make()} still receives {@code $json} as the parsed body map.
     */
    public function postJson(string $path, array $json, array $headers = []): Response
    {
        $headers = array_merge([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], $headers);

        return $this->post($path, $json, $headers);
    }

    /**
     * @param array{
     *     query?: array<string, string>,
     *     body?: array<string, mixed>,
     *     headers?: array<string, string>,
     *     server?: array<string, mixed>,
     *     files?: array<string, UploadedFile>,
     *     cookies?: array<string, string>,
     * } $options
     */
    public function request(string $method, string $path, array $options = []): Response
    {
        return $this->handle(Request::make(
            $method,
            $path,
            $options['query'] ?? [],
            $options['body'] ?? [],
            $options['headers'] ?? [],
            $options['server'] ?? [],
            $options['files'] ?? [],
            $options['cookies'] ?? [],
        ));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException When the response body is not a JSON object.
     */
    public static function decodeJson(Response $response): array
    {
        return JsonHelp::decodeArray($response->body());
    }
}
