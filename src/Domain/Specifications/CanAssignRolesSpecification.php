<?php

declare(strict_types=1);

namespace Ordain\Delegation\Domain\Specifications;

use Ordain\Delegation\Contracts\DelegationAuthorizerInterface;
use Ordain\Delegation\Contracts\RoleInterface;

/**
 * Checks if delegator can assign all specified roles.
 *
 * @extends AbstractSpecification<DelegationContext>
 */
final class CanAssignRolesSpecification extends AbstractSpecification
{
    /** @var array<string, string> */
    private array $roleErrors = [];

    public function __construct(
        private readonly DelegationAuthorizerInterface $authorizer,
    ) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        if (! $candidate instanceof DelegationContext) {
            return $this->fail('Invalid context type');
        }

        $this->roleErrors = [];

        foreach ($candidate->roles as $role) {
            if (! $role instanceof RoleInterface) {
                continue;
            }

            if (! $this->authorizer->canAssignRole($candidate->delegator, $role, $candidate->target)) {
                $this->roleErrors[$role->getRoleName()] = "Cannot assign role '{$role->getRoleName()}'";
            }
        }

        if ($this->roleErrors !== []) {
            return $this->fail(implode('; ', $this->roleErrors));
        }

        return $this->pass();
    }

    /**
     * @return array<string, string>
     */
    public function getRoleErrors(): array
    {
        return $this->roleErrors;
    }
}
