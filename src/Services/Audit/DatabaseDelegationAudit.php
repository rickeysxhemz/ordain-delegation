<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Audit;

use Illuminate\Support\Facades\DB;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationAuditInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Domain\Enums\DelegationAction;

/**
 * Database-based implementation of audit logging.
 *
 * Stores delegation events in a database table for querying and reporting.
 */
final readonly class DatabaseDelegationAudit implements DelegationAuditInterface
{
    public function logRoleAssigned(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): void {
        $this->log(DelegationAction::ROLE_ASSIGNED, $delegator, $target, [
            'role_id' => $role->getRoleIdentifier(),
            'role_name' => $role->getRoleName(),
        ]);
    }

    public function logRoleRevoked(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): void {
        $this->log(DelegationAction::ROLE_REVOKED, $delegator, $target, [
            'role_id' => $role->getRoleIdentifier(),
            'role_name' => $role->getRoleName(),
        ]);
    }

    public function logPermissionGranted(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): void {
        $this->log(DelegationAction::PERMISSION_GRANTED, $delegator, $target, [
            'permission_id' => $permission->getPermissionIdentifier(),
            'permission_name' => $permission->getPermissionName(),
        ]);
    }

    public function logPermissionRevoked(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): void {
        $this->log(DelegationAction::PERMISSION_REVOKED, $delegator, $target, [
            'permission_id' => $permission->getPermissionIdentifier(),
            'permission_name' => $permission->getPermissionName(),
        ]);
    }

    public function logDelegationScopeChanged(
        DelegatableUserInterface $admin,
        DelegatableUserInterface $user,
        array $changes,
    ): void {
        $this->log(DelegationAction::SCOPE_UPDATED, $admin, $user, $changes);
    }

    public function logUnauthorizedAttempt(
        DelegatableUserInterface $delegator,
        string $action,
        array $context = [],
    ): void {
        $this->log(DelegationAction::UNAUTHORIZED_ATTEMPT, $delegator, null, array_merge([
            'attempted_action' => $action,
        ], $context));
    }

    public function logUserCreated(
        DelegatableUserInterface $creator,
        DelegatableUserInterface $createdUser,
    ): void {
        $this->log(DelegationAction::USER_CREATED, $creator, $createdUser, []);
    }

    /**
     * @param  array<string, int|string|array<string, int|string|bool|array<int|string>|null>>  $metadata
     */
    private function log(
        DelegationAction $action,
        DelegatableUserInterface $performedBy,
        ?DelegatableUserInterface $targetUser,
        array $metadata,
    ): void {
        $table = config('permission-delegation.tables.delegation_audit_logs', 'delegation_audit_logs');
        $request = request();

        DB::table($table)->insert([
            'action' => $action->value,
            'performed_by_id' => $performedBy->getDelegatableIdentifier(),
            'target_user_id' => $targetUser?->getDelegatableIdentifier(),
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip() ?? 'cli',
            'user_agent' => $request->userAgent() ?? 'cli',
            'created_at' => now(),
        ]);
    }
}
