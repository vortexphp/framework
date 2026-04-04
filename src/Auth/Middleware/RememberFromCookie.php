<?php

declare(strict_types=1);

namespace Vortex\Auth\Middleware;

use Closure;
use Vortex\Auth\Auth;
use Vortex\Auth\AuthConfig;
use Vortex\Auth\RememberCookie;
use Vortex\Contracts\Middleware;
use Vortex\Http\Request;
use Vortex\Http\Response;

/**
 * Restores a session user from the signed remember cookie when the session is anonymous.
 * Place after session is available (typically near the start of {@code app.middleware}).
 */
final class RememberFromCookie implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guest()) {
            $name = AuthConfig::rememberCookieName();
            $raw = Request::cookie($name);
            if ($raw !== null && $raw !== '') {
                $id = RememberCookie::validate($raw);
                if ($id !== null) {
                    Auth::loginUsingId($id, false);
                    RememberCookie::queue($id);
                }
            }
        }

        return $next($request);
    }
}
