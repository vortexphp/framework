<?php

declare(strict_types=1);

namespace Vortex;

use Closure;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

final class Container
{
    /** @var array<string, Closure|object|string> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $instances = [];

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

        $ctor = $ref->getConstructor();
        if ($ctor === null) {
            return $ref->newInstance();
        }

        $args = [];
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            if ($type === null || !$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                if ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                    continue;
                }
                throw new RuntimeException("Unresolvable parameter \${$param->getName()} for [{$class}].");
            }
            $args[] = $this->make($type->getName());
        }

        return $ref->newInstanceArgs($args);
    }
}
