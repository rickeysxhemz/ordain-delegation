<?php

declare(strict_types=1);

namespace Ordain\Delegation\Domain\Specifications;

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\RoleInterface;

/**
 * Context object for delegation specifications.
 */
final readonly class DelegationContext
{
    /**
     * @param  array<RoleInterface>  $roles
     * @param  array<PermissionInterface>  $permissions
     */
    public function __construct(
        public DelegatableUserInterface $delegator,
        public ?DelegatableUserInterface $target = null,
        public array $roles = [],
        public array $permissions = [],
    ) {}

    public static function forUserManagement(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
    ): self {
        return new self(
            delegator: $delegator,
            target: $target,
        );
    }

    /**
     * @param  array<RoleInterface>  $roles
     * @param  array<PermissionInterface>  $permissions
     */
    public static function forDelegation(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        array $roles = [],
        array $permissions = [],
    ): self {
        return new self(
            delegator: $delegator,
            target: $target,
            roles: $roles,
            permissions: $permissions,
        );
    }
}
