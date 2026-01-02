<?php

declare(strict_types=1);

namespace Ordain\Delegation\Domain\Specifications;

use Ordain\Delegation\Contracts\DelegationAuthorizerInterface;
use Ordain\Delegation\Contracts\PermissionInterface;

/**
 * Checks if delegator can assign all specified permissions.
 *
 * @extends AbstractSpecification<DelegationContext>
 */
final class CanAssignPermissionsSpecification extends AbstractSpecification
{
    /** @var array<string, string> */
    private array $permissionErrors = [];

    public function __construct(
        private readonly DelegationAuthorizerInterface $authorizer,
    ) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        if (! $candidate instanceof DelegationContext) {
            return $this->fail('Invalid context type');
        }

        $this->permissionErrors = [];

        foreach ($candidate->permissions as $permission) {
            if (! $permission instanceof PermissionInterface) {
                continue;
            }

            if (! $this->authorizer->canAssignPermission($candidate->delegator, $permission, $candidate->target)) {
                $this->permissionErrors[$permission->getPermissionName()] = "Cannot assign permission '{$permission->getPermissionName()}'";
            }
        }

        if ($this->permissionErrors !== []) {
            return $this->fail(implode('; ', $this->permissionErrors));
        }

        return $this->pass();
    }

    /**
     * @return array<string, string>
     */
    public function getPermissionErrors(): array
    {
        return $this->permissionErrors;
    }
}
