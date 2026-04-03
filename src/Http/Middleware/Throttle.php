<?php

declare(strict_types=1);

namespace Vortex\Http\Middleware;

use Closure;
use Vortex\Config\Repository;
use Vortex\Contracts\Cache;
use Vortex\Contracts\Middleware;
use Vortex\Http\RateLimiter;
use Vortex\Http\Request;
use Vortex\Http\Response;

/**
 * Per-route rate limit using {@see Cache}. Profiles are read from {@code config/throttle.php}
 * (keys under {@code throttle.*}). Subclass and implement {@see profile()} for each named limiter.
 */
abstract class Throttle implements Middleware
{
    public function __construct(
        private readonly Cache $cache,
    ) {
    }

    abstract protected function profile(): string;

    public function handle(Request $request, Closure $next): Response
    {
        $name = $this->profile();
        $cfg = Repository::get('throttle.' . $name, null);
        if (! is_array($cfg)) {
            /** @var array<string, mixed> $cfg */
            $cfg = Repository::get('throttle.default', ['max_attempts' => 60, 'decay_seconds' => 60]);
        }

        $max = max(1, (int) ($cfg['max_attempts'] ?? 60));
        $decay = max(1, (int) ($cfg['decay_seconds'] ?? 60));

        $limiter = new RateLimiter($this->cache);
        $baseKey = 'http:throttle:' . $name . ':' . ($request->server['REMOTE_ADDR'] ?? '0.0.0.0');

        if ($limiter->tooManyAttempts($baseKey, $max, $decay)) {
            return $this->reject($limiter->availableIn($baseKey, $decay));
        }

        $limiter->hit($baseKey, $decay);

        return $next($request);
    }

    private function reject(int $retryAfterSeconds): Response
    {
        $retry = (string) $retryAfterSeconds;
        if (Request::wantsJson()) {
            return Response::json(['message' => 'Too Many Requests'], 429)->header('Retry-After', $retry);
        }

        return Response::make('Too Many Requests', 429, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Retry-After' => $retry,
        ]);
    }
}
