<?php

declare(strict_types=1);

namespace Vortex\Http;

use Vortex\Config\Repository;
use Vortex\Container;
use Vortex\Routing\Router;
use Throwable;

final class Kernel
{
    public function __construct(
        private readonly Container $container,
    ) {
    }

    public function send(): void
    {
        TrustProxies::apply();
        $this->handle(Request::capture())->send();
    }

    /**
     * Run the HTTP pipeline (router, global middleware, security headers, CSP) and return the
     * response without sending it. Use with {@see Request::make()} in tests; for browser requests
     * prefer {@see send()} so {@see TrustProxies::apply()} runs before {@see Request::capture()}.
     */
    public function handle(Request $request): Response
    {
        Request::setCurrent($request);

        try {
            $router = $this->container->make(Router::class);
            /** @var list<class-string<\Vortex\Contracts\Middleware>> $globalMiddleware */
            $globalMiddleware = Repository::get('app.middleware', []);
            $response = $router->dispatch($request, is_array($globalMiddleware) ? $globalMiddleware : []);
        } catch (Throwable $e) {
            $response = $this->container->make(ErrorRenderer::class)->exception($e);
        }

        $response->withSecurityHeaders();
        $csp = Repository::get('app.csp_header', '');
        if (is_string($csp) && trim($csp) !== '') {
            $response->header('Content-Security-Policy', trim($csp));
        }

        return $response;
    }
}
