<?php

declare(strict_types=1);

namespace Ewaa\PermissionDelegation\Tests\Unit;

use Ewaa\PermissionDelegation\Domain\ValueObjects\DelegationResult;
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
        $this->assertEmpty($result->data);
        $this->assertEmpty($result->errors);
    }

    #[Test]
    public function success_with_message_and_data(): void
    {
        $result = DelegationResult::success(
            message: 'Role assigned successfully.',
            data: ['role_id' => 1, 'user_id' => 42]
        );

        $this->assertTrue($result->isSuccess());
        $this->assertSame('Role assigned successfully.', $result->message);
        $this->assertSame(['role_id' => 1, 'user_id' => 42], $result->data);
    }

    #[Test]
    public function failure_creates_failed_result(): void
    {
        $result = DelegationResult::failure('Operation failed.');

        $this->assertFalse($result->success);
        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isFailure());
        $this->assertSame('Operation failed.', $result->message);
        $this->assertEmpty($result->data);
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
    public function get_data_returns_specific_data(): void
    {
        $result = DelegationResult::success(
            data: ['user_id' => 42, 'role' => 'admin']
        );

        $this->assertSame(42, $result->getData('user_id'));
        $this->assertSame('admin', $result->getData('role'));
        $this->assertNull($result->getData('nonexistent'));
    }

    #[Test]
    public function get_data_returns_default_when_key_missing(): void
    {
        $result = DelegationResult::success();

        $this->assertSame('default_value', $result->getData('missing', 'default_value'));
        $this->assertSame(100, $result->getData('missing', 100));
    }

    #[Test]
    public function to_array_returns_correct_structure(): void
    {
        $result = DelegationResult::success(
            message: 'Done.',
            data: ['id' => 1]
        );

        $array = $result->toArray();

        $this->assertSame([
            'success' => true,
            'message' => 'Done.',
            'data' => ['id' => 1],
            'errors' => [],
        ], $array);
    }

    #[Test]
    public function to_array_includes_errors_for_failure(): void
    {
        $result = DelegationResult::failure(
            'Failed.',
            ['field' => 'Error message.']
        );

        $array = $result->toArray();

        $this->assertSame([
            'success' => false,
            'message' => 'Failed.',
            'data' => [],
            'errors' => ['field' => 'Error message.'],
        ], $array);
    }
}
