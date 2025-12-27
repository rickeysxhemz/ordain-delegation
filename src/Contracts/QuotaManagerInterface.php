<?php

declare(strict_types=1);

namespace Ordain\Delegation\Contracts;

/**
 * Manages user creation quotas.
 */
interface QuotaManagerInterface
{
    /**
     * Check if user can create new users.
     */
    public function canCreateUsers(DelegatableUserInterface $delegator): bool;

    /**
     * Check if user has reached their quota limit.
     */
    public function hasReachedLimit(DelegatableUserInterface $delegator): bool;

    /**
     * Get count of users created by delegator.
     */
    public function getCreatedUsersCount(DelegatableUserInterface $delegator): int;

    /**
     * Get remaining quota (null = unlimited).
     */
    public function getRemainingQuota(DelegatableUserInterface $delegator): ?int;

    /**
     * Execute callback with quota lock for atomic user creation.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withLock(DelegatableUserInterface $delegator, callable $callback): mixed;
}
