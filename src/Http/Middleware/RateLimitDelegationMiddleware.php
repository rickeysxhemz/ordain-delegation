<?php

declare(strict_types=1);

namespace Ordain\Delegation\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limits delegation operations to prevent abuse.
 */
final readonly class RateLimitDelegationMiddleware
{
    public function __construct(
        private RateLimiter $limiter,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     * @param  string  $maxAttempts  Maximum attempts per minute (default: 60)
     * @param  string  $decayMinutes  Decay time in minutes (default: 1)
     */
    public function handle(
        Request $request,
        Closure $next,
        string $maxAttempts = '60',
        string $decayMinutes = '1',
    ): Response {
        $key = $this->resolveRequestKey($request);
        $maxAttemptsInt = (int) $maxAttempts;
        $decayMinutesInt = (int) $decayMinutes;

        if ($this->limiter->tooManyAttempts($key, $maxAttemptsInt)) {
            return $this->buildTooManyAttemptsResponse($key, $maxAttemptsInt);
        }

        $this->limiter->hit($key, $decayMinutesInt * 60);

        $response = $next($request);

        return $this->addRateLimitHeaders(
            $response,
            $key,
            $maxAttemptsInt,
        );
    }

    /**
     * Resolve the rate limit key for the request.
     */
    private function resolveRequestKey(Request $request): string
    {
        $userId = $request->user()?->getAuthIdentifier() ?? 'guest';
        $ip = $request->ip() ?? 'unknown';

        return "delegation_rate_limit:{$userId}:{$ip}";
    }

    /**
     * Build the response when rate limit is exceeded.
     */
    private function buildTooManyAttemptsResponse(string $key, int $maxAttempts): Response
    {
        $retryAfter = $this->limiter->availableIn($key);

        return response()->json([
            'message' => 'Too many delegation attempts. Please try again later.',
            'retry_after' => $retryAfter,
        ], 429)->withHeaders([
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
        ]);
    }

    /**
     * Add rate limit headers to the response.
     */
    private function addRateLimitHeaders(
        Response $response,
        string $key,
        int $maxAttempts,
    ): Response {
        $remaining = $this->limiter->remaining($key, $maxAttempts);

        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remaining),
        ]);

        return $response;
    }
}
