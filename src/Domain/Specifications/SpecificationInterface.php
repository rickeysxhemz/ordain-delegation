<?php

declare(strict_types=1);

namespace Ordain\Delegation\Domain\Specifications;

/**
 * Specification pattern interface for validation rules.
 *
 * @template T
 */
interface SpecificationInterface
{
    /**
     * Check if the candidate satisfies this specification.
     *
     * @param  T  $candidate
     */
    public function isSatisfiedBy(mixed $candidate): bool;

    /**
     * Get the reason for failure if not satisfied.
     */
    public function getFailureReason(): ?string;

    /**
     * Combine with another specification using AND logic.
     *
     * @param  SpecificationInterface<T>  $other
     * @return SpecificationInterface<T>
     */
    public function and(SpecificationInterface $other): SpecificationInterface;

    /**
     * Combine with another specification using OR logic.
     *
     * @param  SpecificationInterface<T>  $other
     * @return SpecificationInterface<T>
     */
    public function or(SpecificationInterface $other): SpecificationInterface;

    /**
     * Negate this specification.
     *
     * @return SpecificationInterface<T>
     */
    public function not(): SpecificationInterface;
}
