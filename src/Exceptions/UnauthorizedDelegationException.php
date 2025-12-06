<?php

declare(strict_types=1);

namespace Ordain\Delegation\Exceptions;

use Ordain\Delegation\Contracts\DelegatableUserInterface;

/**
 * Exception thrown when a user attempts an unauthorized delegation.
 */
class UnauthorizedDelegationException extends DelegationException
{
    public function __construct(
        string $message,
        protected ?DelegatableUserInterface $delegator = null,
        protected ?string $attemptedAction = null,
        array $context = []
    ) {
        parent::__construct($message, $context);
    }

    /**
     * Create exception for unauthorized role assignment.
     */
    public static function cannotAssignRole(
        DelegatableUserInterface $delegator,
        string $roleName
    ): self {
        return new self(
            message: "User is not authorized to assign role '{$roleName}'.",
            delegator: $delegator,
            attemptedAction: 'assign_role',
            context: ['role' => $roleName]
        );
    }

    /**
     * Create exception for unauthorized permission grant.
     */
    public static function cannotGrantPermission(
        DelegatableUserInterface $delegator,
        string $permissionName
    ): self {
        return new self(
            message: "User is not authorized to grant permission '{$permissionName}'.",
            delegator: $delegator,
            attemptedAction: 'grant_permission',
            context: ['permission' => $permissionName]
        );
    }

    /**
     * Create exception for unauthorized role revocation.
     */
    public static function cannotRevokeRole(
        DelegatableUserInterface $delegator,
        string $roleName
    ): self {
        return new self(
            message: "User is not authorized to revoke role '{$roleName}'.",
            delegator: $delegator,
            attemptedAction: 'revoke_role',
            context: ['role' => $roleName]
        );
    }

    /**
     * Create exception for unauthorized permission revocation.
     */
    public static function cannotRevokePermission(
        DelegatableUserInterface $delegator,
        string $permissionName
    ): self {
        return new self(
            message: "User is not authorized to revoke permission '{$permissionName}'.",
            delegator: $delegator,
            attemptedAction: 'revoke_permission',
            context: ['permission' => $permissionName]
        );
    }

    /**
     * Create exception for user creation not allowed.
     */
    public static function cannotCreateUsers(DelegatableUserInterface $delegator): self
    {
        return new self(
            message: 'User is not authorized to create new users.',
            delegator: $delegator,
            attemptedAction: 'create_user'
        );
    }

    /**
     * Create exception for user limit reached.
     */
    public static function userLimitReached(
        DelegatableUserInterface $delegator,
        int $limit
    ): self {
        return new self(
            message: "User has reached their limit of {$limit} manageable users.",
            delegator: $delegator,
            attemptedAction: 'create_user',
            context: ['limit' => $limit]
        );
    }

    /**
     * Create exception for managing user not allowed.
     */
    public static function cannotManageUser(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target
    ): self {
        return new self(
            message: 'User is not authorized to manage this user.',
            delegator: $delegator,
            attemptedAction: 'manage_user',
            context: ['target_id' => $target->getDelegatableIdentifier()]
        );
    }

    /**
     * Get the delegator who attempted the action.
     */
    public function getDelegator(): ?DelegatableUserInterface
    {
        return $this->delegator;
    }

    /**
     * Get the attempted action.
     */
    public function getAttemptedAction(): ?string
    {
        return $this->attemptedAction;
    }
}