<?php

declare(strict_types=1);

namespace Ordain\Delegation\Contracts;

/**
 * Abstracts rate limiting behavior for delegation operations.
 */
interface RateLimiterInterface
{
    /**
     * Determine if the given key has been "accessed" too many times.
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool;

    /**
     * Increment the counter for a given key for a given decay time.
     */
    public function hit(string $key, int $decaySeconds): int;

    /**
     * Get the number of remaining attempts.
     */
    public function remaining(string $key, int $maxAttempts): int;

    /**
     * Get the number of seconds until the key is accessible again.
     */
    public function availableIn(string $key): int;
}
