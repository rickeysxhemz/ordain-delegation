<?php

declare(strict_types=1);

namespace Ordain\Delegation\Domain\Specifications;

/**
 * Checks if delegator is the creator of the target user.
 *
 * @extends AbstractSpecification<DelegationContext>
 */
final class UserIsCreatorSpecification extends AbstractSpecification
{
    public function isSatisfiedBy(mixed $candidate): bool
    {
        if (! $candidate instanceof DelegationContext) {
            return $this->fail('Invalid context type');
        }

        if ($candidate->target === null) {
            return $this->fail('Target user is required');
        }

        $creator = $candidate->target->getCreator();

        if ($creator === null) {
            return $this->fail('Target user has no creator');
        }

        if ($creator->getDelegatableIdentifier() !== $candidate->delegator->getDelegatableIdentifier()) {
            return $this->fail('Only the creator can manage this user');
        }

        return $this->pass();
    }
}
