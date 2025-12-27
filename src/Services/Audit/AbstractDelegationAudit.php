<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Audit;

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationAuditInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Domain\Enums\DelegationAction;

/**
 * Abstract base class for audit implementations.
 *
 * Provides common functionality for building audit metadata and reduces
 * code duplication between concrete audit implementations.
 */
abstract readonly class AbstractDelegationAudit implements DelegationAuditInterface
{
    public function logRoleAssigned(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): void {
        $this->log(
            DelegationAction::ROLE_ASSIGNED,
            $delegator,
            $target,
            $this->buildRoleMetadata($role),
        );
    }

    public function logRoleRevoked(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): void {
        $this->log(
            DelegationAction::ROLE_REVOKED,
            $delegator,
            $target,
            $this->buildRoleMetadata($role),
        );
    }

    public function logPermissionGranted(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): void {
        $this->log(
            DelegationAction::PERMISSION_GRANTED,
            $delegator,
            $target,
            $this->buildPermissionMetadata($permission),
        );
    }

    public function logPermissionRevoked(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): void {
        $this->log(
            DelegationAction::PERMISSION_REVOKED,
            $delegator,
            $target,
            $this->buildPermissionMetadata($permission),
        );
    }

    public function logDelegationScopeChanged(
        DelegatableUserInterface $admin,
        DelegatableUserInterface $user,
        array $changes,
    ): void {
        $this->log(
            DelegationAction::SCOPE_UPDATED,
            $admin,
            $user,
            $changes,
        );
    }

    public function logUnauthorizedAttempt(
        DelegatableUserInterface $delegator,
        string $action,
        array $context = [],
    ): void {
        $this->log(
            DelegationAction::UNAUTHORIZED_ATTEMPT,
            $delegator,
            null,
            array_merge(['attempted_action' => $action], $context),
        );
    }

    public function logUserCreated(
        DelegatableUserInterface $creator,
        DelegatableUserInterface $createdUser,
    ): void {
        $this->log(
            DelegationAction::USER_CREATED,
            $creator,
            $createdUser,
            [],
        );
    }

    /**
     * Build metadata array for role-related actions.
     *
     * @return array{role_id: int|string, role_name: string}
     */
    protected function buildRoleMetadata(RoleInterface $role): array
    {
        return [
            'role_id' => $role->getRoleIdentifier(),
            'role_name' => $role->getRoleName(),
        ];
    }

    /**
     * Build metadata array for permission-related actions.
     *
     * @return array{permission_id: int|string, permission_name: string}
     */
    protected function buildPermissionMetadata(PermissionInterface $permission): array
    {
        return [
            'permission_id' => $permission->getPermissionIdentifier(),
            'permission_name' => $permission->getPermissionName(),
        ];
    }

    /**
     * Log an audit event.
     *
     * Implement this method in concrete classes to handle the actual logging.
     *
     * @param  array<string, mixed>  $metadata
     */
    abstract protected function log(
        DelegationAction $action,
        DelegatableUserInterface $performedBy,
        ?DelegatableUserInterface $targetUser,
        array $metadata,
    ): void;
}
