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

    it('creates success result with target user', function (): void {
        $result = DelegationResult::success(
            message: 'Created',
            targetUserId: 123,
            delegatorUserId: 1,
        );

        expect($result->isSuccess())->toBeTrue()
            ->and($result->targetUserId)->toBe(123)
            ->and($result->delegatorUserId)->toBe(1);
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

    it('creates role assigned result', function (): void {
        $result = DelegationResult::roleAssigned(42, 1, 'admin');

        expect($result->isSuccess())->toBeTrue()
            ->and($result->targetUserId)->toBe(42)
            ->and($result->delegatorUserId)->toBe(1)
            ->and($result->roleName)->toBe('admin');
    });

    it('creates permission granted result', function (): void {
        $result = DelegationResult::permissionGranted(42, 1, 'edit-posts');

        expect($result->isSuccess())->toBeTrue()
            ->and($result->targetUserId)->toBe(42)
            ->and($result->permissionName)->toBe('edit-posts');
    });

    it('converts to array', function (): void {
        $result = DelegationResult::roleAssigned(42, 1, 'admin');

        $array = $result->toArray();

        expect($array['success'])->toBeTrue()
            ->and($array['target_user_id'])->toBe(42)
            ->and($array['delegator_user_id'])->toBe(1)
            ->and($array['role_name'])->toBe('admin')
            ->and($array['errors'])->toBeEmpty();
    });
});
