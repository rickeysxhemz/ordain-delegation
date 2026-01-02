<?php

declare(strict_types=1);

namespace Ordain\Delegation\Domain\Specifications;

/**
 * Base class providing composite specification operations.
 *
 * @template T
 *
 * @implements SpecificationInterface<T>
 */
abstract class AbstractSpecification implements SpecificationInterface
{
    protected ?string $failureReason = null;

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    /**
     * @param  SpecificationInterface<T>  $other
     * @return SpecificationInterface<T>
     */
    public function and(SpecificationInterface $other): SpecificationInterface
    {
        return new AndSpecification($this, $other);
    }

    /**
     * @param  SpecificationInterface<T>  $other
     * @return SpecificationInterface<T>
     */
    public function or(SpecificationInterface $other): SpecificationInterface
    {
        return new OrSpecification($this, $other);
    }

    /**
     * @return SpecificationInterface<T>
     */
    public function not(): SpecificationInterface
    {
        return new NotSpecification($this);
    }

    protected function fail(string $reason): bool
    {
        $this->failureReason = $reason;

        return false;
    }

    protected function pass(): bool
    {
        $this->failureReason = null;

        return true;
    }
}
