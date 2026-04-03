<?php

declare(strict_types=1);

namespace Vortex\Events;

use Vortex\Container;
use TypeError;

/**
 * Synchronous event bus: dispatches a single object to registered listeners in order.
 * Register listener classes (resolved via the container) or callables.
 */
final class Dispatcher
{
    /** @var array<string, list<string|callable(object): void>> */
    private array $listeners = [];

    public function __construct(
        private readonly Container $container,
    ) {
    }

    /**
     * @param class-string $eventClass
     * @param class-string|callable(object): void $listener Class with `handle($event)` or `__invoke`, or a closure
     */
    public function listen(string $eventClass, string|callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function dispatch(object $event): void
    {
        $class = $event::class;
        foreach ($this->listeners[$class] ?? [] as $listener) {
            if (is_string($listener)) {
                $this->invokeClassListener($this->container->make($listener), $event);
            } else {
                $listener($event);
            }
        }
    }

    private function invokeClassListener(object $listener, object $event): void
    {
        if (is_callable([$listener, 'handle'])) {
            $listener->handle($event);

            return;
        }
        if (is_callable($listener)) {
            $listener($event);

            return;
        }

        throw new TypeError(
            sprintf('Event listener %s must define handle(%s $event) or __invoke.', $listener::class, $event::class),
        );
    }
}
