<?php

declare(strict_types=1);

namespace Vortex\Routing;

use Closure;
use InvalidArgumentException;
use RuntimeException;
use Vortex\Container;
use Vortex\Database\Model;
use Vortex\Http\ErrorRenderer;
use Vortex\Http\Request;
use Vortex\Http\Response;
use Throwable;

final class Router
{
    /**
     * @var list<array{methods: list<string>, pattern: string, regex: string, paramNames: list<string>, action: Closure|array{0: class-string, 1: string}, middleware: list<string>, name: ?string}>
     */
    private array $routes = [];

    /** @var array<string, int> name => index in $routes */
    private array $named = [];

    /**
     * Route parameter resolvers keyed by placeholder name ({@see model}, {@see bind}).
     *
     * @var array<string, Closure|array{kind: 'model', class: class-string<Model>, column: string}>
     */
    private array $parameterBindings = [];

    public function __construct(
        private readonly Container $container,
    ) {
    }

    /**
     * @param Closure|array{0: class-string, 1: string}|class-string $action Invokable: pass the class name (must define {@code __invoke}).
     * @param list<string> $middleware
     */
    public function add(array $methods, string $pattern, Closure|array|string $action, array $middleware = []): self
    {
        if (is_string($action)) {
            $action = [$action, '__invoke'];
        }

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
            'name' => null,
        ];

