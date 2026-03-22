<?php

declare(strict_types=1);

namespace Ordain\Delegation\Contracts;

/**
 * Provides cache invalidation for delegation data.
 */
interface CacheInvalidatorInterface
{
    /**
     * Clear all cached delegation data for a user.
     */
    public function forgetUserCache(DelegatableUserInterface $user): void;
}
