<?php

declare(strict_types=1);

use Vortex\AppContext;
use Vortex\Routing\Router;

/**
 * Path for a named HTTP route (leading slash). Requires {@see \Vortex\Application::boot()} to have run.
 *
 * @param array<string, string|int|float> $params
 */
function route(string $name, array $params = []): string
{
    return AppContext::container()->make(Router::class)->path($name, $params);
}
