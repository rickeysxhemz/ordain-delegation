<?php

declare(strict_types=1);

use Ordain\Delegation\Domain\ValueObjects\DelegationResult;

describe('DelegationResult', function (): void {
    it('creates success result', function (): void {
        $result = DelegationResult::success('Operation completed');

        expect($result->isSuccess())->toBeTrue()
            ->and($result->isFailure())->toBeFalse()
            ->and($result->message)->toBe('Operation completed')
            ->and($result->errors)->toBeEmpty();
    });

    it('creates success result with data', function (): void {
        $result = DelegationResult::success('Created', ['id' => 123]);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getData('id'))->toBe(123)
            ->and($result->getData('missing', 'default'))->toBe('default');
    });

    it('creates failure result', function (): void {
        $result = DelegationResult::failure('Operation failed');

        expect($result->isSuccess())->toBeFalse()
            ->and($result->isFailure())->toBeTrue()
            ->and($result->message)->toBe('Operation failed');
    });

    it('creates failure result with errors', function (): void {
        $result = DelegationResult::failure('Validation failed', [
            'role' => 'Invalid role',
            'permission' => 'Invalid permission',
        ]);

        expect($result->hasErrors())->toBeTrue()
            ->and($result->getError('role'))->toBe('Invalid role')
            ->and($result->getError('permission'))->toBe('Invalid permission')
            ->and($result->getError('missing'))->toBeNull();
    });

    it('creates validation failed result', function (): void {
        $result = DelegationResult::validationFailed([
            'name' => 'Name is required',
        ]);

        expect($result->isFailure())->toBeTrue()
            ->and($result->message)->toBe('Validation failed.')
            ->and($result->getError('name'))->toBe('Name is required');
    });

    it('converts to array', function (): void {
        $result = DelegationResult::success('Done', ['key' => 'value']);

        expect($result->toArray())->toBe([
            'success' => true,
            'message' => 'Done',
            'data' => ['key' => 'value'],
            'errors' => [],
        ]);
    });
});
