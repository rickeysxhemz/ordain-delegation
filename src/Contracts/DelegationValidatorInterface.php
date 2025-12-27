<?php

declare(strict_types=1);

namespace Ordain\Delegation\Contracts;

/**
 * Validates delegation operations.
 */
interface DelegationValidatorInterface
{
    /**
     * Validate delegation of roles and permissions.
     *
     * @param  array<int|string>  $roleIds
     * @param  array<int|string>  $permissionIds
     * @return array<string, string>
     */
    public function validate(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        array $roleIds = [],
        array $permissionIds = [],
    ): array;
}
