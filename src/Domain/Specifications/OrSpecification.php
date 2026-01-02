<?php

declare(strict_types=1);

namespace Ordain\Delegation\Domain\Specifications;

/**
 * Combines two specifications with OR logic.
 *
 * @template T
 *
 * @extends AbstractSpecification<T>
 */
final class OrSpecification extends AbstractSpecification
{
    /**
     * @param  SpecificationInterface<T>  $left
     * @param  SpecificationInterface<T>  $right
     */
    public function __construct(
        private readonly SpecificationInterface $left,
        private readonly SpecificationInterface $right,
    ) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        if ($this->left->isSatisfiedBy($candidate)) {
            return $this->pass();
        }

        if ($this->right->isSatisfiedBy($candidate)) {
            return $this->pass();
        }

        $this->failureReason = $this->right->getFailureReason() ?? $this->left->getFailureReason();

        return false;
    }
}
