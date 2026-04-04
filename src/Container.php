<?php

declare(strict_types=1);

namespace Vortex;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use RuntimeException;

/**
 * IoC container: {@see make()}, {@see bind()}/{@see singleton()}, {@see tag()}/{@see tagged()}, {@see bindFor()} for
 * contextual resolution, {@see call()} for method injection.
 */
final class Container
{
    /** @var array<string, Closure|object|string> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, list<string|Closure>> */
    private array $tags = [];

    /**
     * When building {@code $contextClass} (constructor or {@see call()}), resolve {@code $abstract} with this
     * concrete class name or factory instead of the global binding.
     *
     * @var array<class-string, array<string, Closure|string>>
     */
    private array $contextual = [];

    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function singleton(string $abstract, Closure|string $concrete): void
    {
        $this->bindings[$abstract] = function (self $c) use ($abstract, $concrete): object {
            if (isset($this->instances[$abstract])) {
                return $this->instances[$abstract];
            }
            $resolved = $concrete instanceof Closure
                ? $concrete($c)
                : $c->newInstanceOf($concrete);
            $this->instances[$abstract] = $resolved;

            return $resolved;
        };
    }

    public function bind(string $abstract, Closure|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Register a service under a string tag. {@see tagged()} resolves each entry (class via {@see make()},
     * {@see Closure} with the container).
     *
     * @param class-string|Closure(Container): object $definition
     */
    public function tag(string $tag, string|Closure $definition): void
    {
        $this->tags[$tag][] = $definition;
    }

    /**
     * @return list<object>
     */
    public function tagged(string $tag): array
    {
        $out = [];
        foreach ($this->tags[$tag] ?? [] as $definition) {
            $out[] = $definition instanceof Closure ? $definition($this) : $this->make($definition);
        }

        return $out;
    }

    /**
     * @param class-string $contextClass Class whose dependencies are being resolved (the consumer).
     * @param class-string $abstract Dependency type-hint (interface or class).
     * @param class-string|Closure(Container): object $concrete
     */
    public function bindFor(string $contextClass, string $abstract, Closure|string $concrete): void
    {
        $this->contextual[$contextClass][$abstract] = $concrete;
    }

    /**
     * True when {@see make()} resolves from an {@see instance()} or {@see bind()}/{@see singleton()} entry.
     */
    public function has(string $abstract): bool
    {
        return isset($this->instances[$abstract]) || isset($this->bindings[$abstract]);
    }

    public function make(string $abstract): object
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $binding = $this->bindings[$abstract];
            if ($binding instanceof Closure) {
                return $binding($this);
            }

            return $this->build($binding);
        }

