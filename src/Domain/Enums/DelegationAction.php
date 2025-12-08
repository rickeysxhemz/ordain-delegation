<?php

declare(strict_types=1);

namespace Ordain\Delegation\Domain\Enums;

/**
 * Enum representing delegation actions for audit logging.
 */
enum DelegationAction: string
{
    case ROLE_ASSIGNED = 'role_assigned';
    case ROLE_REVOKED = 'role_revoked';
    case PERMISSION_GRANTED = 'permission_granted';
    case PERMISSION_REVOKED = 'permission_revoked';
    case SCOPE_UPDATED = 'scope_updated';
    case USER_CREATED = 'user_created';
    case UNAUTHORIZED_ATTEMPT = 'unauthorized_attempt';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::ROLE_ASSIGNED => 'Role Assigned',
            self::ROLE_REVOKED => 'Role Revoked',
            self::PERMISSION_GRANTED => 'Permission Granted',
            self::PERMISSION_REVOKED => 'Permission Revoked',
            self::SCOPE_UPDATED => 'Delegation Scope Updated',
            self::USER_CREATED => 'User Created',
            self::UNAUTHORIZED_ATTEMPT => 'Unauthorized Attempt',
        };
    }

    /**
     * Get severity level for logging.
     */
    public function severity(): string
    {
        return match ($this) {
            self::UNAUTHORIZED_ATTEMPT => 'warning',
            self::ROLE_REVOKED, self::PERMISSION_REVOKED => 'notice',
            default => 'info',
        };
    }

    /**
     * Check if this is a grant action.
     */
    public function isGrant(): bool
    {
        return match ($this) {
            self::ROLE_ASSIGNED, self::PERMISSION_GRANTED => true,
            default => false,
        };
    }

    /**
     * Check if this is a revoke action.
     */
    public function isRevoke(): bool
    {
        return match ($this) {
            self::ROLE_REVOKED, self::PERMISSION_REVOKED => true,
            default => false,
        };
    }
}
