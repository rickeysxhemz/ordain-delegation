<?php

declare(strict_types=1);

namespace Ordain\Delegation\Tests\Unit;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Services\Audit\NullDelegationAudit;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NullDelegationAuditTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private NullDelegationAudit $audit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->audit = new NullDelegationAudit;
    }

    #[Test]
    public function log_role_assigned_does_nothing(): void
    {
        $delegator = $this->createMockUser(1);
        $target = $this->createMockUser(2);
        $role = $this->createMockRole(1, 'admin');

        $this->audit->logRoleAssigned($delegator, $target, $role);

        $this->assertTrue(true);
    }

    #[Test]
    public function log_role_revoked_does_nothing(): void
    {
        $delegator = $this->createMockUser(1);
        $target = $this->createMockUser(2);
        $role = $this->createMockRole(1, 'admin');

        $this->audit->logRoleRevoked($delegator, $target, $role);

        $this->assertTrue(true);
    }

    #[Test]
    public function log_permission_granted_does_nothing(): void
    {
        $delegator = $this->createMockUser(1);
        $target = $this->createMockUser(2);
        $permission = $this->createMockPermission(1, 'create-posts');

        $this->audit->logPermissionGranted($delegator, $target, $permission);

        $this->assertTrue(true);
    }

    #[Test]
    public function log_permission_revoked_does_nothing(): void
    {
        $delegator = $this->createMockUser(1);
        $target = $this->createMockUser(2);
        $permission = $this->createMockPermission(1, 'delete-posts');

        $this->audit->logPermissionRevoked($delegator, $target, $permission);

        $this->assertTrue(true);
    }

    #[Test]
    public function log_delegation_scope_changed_does_nothing(): void
    {
        $admin = $this->createMockUser(1);
        $user = $this->createMockUser(2);

        $this->audit->logDelegationScopeChanged($admin, $user, [
            'old' => [
                'can_manage_users' => false,
                'max_manageable_users' => null,
                'assignable_role_ids' => [],
                'assignable_permission_ids' => [],
            ],
            'new' => [
                'can_manage_users' => true,
                'max_manageable_users' => 10,
                'assignable_role_ids' => [1, 2],
                'assignable_permission_ids' => [1],
            ],
        ]);

        $this->assertTrue(true);
    }

    #[Test]
    public function log_unauthorized_attempt_does_nothing(): void
    {
        $delegator = $this->createMockUser(1);

        $this->audit->logUnauthorizedAttempt($delegator, 'assign_role', [
            'role' => 'admin',
        ]);

        $this->assertTrue(true);
    }

    #[Test]
    public function log_user_created_does_nothing(): void
    {
        $creator = $this->createMockUser(1);
        $createdUser = $this->createMockUser(2);

        $this->audit->logUserCreated($creator, $createdUser);

        $this->assertTrue(true);
    }

    private function createMockUser(int $id): DelegatableUserInterface
    {
        $user = Mockery::mock(DelegatableUserInterface::class);
        $user->shouldReceive('getDelegatableIdentifier')->andReturn($id);

        return $user;
    }

    private function createMockRole(int $id, string $name): RoleInterface
    {
        $role = Mockery::mock(RoleInterface::class);
        $role->shouldReceive('getRoleIdentifier')->andReturn($id);
        $role->shouldReceive('getRoleName')->andReturn($name);

        return $role;
    }

    private function createMockPermission(int $id, string $name): PermissionInterface
    {
        $permission = Mockery::mock(PermissionInterface::class);
        $permission->shouldReceive('getPermissionIdentifier')->andReturn($id);
        $permission->shouldReceive('getPermissionName')->andReturn($name);

        return $permission;
    }
}
