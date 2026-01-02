<?php

declare(strict_types=1);

namespace Ordain\Delegation\Domain\Specifications;

use Ordain\Delegation\Contracts\QuotaManagerInterface;

/**
 * Checks if delegator has not exceeded their user management quota.
 *
 * @extends AbstractSpecification<DelegationContext>
 */
final class QuotaNotExceededSpecification extends AbstractSpecification
{
    public function __construct(
        private readonly QuotaManagerInterface $quotaManager,
    ) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        if (! $candidate instanceof DelegationContext) {
            return $this->fail('Invalid context type');
        }

        if ($this->quotaManager->hasReachedLimit($candidate->delegator)) {
            return $this->fail('User management quota exceeded');
        }

        return $this->pass();
    }
}
