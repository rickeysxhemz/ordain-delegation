<?php

declare(strict_types=1);

namespace Ordain\Delegation\Tests\Unit;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Exceptions\UnauthorizedDelegationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UnauthorizedDelegationExceptionTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    #[Test]
    public function cannot_assign_role_creates_correct_exception(): void
    {
        $delegator = $this->createMockUser(1);

        $exception = UnauthorizedDelegationException::cannotAssignRole($delegator, 'admin');

        $this->assertStringContainsString('admin', $exception->getMessage());
        $this->assertStringContainsString('not authorized to assign role', $exception->getMessage());
        $this->assertSame($delegator, $exception->getDelegator());
        $this->assertSame('assign_role', $exception->getAttemptedAction());
    }

    #[Test]
    public function cannot_grant_permission_creates_correct_exception(): void
    {
        $delegator = $this->createMockUser(1);

        $exception = UnauthorizedDelegationException::cannotGrantPermission($delegator, 'create-posts');

        $this->assertStringContainsString('create-posts', $exception->getMessage());
        $this->assertStringContainsString('not authorized to grant permission', $exception->getMessage());
        $this->assertSame('grant_permission', $exception->getAttemptedAction());
    }

    #[Test]
    public function cannot_revoke_role_creates_correct_exception(): void
    {
        $delegator = $this->createMockUser(1);

        $exception = UnauthorizedDelegationException::cannotRevokeRole($delegator, 'editor');

        $this->assertStringContainsString('editor', $exception->getMessage());
        $this->assertStringContainsString('not authorized to revoke role', $exception->getMessage());
        $this->assertSame('revoke_role', $exception->getAttemptedAction());
    }

    #[Test]
    public function cannot_revoke_permission_creates_correct_exception(): void
    {
        $delegator = $this->createMockUser(1);

        $exception = UnauthorizedDelegationException::cannotRevokePermission($delegator, 'delete-posts');

        $this->assertStringContainsString('delete-posts', $exception->getMessage());
        $this->assertStringContainsString('not authorized to revoke permission', $exception->getMessage());
        $this->assertSame('revoke_permission', $exception->getAttemptedAction());
    }

    #[Test]
    public function cannot_create_users_creates_correct_exception(): void
    {
        $delegator = $this->createMockUser(1);

        $exception = UnauthorizedDelegationException::cannotCreateUsers($delegator);

        $this->assertStringContainsString('not authorized to create new users', $exception->getMessage());
        $this->assertSame('create_user', $exception->getAttemptedAction());
    }

    #[Test]
    public function user_limit_reached_creates_correct_exception(): void
    {
        $delegator = $this->createMockUser(1);

        $exception = UnauthorizedDelegationException::userLimitReached($delegator, 10);

        $this->assertStringContainsString('10', $exception->getMessage());
        $this->assertStringContainsString('reached their limit', $exception->getMessage());
        $this->assertSame('create_user', $exception->getAttemptedAction());
    }

    #[Test]
    public function cannot_manage_user_creates_correct_exception(): void
    {
        $delegator = $this->createMockUser(1);
        $target = $this->createMockUser(2);

        $exception = UnauthorizedDelegationException::cannotManageUser($delegator, $target);

        $this->assertStringContainsString('not authorized to manage this user', $exception->getMessage());
        $this->assertSame('manage_user', $exception->getAttemptedAction());
    }

    #[Test]
    public function exception_provides_context(): void
    {
        $delegator = $this->createMockUser(1);

        $exception = UnauthorizedDelegationException::cannotAssignRole($delegator, 'admin');

        $context = $exception->getContext();

        $this->assertArrayHasKey('role', $context);
        $this->assertSame('admin', $context['role']);
    }

    private function createMockUser(int $id): DelegatableUserInterface
    {
        $user = Mockery::mock(DelegatableUserInterface::class);
        $user->shouldReceive('getDelegatableIdentifier')->andReturn($id);

        return $user;
    }
}
