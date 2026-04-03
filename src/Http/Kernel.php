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
        $request = Request::capture();
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
        $response->send();
    }
}
