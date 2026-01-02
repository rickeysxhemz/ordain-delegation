<?php

declare(strict_types=1);

namespace Ordain\Delegation\Domain\Specifications;

/**
 * Negates a specification.
 *
 * @template T
 *
 * @extends AbstractSpecification<T>
 */
final class NotSpecification extends AbstractSpecification
{
    /**
     * @param  SpecificationInterface<T>  $specification
     */
    public function __construct(
        private readonly SpecificationInterface $specification,
    ) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        if ($this->specification->isSatisfiedBy($candidate)) {
            return $this->fail('Specification should not be satisfied');
        }

        return $this->pass();
    }
}
