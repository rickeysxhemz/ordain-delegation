<?php

declare(strict_types=1);

namespace Ordain\Delegation\Domain\Specifications;

/**
 * Combines two specifications with AND logic.
 *
 * @template T
 *
 * @extends AbstractSpecification<T>
 */
final class AndSpecification extends AbstractSpecification
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
        if (! $this->left->isSatisfiedBy($candidate)) {
            $this->failureReason = $this->left->getFailureReason();

            return false;
        }

        if (! $this->right->isSatisfiedBy($candidate)) {
            $this->failureReason = $this->right->getFailureReason();

            return false;
        }

        return $this->pass();
    }
}
