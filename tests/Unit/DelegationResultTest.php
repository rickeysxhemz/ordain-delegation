<?php

declare(strict_types=1);

namespace Ordain\Delegation\Tests\Unit;

use Ordain\Delegation\Domain\ValueObjects\DelegationResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DelegationResultTest extends TestCase
{
    #[Test]
    public function success_creates_successful_result(): void
    {
        $result = DelegationResult::success();

        $this->assertTrue($result->success);
        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isFailure());
        $this->assertNull($result->message);
        $this->assertEmpty($result->errors);
    }

    #[Test]
    public function success_with_message_and_target(): void
    {
        $result = DelegationResult::success(
            message: 'Role assigned successfully.',
            targetUserId: 42,
            delegatorUserId: 1,
        );

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Role assigned successfully.', $result->message);
        $this->assertSame(42, $result->targetUserId);
        $this->assertSame(1, $result->delegatorUserId);
    }

    #[Test]
    public function failure_creates_failed_result(): void
    {
        $result = DelegationResult::failure('Operation failed.');

        $this->assertFalse($result->success);
        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isFailure());
        $this->assertSame('Operation failed.', $result->message);
    }

    #[Test]
    public function failure_with_errors(): void
    {
        $errors = [
            'role' => 'Invalid role.',
            'permission' => 'Permission not found.',
        ];

        $result = DelegationResult::failure('Validation failed.', $errors);

        $this->assertTrue($result->isFailure());
        $this->assertTrue($result->hasErrors());
        $this->assertSame($errors, $result->errors);
    }

    #[Test]
    public function validation_failed_creates_result_with_errors(): void
    {
        $errors = [
            'email' => 'Email is required.',
            'role_id' => 'Role not found.',
        ];

        $result = DelegationResult::validationFailed($errors);

        $this->assertTrue($result->isFailure());
        $this->assertTrue($result->hasErrors());
        $this->assertSame('Validation failed.', $result->message);
        $this->assertSame($errors, $result->errors);
    }

    #[Test]
    public function has_errors_returns_false_when_no_errors(): void
    {
        $result = DelegationResult::success();

        $this->assertFalse($result->hasErrors());
    }

    #[Test]
    public function get_error_returns_specific_error(): void
    {
        $errors = [
            'email' => 'Email is invalid.',
            'name' => 'Name is required.',
        ];

        $result = DelegationResult::failure('Validation failed.', $errors);

        $this->assertSame('Email is invalid.', $result->getError('email'));
        $this->assertSame('Name is required.', $result->getError('name'));
        $this->assertNull($result->getError('nonexistent'));
    }

    #[Test]
    public function role_assigned_creates_correct_result(): void
    {
        $result = DelegationResult::roleAssigned(42, 1, 'admin');

        $this->assertTrue($result->isSuccess());
        $this->assertSame(42, $result->targetUserId);
        $this->assertSame(1, $result->delegatorUserId);
        $this->assertSame('admin', $result->roleName);
        $this->assertStringContainsString('admin', $result->message ?? '');
    }

    #[Test]
    public function role_revoked_creates_correct_result(): void
    {
        $result = DelegationResult::roleRevoked(42, 1, 'admin');

        $this->assertTrue($result->isSuccess());
        $this->assertSame(42, $result->targetUserId);
        $this->assertSame(1, $result->delegatorUserId);
        $this->assertSame('admin', $result->roleName);
    }

    #[Test]
    public function permission_granted_creates_correct_result(): void
    {
        $result = DelegationResult::permissionGranted(42, 1, 'edit-posts');

        $this->assertTrue($result->isSuccess());
        $this->assertSame(42, $result->targetUserId);
        $this->assertSame(1, $result->delegatorUserId);
        $this->assertSame('edit-posts', $result->permissionName);
    }

    #[Test]
    public function permission_revoked_creates_correct_result(): void
    {
        $result = DelegationResult::permissionRevoked(42, 1, 'edit-posts');

        $this->assertTrue($result->isSuccess());
        $this->assertSame(42, $result->targetUserId);
        $this->assertSame(1, $result->delegatorUserId);
        $this->assertSame('edit-posts', $result->permissionName);
    }

    #[Test]
    public function to_array_returns_correct_structure(): void
    {
        $result = DelegationResult::roleAssigned(42, 1, 'admin');

        $array = $result->toArray();

        $this->assertTrue($array['success']);
        $this->assertSame(42, $array['target_user_id']);
        $this->assertSame(1, $array['delegator_user_id']);
        $this->assertSame('admin', $array['role_name']);
        $this->assertEmpty($array['errors']);
    }

    #[Test]
    public function to_array_includes_errors_for_failure(): void
    {
        $result = DelegationResult::failure(
            'Failed.',
            ['field' => 'Error message.'],
        );

        $array = $result->toArray();

        $this->assertFalse($array['success']);
        $this->assertSame('Failed.', $array['message']);
        $this->assertSame(['field' => 'Error message.'], $array['errors']);
    }

    #[Test]
    public function equals_returns_true_for_identical_results(): void
    {
        $result1 = DelegationResult::roleAssigned(42, 1, 'admin');
        $result2 = DelegationResult::roleAssigned(42, 1, 'admin');

        $this->assertTrue($result1->equals($result2));
    }

    #[Test]
    public function equals_returns_false_for_different_results(): void
    {
        $result1 = DelegationResult::roleAssigned(42, 1, 'admin');
        $result2 = DelegationResult::roleAssigned(42, 1, 'editor');

        $this->assertFalse($result1->equals($result2));
    }
}