        return $this;
    }

    /**
     * Attach middleware to the route registered most recently (after {@see add} / {@see get} / {@see post}).
     *
     * @param list<string>|string $middleware Fully-qualified {@see \Vortex\Contracts\Middleware} classes.
     */
    public function middleware(string|array $middleware): self
    {
        if ($this->routes === []) {
            throw new RuntimeException('Cannot attach middleware: no route registered yet.');
        }

        $items = is_array($middleware) ? $middleware : [$middleware];
        $i = count($this->routes) - 1;
        foreach ($items as $mw) {
            if (! is_string($mw) || $mw === '') {
                continue;
            }
            if (! in_array($mw, $this->routes[$i]['middleware'], true)) {
                $this->routes[$i]['middleware'][] = $mw;
            }
        }

        return $this;
    }

    /**
     * Assign a unique name to the route registered most recently. Call immediately after
     * {@see add} / {@see get} / {@see post} (or at the end of a chain).
     */
    public function name(string $name): self
    {
        if ($this->routes === []) {
            throw new RuntimeException('Cannot name a route: none registered yet.');
        }
        if (isset($this->named[$name])) {
            throw new RuntimeException(sprintf('Duplicate route name "%s".', $name));
        }

        $i = count($this->routes) - 1;
        if ($this->routes[$i]['name'] !== null) {
            throw new RuntimeException(
                'The latest route already has a name; register another route before calling name() again.',
            );
        }

        $this->routes[$i]['name'] = $name;
        $this->named[$name] = $i;

        return $this;
    }

    /**
     * Build a path (leading slash, no query string) for a named route.
     *
     * @param array<string, string|int|float> $params placeholder name => value ({@code {slug}} / {@code {path...}})
     */
    public function path(string $name, array $params = []): string
    {
        if (! isset($this->named[$name])) {
            throw new InvalidArgumentException(sprintf('Route name "%s" is not registered.', $name));
        }

        $pattern = $this->routes[$this->named[$name]]['pattern'];

        return self::interpolatePattern($pattern, $params);
    }

    /**
     * @param array<string, string|int|float> $params
     */
    public static function interpolatePattern(string $pattern, array $params): string
    {
        $result = '';
        $offset = 0;
        while (preg_match('/\{([a-zA-Z_][a-zA-Z0-9_]*)(\.\.\.)?\}/', $pattern, $m, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $full = $m[0][0];
            $start = $m[0][1];
            $paramName = $m[1][0];
            $greedy = ($m[2][0] ?? '') === '...';

            $result .= substr($pattern, $offset, $start - $offset);

            if (! array_key_exists($paramName, $params)) {
                throw new InvalidArgumentException(
                    sprintf('Missing value for route parameter "%s" when building URL.', $paramName),
                );
            }

            $value = (string) $params[$paramName];
            $result .= $greedy ? $value : rawurlencode($value);

            $offset = $start + strlen($full);
        }

        $result .= substr($pattern, $offset);

        return $result;
    }

    public function get(string $pattern, Closure|array|string $action, array $middleware = []): self
    {
        return $this->add(['GET'], $pattern, $action, $middleware);
    }

    public function post(string $pattern, Closure|array|string $action, array $middleware = []): self
    {
        return $this->add(['POST'], $pattern, $action, $middleware);
    }

    /**
     * Register REST-style routes for one controller. Order matches Laravel so {@code /create} and {@code /edit} win over {@code /{id}}.
     *
     * Default omits {@code create} and {@code edit} (JSON-style). Pass {@code except: []} for the full seven actions.
     *
     * @param class-string $controller
     * @param array{
     *     only?: list<string>,
     *     except?: list<string>,
     *     parameter?: string,
     *     middleware?: list<string>,
     *     names?: bool|string
     * } $options {@code names: false} skips names; string adds a prefix (e.g. {@code 'api'} → {@code api.posts.index}).
     */
    public function resource(string $uri, string $controller, array $options = []): self
    {
        $prefix = '/' . trim($uri, '/');
        $nameBase = str_replace('/', '.', trim($prefix, '/'));
        $segments = $nameBase !== '' ? explode('.', $nameBase) : [];
        $lastSeg = $segments !== [] ? $segments[array_key_last($segments)] : 'resource';
        $param = $options['parameter'] ?? self::singularRouteSegment($lastSeg);

        $middleware = $options['middleware'] ?? [];
        $namesMode = $options['names'] ?? true;
        $namePrefix = null;
        if ($namesMode !== false) {
            $namePrefix = is_string($namesMode) ? $namesMode . '.' . $nameBase : $nameBase;
        }

        $order = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];
        if (isset($options['only'])) {
            $active = array_values(array_intersect($order, $options['only']));
        } else {
            $except = $options['except'] ?? ['create', 'edit'];
            $active = array_values(array_diff($order, $except));
        }

        foreach ($active as $key) {
            match ($key) {
                'index' => $this->registerNamedResourceRoute(
                    ['GET'],
                    $prefix,
                    [$controller, 'index'],
                    $middleware,
                    $namePrefix,
                    'index',
                ),
                'create' => $this->registerNamedResourceRoute(
                    ['GET'],
                    $prefix . '/create',
                    [$controller, 'create'],
                    $middleware,
                    $namePrefix,
                    'create',
                ),
                'store' => $this->registerNamedResourceRoute(
                    ['POST'],
                    $prefix,
                    [$controller, 'store'],
                    $middleware,
                    $namePrefix,
                    'store',
                ),
                'show' => $this->registerNamedResourceRoute(
                    ['GET'],
                    $prefix . '/{' . $param . '}',
                    [$controller, 'show'],
                    $middleware,
                    $namePrefix,
                    'show',
                ),
                'edit' => $this->registerNamedResourceRoute(
                    ['GET'],
                    $prefix . '/{' . $param . '}/edit',
                    [$controller, 'edit'],
                    $middleware,
                    $namePrefix,
                    'edit',
                ),
                'update' => $this->registerNamedResourceRoute(
                    ['PUT', 'PATCH'],
                    $prefix . '/{' . $param . '}',
                    [$controller, 'update'],
                    $middleware,
                    $namePrefix,
                    'update',
                ),
                'destroy' => $this->registerNamedResourceRoute(
                    ['DELETE'],
                    $prefix . '/{' . $param . '}',
                    [$controller, 'destroy'],
                    $middleware,
                    $namePrefix,
                    'destroy',
                ),
            };
        }

        return $this;
    }

    /**
     * @param list<string> $methods
     * @param list<string> $middleware
     * @param Closure|array{0: class-string, 1: string} $action
     */
    private function registerNamedResourceRoute(
        array $methods,
        string $pattern,
        array|Closure $action,
        array $middleware,
        ?string $namePrefix,
        string $nameSuffix,
    ): void {
        $this->add($methods, $pattern, $action, $middleware);
        if ($namePrefix !== null) {
            $this->name($namePrefix . '.' . $nameSuffix);
        }
    }

    private static function singularRouteSegment(string $segment): string
    {
        $segment = strtolower($segment);
        if (str_ends_with($segment, 'ies') && strlen($segment) > 3) {
            return substr($segment, 0, -3) . 'y';
        }
        if (str_ends_with($segment, 's') && strlen($segment) > 1 && ! str_ends_with($segment, 'ss')) {
            return substr($segment, 0, -1);
        }

        return $segment;
    }

    /**
     * Resolve a route parameter to a model instance (404 when missing).
     *
     * @param class-string<Model> $modelClass
     */
    public function model(string $parameter, string $modelClass, string $column = 'id'): self
    {
        if (! is_subclass_of($modelClass, Model::class)) {
            throw new InvalidArgumentException(
                sprintf('Route model binding expects a %s subclass, got %s.', Model::class, $modelClass),
            );
        }
        $this->parameterBindings[$parameter] = [
            'kind' => 'model',
            'class' => $modelClass,
            'column' => $column,
        ];

        return $this;
    }

    /**
     * Custom resolution; return null to abort with 404.
     *
     * @param Closure(string): mixed $resolver
     */
    public function bind(string $parameter, Closure $resolver): self
    {
        $this->parameterBindings[$parameter] = $resolver;

        return $this;
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
        $resolved = $this->resolveRouteParameters($params);
        if ($resolved === null) {
            return $this->container->make(ErrorRenderer::class)->notFound();
        }
        $params = $resolved;

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

    /**
     * @param array<string, string|null> $params
     * @return array<string, mixed>|null
     */
    private function resolveRouteParameters(array $params): ?array
    {
        foreach ($params as $key => $value) {
            if (! isset($this->parameterBindings[$key])) {
                continue;
            }
            if ($value === null || $value === '') {
                return null;
            }
            $binding = $this->parameterBindings[$key];
            if ($binding instanceof Closure) {
                $resolved = $binding((string) $value);
                if ($resolved === null) {
                    return null;
                }
                $params[$key] = $resolved;

                continue;
            }

            $class = $binding['class'];
            $column = $binding['column'];
            if ($column === 'id') {
                $model = $class::find($value);
            } else {
                $model = $class::query()->where($column, $value)->first();
            }
            if ($model === null) {
                return null;
            }
            $params[$key] = $model;
        }

        return $params;
    }
}
