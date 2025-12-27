<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Quota;

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\QuotaManagerInterface;
use Ordain\Delegation\Contracts\Repositories\DelegationRepositoryInterface;
use Ordain\Delegation\Contracts\RootAdminResolverInterface;
use Ordain\Delegation\Contracts\TransactionManagerInterface;
use Ordain\Delegation\Exceptions\UnauthorizedDelegationException;

/**
 * Manages user creation quotas with atomic operations.
 */
final readonly class QuotaManager implements QuotaManagerInterface
{
    public function __construct(
        private DelegationRepositoryInterface $delegationRepository,
        private RootAdminResolverInterface $rootAdminResolver,
        private TransactionManagerInterface $transactionManager,
    ) {}

    public function canCreateUsers(DelegatableUserInterface $delegator): bool
    {
        if ($this->rootAdminResolver->isRootAdmin($delegator)) {
            return true;
        }

        if (! $delegator->canManageUsers()) {
            return false;
        }

        return ! $this->hasReachedLimit($delegator);
    }

    public function hasReachedLimit(DelegatableUserInterface $delegator): bool
    {
        if ($this->rootAdminResolver->isRootAdmin($delegator)) {
            return false;
        }

        $maxUsers = $delegator->getMaxManageableUsers();

        if ($maxUsers === null) {
            return false;
        }

        return $this->getCreatedUsersCount($delegator) >= $maxUsers;
    }

    public function getCreatedUsersCount(DelegatableUserInterface $delegator): int
    {
        return $this->delegationRepository->getCreatedUsersCount($delegator);
    }

    public function getRemainingQuota(DelegatableUserInterface $delegator): ?int
    {
        if ($this->rootAdminResolver->isRootAdmin($delegator)) {
            return null;
        }

        $maxUsers = $delegator->getMaxManageableUsers();

        if ($maxUsers === null) {
            return null;
        }

        $created = $this->getCreatedUsersCount($delegator);

        return max(0, $maxUsers - $created);
    }

    public function withLock(DelegatableUserInterface $delegator, callable $callback): mixed
    {
        return $this->transactionManager->transaction(function () use ($delegator, $callback): mixed {
            $this->transactionManager->lockUserForUpdate($delegator);

            if (! $this->canCreateUsers($delegator)) {
                $maxUsers = $delegator->getMaxManageableUsers();

                throw $maxUsers !== null
                    ? UnauthorizedDelegationException::userLimitReached($delegator, $maxUsers)
                    : UnauthorizedDelegationException::cannotCreateUsers($delegator);
            }

            return $callback();
        });
    }
}