        return $this->build($abstract);
    }

    /**
     * Invoke a closure, invokable object, function name, {@code Class::method}, or {@code [object|class, method]} with
     * auto-wired parameters (same rules as {@see make()} for class type-hints). Pass named overrides in {@code $parameters}.
     *
     * @param array<string, mixed> $parameters
     */
    public function call(callable $callback, array $parameters = []): mixed
    {
        $ref = $this->callableToReflection($callback);
        $declaringClass = $ref instanceof ReflectionMethod ? $ref->getDeclaringClass()->getName() : null;
        $args = [];

        foreach ($ref->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $parameters)) {
                $value = $parameters[$name];
                if ($param->isVariadic()) {
                    $value = is_array($value) ? $value : [$value];
                    array_push($args, ...$value);
                } else {
                    $args[] = $value;
                }

                continue;
            }

            if ($param->isVariadic()) {
                break;
            }

            $args[] = $this->resolveParameterReflection($param, $declaringClass);
        }

        if ($ref instanceof ReflectionFunction) {
            return $ref->invokeArgs($args);
        }

        /** @var ReflectionMethod $ref */
        if (is_array($callback)) {
            $object = is_object($callback[0]) ? $callback[0] : null;

            return $ref->invokeArgs($object, $args);
        }

        if (is_object($callback) && ! $callback instanceof Closure) {
            return $ref->invokeArgs($callback, $args);
        }

        if (is_string($callback) && str_contains($callback, '::')) {
            return $ref->invokeArgs(null, $args);
        }

        throw new RuntimeException('Unsupported callable for Container::call().');
    }

    /**
     * Instantiate a class without resolving {$class} through container bindings (avoids singleton recursion).
     *
     * @param class-string $class
     */
    public function newInstanceOf(string $class): object
    {
        return $this->build($class);
    }

    /**
     * @param class-string $class
     */
    private function build(string $class): object
    {
        try {
            $ref = new ReflectionClass($class);
        } catch (ReflectionException) {
            throw new RuntimeException("Cannot resolve [{$class}].");
        }

        if ($ref->isInterface() || $ref->isAbstract()) {
            throw new RuntimeException("Cannot instantiate [{$class}].");
        }

        $ctor = $ref->getConstructor();
        if ($ctor === null) {
            return $ref->newInstance();
        }

        $args = [];
        foreach ($ctor->getParameters() as $param) {
            $args[] = $this->resolveParameterReflection($param, $class);
        }

        return $ref->newInstanceArgs($args);
    }

    private function callableToReflection(callable $callback): ReflectionFunctionAbstract
    {
        if ($callback instanceof Closure) {
            return new ReflectionFunction($callback);
        }

        if (is_string($callback)) {
            if (str_contains($callback, '::')) {
                [$class, $method] = explode('::', $callback, 2);

                return new ReflectionMethod($class, $method);
            }

            return new ReflectionFunction($callback);
        }

        if (is_array($callback)) {
            [$subject, $method] = $callback;
            $className = is_object($subject) ? $subject::class : $subject;

            return new ReflectionMethod($className, $method);
        }

        return new ReflectionMethod($callback, '__invoke');
    }

    private function resolveParameterReflection(\ReflectionParameter $param, ?string $declaringClass): mixed
    {
        $type = $param->getType();

        if ($type instanceof ReflectionUnionType) {
            $lastException = null;
            foreach ($type->getTypes() as $part) {
                if (! $part instanceof ReflectionNamedType || $part->getName() === 'null') {
                    continue;
                }
                if ($part->isBuiltin()) {
                    continue;
                }
                $name = $this->normalizeTypeName($part->getName(), $declaringClass);
                if ($this->tryContextual($declaringClass, $name)) {
                    return $this->makeContextual($declaringClass, $name);
                }
                try {
                    return $this->make($name);
                } catch (RuntimeException $e) {
                    $lastException = $e;
                }
            }
            if ($param->allowsNull()) {
                return null;
            }
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }
            throw $lastException ?? new RuntimeException("Unresolvable parameter \${$param->getName()}.");
        }

        if ($type instanceof ReflectionIntersectionType) {
            throw new RuntimeException("Intersection types are not supported for \${$param->getName()}.");
        }

        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
            $name = $this->normalizeTypeName($type->getName(), $declaringClass);
            if ($this->tryContextual($declaringClass, $name)) {
                return $this->makeContextual($declaringClass, $name);
            }
            try {
                return $this->make($name);
            } catch (RuntimeException $e) {
                if ($param->allowsNull()) {
                    return null;
                }
                if ($param->isDefaultValueAvailable()) {
                    return $param->getDefaultValue();
                }
                throw $e;
            }
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        if ($param->allowsNull()) {
            return null;
        }

        throw new RuntimeException("Unresolvable parameter \${$param->getName()}.");
    }

    private function normalizeTypeName(string $name, ?string $declaringClass): string
    {
        if ($declaringClass === null) {
            return $name;
        }

        return match ($name) {
            'self' => $declaringClass,
            'parent' => $this->parentClassName($declaringClass),
            default => $name,
        };
    }

    /**
     * @param class-string $class
     *
     * @return class-string
     */
    private function parentClassName(string $class): string
    {
        try {
            $ref = new ReflectionClass($class);
        } catch (ReflectionException) {
            throw new RuntimeException("Cannot resolve parent for [{$class}].");
        }
        $parent = $ref->getParentClass();
        if ($parent === false) {
            throw new RuntimeException("Class [{$class}] has no parent.");
        }

        return $parent->getName();
    }

    /**
     * @param class-string $needType
     */
    private function tryContextual(?string $declaringClass, string $needType): bool
    {
        return $declaringClass !== null && isset($this->contextual[$declaringClass][$needType]);
    }

    /**
     * @param class-string $declaringClass
     * @param class-string $needType
     */
    private function makeContextual(string $declaringClass, string $needType): mixed
    {
        $concrete = $this->contextual[$declaringClass][$needType];

        return $concrete instanceof Closure ? $concrete($this) : $this->make($concrete);
    }
}
