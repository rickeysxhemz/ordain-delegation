<?php

declare(strict_types=1);

namespace Ordain\Delegation\Http\Middleware;

use Illuminate\Cache\RateLimiter;
use Ordain\Delegation\Contracts\RateLimiterInterface;

/**
 * Adapts Laravel's RateLimiter to the package's RateLimiterInterface.
 */
final readonly class LaravelRateLimiterAdapter implements RateLimiterInterface
{
    public function __construct(
        private RateLimiter $limiter,
    ) {}

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->limiter->tooManyAttempts($key, $maxAttempts);
    }

    public function hit(string $key, int $decaySeconds): int
    {
        return $this->limiter->hit($key, $decaySeconds);
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        return $this->limiter->remaining($key, $maxAttempts);
    }

    public function availableIn(string $key): int
    {
        return $this->limiter->availableIn($key);
    }
}
