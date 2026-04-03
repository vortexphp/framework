<?php

declare(strict_types=1);

namespace Vortex\Routing;

use Closure;
use Vortex\Container;
use Vortex\Http\ErrorRenderer;
use Vortex\Http\Request;
use Vortex\Http\Response;
use Throwable;

final class Router
{
    /** @var list<array{methods: list<string>, pattern: string, regex: string, paramNames: list<string>, action: mixed, middleware: list<string>}> */
    private array $routes = [];

    public function __construct(
        private readonly Container $container,
    ) {
    }

    /**
     * @param Closure|array{0: class-string, 1: string} $action
     * @param list<string> $middleware
     */
    public function add(array $methods, string $pattern, Closure|array $action, array $middleware = []): self
    {
        $paramNames = [];
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(\.\.\.)?\}/',
            static function (array $m) use (&$paramNames): string {
                $paramNames[] = $m[1];

                return ($m[2] ?? '') === '...' ? '(.+)' : '([^/]+)';
            },
            $pattern,
        ) ?? $pattern;

        $this->routes[] = [
            'methods' => array_map(strtoupper(...), $methods),
            'pattern' => $pattern,
            'regex' => '#^' . $regex . '$#',
            'paramNames' => $paramNames,
            'action' => $action,
            'middleware' => $middleware,
        ];

        return $this;
    }

    public function get(string $pattern, Closure|array $action, array $middleware = []): self
    {
        return $this->add(['GET'], $pattern, $action, $middleware);
    }

    public function post(string $pattern, Closure|array $action, array $middleware = []): self
    {
        return $this->add(['POST'], $pattern, $action, $middleware);
    }

    public function match(string $method, string $path): ?array
    {
        foreach ($this->routes as $route) {
            if (! in_array($method, $route['methods'], true)) {
                continue;
            }
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }
            $params = [];
            foreach ($route['paramNames'] as $i => $name) {
                $params[$name] = $matches[$i + 1] ?? null;
            }

            return ['route' => $route, 'params' => $params];
        }

        return null;
    }

    public function dispatch(Request $request, array $globalMiddleware = []): Response
    {
        Request::setCurrent($request);

        $match = $this->match($request->method, $request->path);
        if ($match === null) {
            return $this->container->make(ErrorRenderer::class)->notFound();
        }

        $route = $match['route'];
        $params = $match['params'];

        $stack = array_merge($globalMiddleware, $route['middleware']);
        $next = function (Request $req) use ($route, $params): Response {
            Request::setCurrent($req);

            return $this->runAction($route['action'], $params);
        };

        while ($stack !== []) {
            $mw = array_pop($stack);
            $next = function (Request $req) use ($mw, $next): Response {
                Request::setCurrent($req);

                $instance = $this->container->make($mw);

                return $instance->handle($req, $next);
            };
        }

        return $next($request);
    }

    /**
     * @param Closure|array{0: class-string, 1: string} $action
     * @param array<string, string|null> $params
     */
    private function runAction(Closure|array $action, array $params): Response
    {
        try {
            if ($action instanceof Closure) {
                $result = $action(...array_values($params));
            } else {
                [$class, $method] = $action;
                $handler = $this->container->make($class);
                $result = $handler->{$method}(...array_values($params));
            }
        } catch (Throwable $e) {
            return $this->container->make(ErrorRenderer::class)->exception($e);
        }

        if ($result instanceof Response) {
            return $result;
        }

        if (is_string($result)) {
            return Response::make($result);
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        return Response::make('');
    }
}
