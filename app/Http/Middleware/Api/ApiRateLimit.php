<?php

declare(strict_types=1);

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token-keyed rate limit. Default 120/min for general API; sync endpoints
 * use api.throttle:30 to lower the cap.
 */
class ApiRateLimit
{
    public function __construct(private readonly RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next, int $maxPerMinute = 120): Response
    {
        $key = 'api|'.($request->bearerToken() ?: $request->ip());

        if ($this->limiter->tooManyAttempts($key, $maxPerMinute)) {
            $retry = $this->limiter->availableIn($key);

            return new JsonResponse([
                'error' => ['code' => 'rate_limited', 'retry_after_seconds' => $retry],
            ], 429, ['Retry-After' => (string) $retry]);
        }

        $this->limiter->hit($key, 60);

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $maxPerMinute);
        $response->headers->set(
            'X-RateLimit-Remaining',
            (string) max(0, $maxPerMinute - $this->limiter->attempts($key))
        );

        return $response;
    }
}
