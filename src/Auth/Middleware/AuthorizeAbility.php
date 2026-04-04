<?php

declare(strict_types=1);

namespace Vortex\Auth\Middleware;

use Closure;
use Vortex\Auth\Gate;
use Vortex\Contracts\Middleware;
use Vortex\Http\Request;
use Vortex\Http\Response;

/**
 * Extend and implement {@see ability()} (and optionally {@see arguments()}). Denied users get 403.
 */
abstract class AuthorizeAbility implements Middleware
{
    abstract protected function ability(): string;

    /**
     * @return list<mixed>
     */
    protected function arguments(Request $request): array
    {
        return [];
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (Gate::denies($this->ability(), ...$this->arguments($request))) {
            if (Request::wantsJson()) {
                return Response::forbidden();
            }

            return Response::forbidden('This action is unauthorized.');
        }

        return $next($request);
    }
}
