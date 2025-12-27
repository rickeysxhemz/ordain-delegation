<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Authorization;

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationAuthorizerInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\Repositories\DelegationRepositoryInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Contracts\RootAdminResolverInterface;

/**
 * Handles all delegation authorization checks.
 */
final readonly class DelegationAuthorizer implements DelegationAuthorizerInterface
{
    public function __construct(
        private DelegationRepositoryInterface $delegationRepository,
        private RootAdminResolverInterface $rootAdminResolver,
    ) {}

    public function canAssignRole(
        DelegatableUserInterface $delegator,
        RoleInterface $role,
        ?DelegatableUserInterface $target = null,
    ): bool {
        if ($this->rootAdminResolver->isRootAdmin($delegator)) {
            return true;
        }

        if (! $delegator->canManageUsers()) {
            return false;
        }

        if ($target !== null && ! $this->canManageUser($delegator, $target)) {
            return false;
        }

        return $this->delegationRepository->hasAssignableRole($delegator, $role);
    }

    public function canAssignPermission(
        DelegatableUserInterface $delegator,
        PermissionInterface $permission,
        ?DelegatableUserInterface $target = null,
    ): bool {
        if ($this->rootAdminResolver->isRootAdmin($delegator)) {
            return true;
        }

        if (! $delegator->canManageUsers()) {
            return false;
        }

        if ($target !== null && ! $this->canManageUser($delegator, $target)) {
            return false;
        }

        return $this->delegationRepository->hasAssignablePermission($delegator, $permission);
    }

    public function canRevokeRole(
        DelegatableUserInterface $delegator,
        RoleInterface $role,
        DelegatableUserInterface $target,
    ): bool {
        return $this->canAssignRole($delegator, $role, $target);
    }

    public function canRevokePermission(
        DelegatableUserInterface $delegator,
        PermissionInterface $permission,
        DelegatableUserInterface $target,
    ): bool {
        return $this->canAssignPermission($delegator, $permission, $target);
    }

    public function canManageUser(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
    ): bool {
        if ($this->rootAdminResolver->isRootAdmin($delegator)) {
            return true;
        }

        if ($delegator->getDelegatableIdentifier() === $target->getDelegatableIdentifier()) {
            return false;
        }

        if (! $delegator->canManageUsers()) {
            return false;
        }

        $creator = $target->getCreator();

        return $creator !== null
            && $creator->getDelegatableIdentifier() === $delegator->getDelegatableIdentifier();
    }
}
