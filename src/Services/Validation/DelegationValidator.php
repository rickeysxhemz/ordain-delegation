<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Validation;

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationAuthorizerInterface;
use Ordain\Delegation\Contracts\DelegationValidatorInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\Repositories\PermissionRepositoryInterface;
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;
use Ordain\Delegation\Contracts\RoleInterface;

/**
 * Validates delegation operations with batch loading.
 */
final readonly class DelegationValidator implements DelegationValidatorInterface
{
    public function __construct(
        private DelegationAuthorizerInterface $authorizer,
        private RoleRepositoryInterface $roleRepository,
        private PermissionRepositoryInterface $permissionRepository,
    ) {}

    public function validate(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        array $roleIds = [],
        array $permissionIds = [],
    ): array {
        $errors = [];

        if (! $this->authorizer->canManageUser($delegator, $target)) {
            $errors['target'] = 'You are not authorized to manage this user.';
        }

        $this->validateRoles($delegator, $roleIds, $errors);
        $this->validatePermissions($delegator, $permissionIds, $errors);

        return $errors;
    }

    /**
     * @param  array<int|string>  $roleIds
     * @param  array<string, string>  $errors
     */
    private function validateRoles(
        DelegatableUserInterface $delegator,
        array $roleIds,
        array &$errors,
    ): void {
        if ($roleIds === []) {
            return;
        }

        $roleMap = $this->roleRepository->findByIds($roleIds)->keyBy(
            fn (RoleInterface $r): int|string => $r->getRoleIdentifier(),
        );

        foreach ($roleIds as $roleId) {
            $role = $roleMap->get($roleId);

            if ($role === null) {
                $errors["role_{$roleId}"] = "Role with ID {$roleId} not found.";

                continue;
            }

            if (! $this->authorizer->canAssignRole($delegator, $role)) {
                $errors["role_{$roleId}"] = "You cannot assign the role '{$role->getRoleName()}'.";
            }
        }
    }

    /**
     * @param  array<int|string>  $permissionIds
     * @param  array<string, string>  $errors
     */
    private function validatePermissions(
        DelegatableUserInterface $delegator,
        array $permissionIds,
        array &$errors,
    ): void {
        if ($permissionIds === []) {
            return;
        }

        $permissionMap = $this->permissionRepository->findByIds($permissionIds)->keyBy(
            fn (PermissionInterface $p): int|string => $p->getPermissionIdentifier(),
        );

        foreach ($permissionIds as $permissionId) {
            $permission = $permissionMap->get($permissionId);

            if ($permission === null) {
                $errors["permission_{$permissionId}"] = "Permission with ID {$permissionId} not found.";

                continue;
            }

            if (! $this->authorizer->canAssignPermission($delegator, $permission)) {
                $errors["permission_{$permissionId}"] = "You cannot grant the permission '{$permission->getPermissionName()}'.";
            }
        }
    }
}
