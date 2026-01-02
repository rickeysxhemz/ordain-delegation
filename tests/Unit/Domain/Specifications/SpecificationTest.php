<?php

declare(strict_types=1);

use Ordain\Delegation\Domain\Specifications\AbstractSpecification;
use Ordain\Delegation\Domain\Specifications\AndSpecification;
use Ordain\Delegation\Domain\Specifications\NotSpecification;
use Ordain\Delegation\Domain\Specifications\OrSpecification;
use Ordain\Delegation\Domain\Specifications\SpecificationInterface;

// Create a simple test specification for testing composite operations
class TrueSpecification extends AbstractSpecification
{
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $this->pass();
    }
}

class FalseSpecification extends AbstractSpecification
{
    public function __construct(private string $reason = 'Failed') {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $this->fail($this->reason);
    }
}

describe('AbstractSpecification', function (): void {
    it('implements SpecificationInterface', function (): void {
        $spec = new TrueSpecification;

        expect($spec)->toBeInstanceOf(SpecificationInterface::class);
    });

    it('returns null failure reason when satisfied', function (): void {
        $spec = new TrueSpecification;
        $spec->isSatisfiedBy('anything');

        expect($spec->getFailureReason())->toBeNull();
    });

    it('returns failure reason when not satisfied', function (): void {
        $spec = new FalseSpecification('Custom reason');
        $spec->isSatisfiedBy('anything');

        expect($spec->getFailureReason())->toBe('Custom reason');
    });

    it('creates AndSpecification via and method', function (): void {
        $spec = new TrueSpecification;
        $combined = $spec->and(new TrueSpecification);

        expect($combined)->toBeInstanceOf(AndSpecification::class);
    });

    it('creates OrSpecification via or method', function (): void {
        $spec = new TrueSpecification;
        $combined = $spec->or(new FalseSpecification);

        expect($combined)->toBeInstanceOf(OrSpecification::class);
    });

    it('creates NotSpecification via not method', function (): void {
        $spec = new TrueSpecification;
        $negated = $spec->not();

        expect($negated)->toBeInstanceOf(NotSpecification::class);
    });
});

describe('AndSpecification', function (): void {
    it('returns true when both specifications are satisfied', function (): void {
        $spec = new AndSpecification(new TrueSpecification, new TrueSpecification);

        expect($spec->isSatisfiedBy('anything'))->toBeTrue()
            ->and($spec->getFailureReason())->toBeNull();
    });

    it('returns false when left specification fails', function (): void {
        $spec = new AndSpecification(new FalseSpecification('Left failed'), new TrueSpecification);

        expect($spec->isSatisfiedBy('anything'))->toBeFalse()
            ->and($spec->getFailureReason())->toBe('Left failed');
    });

    it('returns false when right specification fails', function (): void {
        $spec = new AndSpecification(new TrueSpecification, new FalseSpecification('Right failed'));

        expect($spec->isSatisfiedBy('anything'))->toBeFalse()
            ->and($spec->getFailureReason())->toBe('Right failed');
    });

    it('returns false when both specifications fail with left reason', function (): void {
        $spec = new AndSpecification(
            new FalseSpecification('Left failed'),
            new FalseSpecification('Right failed'),
        );

        expect($spec->isSatisfiedBy('anything'))->toBeFalse()
            ->and($spec->getFailureReason())->toBe('Left failed');
    });

    it('can be chained', function (): void {
        $spec = (new TrueSpecification)
            ->and(new TrueSpecification)
            ->and(new TrueSpecification);

        expect($spec->isSatisfiedBy('anything'))->toBeTrue();
    });
});

describe('OrSpecification', function (): void {
    it('returns true when left specification is satisfied', function (): void {
        $spec = new OrSpecification(new TrueSpecification, new FalseSpecification);

        expect($spec->isSatisfiedBy('anything'))->toBeTrue()
            ->and($spec->getFailureReason())->toBeNull();
    });

    it('returns true when right specification is satisfied', function (): void {
        $spec = new OrSpecification(new FalseSpecification, new TrueSpecification);

        expect($spec->isSatisfiedBy('anything'))->toBeTrue()
            ->and($spec->getFailureReason())->toBeNull();
    });

    it('returns true when both specifications are satisfied', function (): void {
        $spec = new OrSpecification(new TrueSpecification, new TrueSpecification);

        expect($spec->isSatisfiedBy('anything'))->toBeTrue();
    });

    it('returns false when both specifications fail', function (): void {
        $spec = new OrSpecification(
            new FalseSpecification('Left failed'),
            new FalseSpecification('Right failed'),
        );

        expect($spec->isSatisfiedBy('anything'))->toBeFalse()
            ->and($spec->getFailureReason())->toBe('Right failed');
    });

    it('can be chained', function (): void {
        $spec = (new FalseSpecification)
            ->or(new FalseSpecification)
            ->or(new TrueSpecification);

        expect($spec->isSatisfiedBy('anything'))->toBeTrue();
    });
});

describe('NotSpecification', function (): void {
    it('returns true when inner specification is not satisfied', function (): void {
        $spec = new NotSpecification(new FalseSpecification);

        expect($spec->isSatisfiedBy('anything'))->toBeTrue()
            ->and($spec->getFailureReason())->toBeNull();
    });

    it('returns false when inner specification is satisfied', function (): void {
        $spec = new NotSpecification(new TrueSpecification);

        expect($spec->isSatisfiedBy('anything'))->toBeFalse()
            ->and($spec->getFailureReason())->toBe('Specification should not be satisfied');
    });

    it('can be chained with and', function (): void {
        $spec = (new TrueSpecification)
            ->and((new FalseSpecification)->not());

        expect($spec->isSatisfiedBy('anything'))->toBeTrue();
    });
});

describe('complex composite specifications', function (): void {
    it('handles (A AND B) OR C', function (): void {
        // (false AND true) OR true = true
        $spec = (new FalseSpecification)
            ->and(new TrueSpecification)
            ->or(new TrueSpecification);

        expect($spec->isSatisfiedBy('anything'))->toBeTrue();
    });

    it('handles A AND (B OR C)', function (): void {
        // true AND (false OR true) = true
        $spec = (new TrueSpecification)
            ->and((new FalseSpecification)->or(new TrueSpecification));

        expect($spec->isSatisfiedBy('anything'))->toBeTrue();
    });

    it('handles NOT (A AND B)', function (): void {
        // NOT (true AND false) = true
        $spec = (new TrueSpecification)
            ->and(new FalseSpecification)
            ->not();

        expect($spec->isSatisfiedBy('anything'))->toBeTrue();
    });
});
