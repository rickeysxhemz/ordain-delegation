<?php

declare(strict_types=1);

namespace Ordain\Delegation\Domain\Specifications;

use Ordain\Delegation\Contracts\DelegationAuthorizerInterface;

/**
 * Checks if delegator can manage the target user.
 *
 * @extends AbstractSpecification<DelegationContext>
 */
final class CanManageUserSpecification extends AbstractSpecification
{
    public function __construct(
        private readonly DelegationAuthorizerInterface $authorizer,
    ) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        if (! $candidate instanceof DelegationContext) {
            return $this->fail('Invalid context type');
        }

        if ($candidate->target === null) {
            return $this->fail('Target user is required');
        }

        if (! $this->authorizer->canManageUser($candidate->delegator, $candidate->target)) {
            return $this->fail('You are not authorized to manage this user');
        }

        return $this->pass();
    }
}
