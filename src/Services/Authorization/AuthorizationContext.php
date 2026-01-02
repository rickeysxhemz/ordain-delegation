<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Authorization;

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\RoleInterface;

/**
 * Context object passed through the authorization pipeline.
 */
final class AuthorizationContext
{
    private ?bool $result = null;

    private ?string $deniedReason = null;

    public function __construct(
        public readonly DelegatableUserInterface $delegator,
        public readonly ?RoleInterface $role = null,
        public readonly ?PermissionInterface $permission = null,
        public readonly ?DelegatableUserInterface $target = null,
        public readonly string $action = 'assign',
    ) {}

    public static function forRoleAssignment(
        DelegatableUserInterface $delegator,
        RoleInterface $role,
        ?DelegatableUserInterface $target = null,
    ): self {
        return new self(
            delegator: $delegator,
            role: $role,
            target: $target,
            action: 'assign_role',
        );
    }

    public static function forPermissionAssignment(
        DelegatableUserInterface $delegator,
        PermissionInterface $permission,
        ?DelegatableUserInterface $target = null,
    ): self {
        return new self(
            delegator: $delegator,
            permission: $permission,
            target: $target,
            action: 'assign_permission',
        );
    }

    public static function forUserManagement(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
    ): self {
        return new self(
            delegator: $delegator,
            target: $target,
            action: 'manage_user',
        );
    }

    public function grant(): self
    {
        $this->result = true;

        return $this;
    }

    public function deny(?string $reason = null): self
    {
        $this->result = false;
        $this->deniedReason = $reason;

        return $this;
    }

    public function isResolved(): bool
    {
        return $this->result !== null;
    }

    public function isGranted(): bool
    {
        return $this->result === true;
    }

    public function isDenied(): bool
    {
        return $this->result === false;
    }

    public function getResult(): ?bool
    {
        return $this->result;
    }

    public function getDeniedReason(): ?string
    {
        return $this->deniedReason;
    }
}
