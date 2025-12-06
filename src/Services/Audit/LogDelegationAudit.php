<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Audit;

use Illuminate\Support\Facades\Log;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationAuditInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Domain\Enums\DelegationAction;

/**
 * Log-based implementation of audit logging.
 *
 * Writes delegation events to Laravel's logging system.
 */
final class LogDelegationAudit implements DelegationAuditInterface
{
    public function __construct(
        private readonly string $channel = 'stack',
    ) {}

    public function logRoleAssigned(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): void {
        $this->log(DelegationAction::ROLE_ASSIGNED, [
            'delegator_id' => $delegator->getDelegatableIdentifier(),
            'target_id' => $target->getDelegatableIdentifier(),
            'role_id' => $role->getRoleIdentifier(),
            'role_name' => $role->getRoleName(),
        ]);
    }

    public function logRoleRevoked(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): void {
        $this->log(DelegationAction::ROLE_REVOKED, [
            'delegator_id' => $delegator->getDelegatableIdentifier(),
            'target_id' => $target->getDelegatableIdentifier(),
            'role_id' => $role->getRoleIdentifier(),
            'role_name' => $role->getRoleName(),
        ]);
    }

    public function logPermissionGranted(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): void {
        $this->log(DelegationAction::PERMISSION_GRANTED, [
            'delegator_id' => $delegator->getDelegatableIdentifier(),
            'target_id' => $target->getDelegatableIdentifier(),
            'permission_id' => $permission->getPermissionIdentifier(),
            'permission_name' => $permission->getPermissionName(),
        ]);
    }

    public function logPermissionRevoked(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): void {
        $this->log(DelegationAction::PERMISSION_REVOKED, [
            'delegator_id' => $delegator->getDelegatableIdentifier(),
            'target_id' => $target->getDelegatableIdentifier(),
            'permission_id' => $permission->getPermissionIdentifier(),
            'permission_name' => $permission->getPermissionName(),
        ]);
    }

    public function logDelegationScopeChanged(
        DelegatableUserInterface $admin,
        DelegatableUserInterface $user,
        array $changes,
    ): void {
        $this->log(DelegationAction::SCOPE_UPDATED, [
            'admin_id' => $admin->getDelegatableIdentifier(),
            'user_id' => $user->getDelegatableIdentifier(),
            'changes' => $changes,
        ]);
    }

    public function logUnauthorizedAttempt(
        DelegatableUserInterface $delegator,
        string $action,
        array $context = [],
    ): void {
        $this->log(DelegationAction::UNAUTHORIZED_ATTEMPT, array_merge([
            'delegator_id' => $delegator->getDelegatableIdentifier(),
            'attempted_action' => $action,
        ], $context));
    }

    public function logUserCreated(
        DelegatableUserInterface $creator,
        DelegatableUserInterface $createdUser,
    ): void {
        $this->log(DelegationAction::USER_CREATED, [
            'creator_id' => $creator->getDelegatableIdentifier(),
            'created_user_id' => $createdUser->getDelegatableIdentifier(),
        ]);
    }

    /**
     * Write log entry.
     *
     * @param  array<string, mixed>  $context
     */
    private function log(DelegationAction $action, array $context): void
    {
        $message = "[Delegation] {$action->label()}";

        Log::channel($this->channel)->{$action->severity()}($message, $context);
    }
}
