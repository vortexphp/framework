<?php

declare(strict_types=1);

namespace Vortex\Auth\Middleware;

use Closure;
use Vortex\Auth\Auth;
use Vortex\Auth\AuthConfig;
use Vortex\Contracts\Middleware;
use Vortex\Http\Request;
use Vortex\Http\Response;

/**
 * Rejects guests with 401 JSON or redirect to {@code auth.login_path} (default {@code /login}).
 */
final class Authenticate implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            return $next($request);
        }

        if (Request::wantsJson()) {
            return Response::unauthorized();
        }

        return Response::redirect(AuthConfig::loginPath());
    }
}
