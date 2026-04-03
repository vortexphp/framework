<?php

declare(strict_types=1);

namespace Vortex\Contracts;

use Closure;
use Vortex\Http\Request;
use Vortex\Http\Response;

interface Middleware
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response;
}
