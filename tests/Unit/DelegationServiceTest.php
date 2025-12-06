<?php

declare(strict_types=1);

namespace Ewaa\PermissionDelegation\Tests\Unit;

use Ewaa\PermissionDelegation\Contracts\DelegatableUserInterface;
use Ewaa\PermissionDelegation\Contracts\DelegationAuditInterface;
use Ewaa\PermissionDelegation\Contracts\PermissionInterface;
use Ewaa\PermissionDelegation\Contracts\Repositories\DelegationRepositoryInterface;
use Ewaa\PermissionDelegation\Contracts\Repositories\PermissionRepositoryInterface;
use Ewaa\PermissionDelegation\Contracts\Repositories\RoleRepositoryInterface;
use Ewaa\PermissionDelegation\Contracts\RoleInterface;
use Ewaa\PermissionDelegation\Domain\ValueObjects\DelegationScope;
use Ewaa\PermissionDelegation\Exceptions\UnauthorizedDelegationException;
use Ewaa\PermissionDelegation\Services\DelegationService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DelegationServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private DelegationRepositoryInterface $delegationRepository;

    private RoleRepositoryInterface $roleRepository;

    private PermissionRepositoryInterface $permissionRepository;

    private DelegationAuditInterface $audit;

    private DelegationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->delegationRepository = Mockery::mock(DelegationRepositoryInterface::class);
        $this->roleRepository = Mockery::mock(RoleRepositoryInterface::class);
        $this->permissionRepository = Mockery::mock(PermissionRepositoryInterface::class);
        $this->audit = Mockery::mock(DelegationAuditInterface::class);

        $this->service = new DelegationService(
            delegationRepository: $this->delegationRepository,
            roleRepository: $this->roleRepository,
            permissionRepository: $this->permissionRepository,
            audit: $this->audit,
            superAdminBypassEnabled: true,
            superAdminIdentifier: 'super-admin'
        );
    }

    #[Test]
    public function super_admin_can_assign_any_role(): void
    {
        $delegator = $this->createMockUser(1);
        $role = $this->createMockRole(1, 'admin');
        $superAdminRole = $this->createMockRole(999, 'super-admin');

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([$superAdminRole]));

        $result = $this->service->canAssignRole($delegator, $role);

        $this->assertTrue($result);
    }

    #[Test]
    public function non_manager_cannot_assign_roles(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: false);
        $role = $this->createMockRole(1, 'admin');

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $result = $this->service->canAssignRole($delegator, $role);

        $this->assertFalse($result);
    }

    #[Test]
    public function manager_can_assign_role_in_assignable_list(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: true);
        $role = $this->createMockRole(1, 'editor');

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $this->delegationRepository
            ->shouldReceive('hasAssignableRole')
            ->with($delegator, $role)
            ->andReturn(true);

        $result = $this->service->canAssignRole($delegator, $role);

        $this->assertTrue($result);
    }

    #[Test]
    public function manager_cannot_assign_role_not_in_assignable_list(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: true);
        $role = $this->createMockRole(1, 'super-admin');

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $this->delegationRepository
            ->shouldReceive('hasAssignableRole')
            ->with($delegator, $role)
            ->andReturn(false);

        $result = $this->service->canAssignRole($delegator, $role);

        $this->assertFalse($result);
    }

    #[Test]
    public function manager_cannot_assign_role_to_user_they_did_not_create(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: true);
        $target = $this->createMockUserWithCreator(2, 999);
        $role = $this->createMockRole(1, 'editor');

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $result = $this->service->canAssignRole($delegator, $role, $target);

        $this->assertFalse($result);
    }

    #[Test]
    public function manager_can_assign_role_to_user_they_created(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: true);
        $target = $this->createMockUserWithCreator(2, 1);
        $role = $this->createMockRole(1, 'editor');

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $this->delegationRepository
            ->shouldReceive('hasAssignableRole')
            ->with($delegator, $role)
            ->andReturn(true);

        $result = $this->service->canAssignRole($delegator, $role, $target);

        $this->assertTrue($result);
    }

    #[Test]
    public function can_assign_permission_checks_assignable_list(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: true);
        $permission = $this->createMockPermission(1, 'create-posts');

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $this->delegationRepository
            ->shouldReceive('hasAssignablePermission')
            ->with($delegator, $permission)
            ->andReturn(true);

        $result = $this->service->canAssignPermission($delegator, $permission);

        $this->assertTrue($result);
    }

    #[Test]
    public function can_create_users_returns_true_for_manager_under_limit(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: true, maxUsers: 10);

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $this->delegationRepository
            ->shouldReceive('getCreatedUsersCount')
            ->with($delegator)
            ->andReturn(5);

        $result = $this->service->canCreateUsers($delegator);

        $this->assertTrue($result);
    }

    #[Test]
    public function can_create_users_returns_false_when_limit_reached(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: true, maxUsers: 5);

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $this->delegationRepository
            ->shouldReceive('getCreatedUsersCount')
            ->with($delegator)
            ->andReturn(5);

        $result = $this->service->canCreateUsers($delegator);

        $this->assertFalse($result);
    }

    #[Test]
    public function can_create_users_returns_true_for_unlimited_manager(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: true, maxUsers: null);

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $result = $this->service->canCreateUsers($delegator);

        $this->assertTrue($result);
    }

    #[Test]
    public function has_reached_user_limit_returns_correct_value(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: true, maxUsers: 3);

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $this->delegationRepository
            ->shouldReceive('getCreatedUsersCount')
            ->with($delegator)
            ->andReturn(3);

        $result = $this->service->hasReachedUserLimit($delegator);

        $this->assertTrue($result);
    }

    #[Test]
    public function get_remaining_user_quota_calculates_correctly(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: true, maxUsers: 10);

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $this->delegationRepository
            ->shouldReceive('getCreatedUsersCount')
            ->with($delegator)
            ->andReturn(7);

        $result = $this->service->getRemainingUserQuota($delegator);

        $this->assertSame(3, $result);
    }

    #[Test]
    public function get_remaining_user_quota_returns_null_for_unlimited(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: true, maxUsers: null);

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $result = $this->service->getRemainingUserQuota($delegator);

        $this->assertNull($result);
    }

    #[Test]
    public function get_assignable_roles_returns_all_roles_for_super_admin(): void
    {
        $delegator = $this->createMockUser(1);
        $superAdminRole = $this->createMockRole(999, 'super-admin');
        $allRoles = collect([
            $this->createMockRole(1, 'admin'),
            $this->createMockRole(2, 'editor'),
        ]);

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([$superAdminRole]));

        $this->roleRepository
            ->shouldReceive('all')
            ->andReturn($allRoles);

        $result = $this->service->getAssignableRoles($delegator);

        $this->assertCount(2, $result);
    }

    #[Test]
    public function get_assignable_roles_returns_limited_roles_for_manager(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: true);
        $assignableRoles = collect([
            $this->createMockRole(1, 'editor'),
        ]);

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $this->delegationRepository
            ->shouldReceive('getAssignableRoles')
            ->with($delegator)
            ->andReturn($assignableRoles);

        $result = $this->service->getAssignableRoles($delegator);

        $this->assertCount(1, $result);
    }

    #[Test]
    public function delegate_role_assigns_role_when_authorized(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: true);
        $target = $this->createMockUserWithCreator(2, 1);
        $role = $this->createMockRole(1, 'editor');

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $this->delegationRepository
            ->shouldReceive('hasAssignableRole')
            ->with($delegator, $role)
            ->andReturn(true);

        $this->roleRepository
            ->shouldReceive('assignToUser')
            ->with($target, $role)
            ->once();

        $this->audit
            ->shouldReceive('logRoleAssigned')
            ->with($delegator, $target, $role)
            ->once();

        $this->service->delegateRole($delegator, $target, $role);

        $this->assertTrue(true);
    }

    #[Test]
    public function delegate_role_throws_exception_when_not_authorized(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: false);
        $target = $this->createMockUserWithCreator(2, 1);
        $role = $this->createMockRole(1, 'admin');

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $this->audit
            ->shouldReceive('logUnauthorizedAttempt')
            ->once();

        $this->expectException(UnauthorizedDelegationException::class);
        $this->expectExceptionMessage("User is not authorized to assign role 'admin'.");

        $this->service->delegateRole($delegator, $target, $role);
    }

    #[Test]
    public function delegate_permission_assigns_permission_when_authorized(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: true);
        $target = $this->createMockUserWithCreator(2, 1);
        $permission = $this->createMockPermission(1, 'create-posts');

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $this->delegationRepository
            ->shouldReceive('hasAssignablePermission')
            ->with($delegator, $permission)
            ->andReturn(true);

        $this->permissionRepository
            ->shouldReceive('assignToUser')
            ->with($target, $permission)
            ->once();

        $this->audit
            ->shouldReceive('logPermissionGranted')
            ->with($delegator, $target, $permission)
            ->once();

        $this->service->delegatePermission($delegator, $target, $permission);

        $this->assertTrue(true);
    }

    #[Test]
    public function revoke_role_removes_role_when_authorized(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: true);
        $target = $this->createMockUserWithCreator(2, 1);
        $role = $this->createMockRole(1, 'editor');

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $this->delegationRepository
            ->shouldReceive('hasAssignableRole')
            ->with($delegator, $role)
            ->andReturn(true);

        $this->roleRepository
            ->shouldReceive('removeFromUser')
            ->with($target, $role)
            ->once();

        $this->audit
            ->shouldReceive('logRoleRevoked')
            ->with($delegator, $target, $role)
            ->once();

        $this->service->revokeRole($delegator, $target, $role);

        $this->assertTrue(true);
    }

    #[Test]
    public function revoke_permission_removes_permission_when_authorized(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: true);
        $target = $this->createMockUserWithCreator(2, 1);
        $permission = $this->createMockPermission(1, 'edit-posts');

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $this->delegationRepository
            ->shouldReceive('hasAssignablePermission')
            ->with($delegator, $permission)
            ->andReturn(true);

        $this->permissionRepository
            ->shouldReceive('removeFromUser')
            ->with($target, $permission)
            ->once();

        $this->audit
            ->shouldReceive('logPermissionRevoked')
            ->with($delegator, $target, $permission)
            ->once();

        $this->service->revokePermission($delegator, $target, $permission);

        $this->assertTrue(true);
    }

    #[Test]
    public function can_manage_user_returns_false_for_self(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: true);
        $target = $this->createMockUser(1, canManageUsers: true);

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $result = $this->service->canManageUser($delegator, $target);

        $this->assertFalse($result);
    }

    #[Test]
    public function can_manage_user_returns_true_for_created_user(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: true);
        $target = $this->createMockUserWithCreator(2, 1);

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $result = $this->service->canManageUser($delegator, $target);

        $this->assertTrue($result);
    }

    #[Test]
    public function set_delegation_scope_updates_all_settings(): void
    {
        $user = $this->createMockUser(1);
        $scope = new DelegationScope(
            canManageUsers: true,
            maxManageableUsers: 10,
            assignableRoleIds: [1, 2],
            assignablePermissionIds: [3, 4]
        );

        $this->delegationRepository
            ->shouldReceive('updateDelegationSettings')
            ->with($user, true, 10)
            ->once();

        $this->delegationRepository
            ->shouldReceive('syncAssignableRoles')
            ->with($user, [1, 2])
            ->once();

        $this->delegationRepository
            ->shouldReceive('syncAssignablePermissions')
            ->with($user, [3, 4])
            ->once();

        $this->service->setDelegationScope($user, $scope);

        $this->assertTrue(true);
    }

    #[Test]
    public function get_delegation_scope_returns_correct_scope(): void
    {
        $user = $this->createMockUser(1, canManageUsers: true, maxUsers: 5);
        $role = $this->createMockRole(1, 'editor');
        $permission = $this->createMockPermission(1, 'create-posts');

        $this->delegationRepository
            ->shouldReceive('getAssignableRoles')
            ->with($user)
            ->andReturn(collect([$role]));

        $this->delegationRepository
            ->shouldReceive('getAssignablePermissions')
            ->with($user)
            ->andReturn(collect([$permission]));

        $result = $this->service->getDelegationScope($user);

        $this->assertTrue($result->canManageUsers);
        $this->assertSame(5, $result->maxManageableUsers);
        $this->assertSame([1], $result->assignableRoleIds);
        $this->assertSame([1], $result->assignablePermissionIds);
    }

    #[Test]
    public function validate_delegation_returns_errors_for_invalid_roles(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: true);
        $target = $this->createMockUserWithCreator(2, 1);

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $this->roleRepository
            ->shouldReceive('findById')
            ->with(999)
            ->andReturn(null);

        $errors = $this->service->validateDelegation($delegator, $target, [999], []);

        $this->assertArrayHasKey('role_999', $errors);
        $this->assertStringContainsString('not found', $errors['role_999']);
    }

    #[Test]
    public function validate_delegation_returns_errors_for_unassignable_roles(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: true);
        $target = $this->createMockUserWithCreator(2, 1);
        $role = $this->createMockRole(1, 'admin');

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $this->roleRepository
            ->shouldReceive('findById')
            ->with(1)
            ->andReturn($role);

        $this->delegationRepository
            ->shouldReceive('hasAssignableRole')
            ->with($delegator, $role)
            ->andReturn(false);

        $errors = $this->service->validateDelegation($delegator, $target, [1], []);

        $this->assertArrayHasKey('role_1', $errors);
        $this->assertStringContainsString('cannot assign', $errors['role_1']);
    }

    #[Test]
    public function validate_delegation_returns_empty_when_valid(): void
    {
        $delegator = $this->createMockUser(1, canManageUsers: true);
        $target = $this->createMockUserWithCreator(2, 1);
        $role = $this->createMockRole(1, 'editor');
        $permission = $this->createMockPermission(1, 'create-posts');

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([]));

        $this->roleRepository
            ->shouldReceive('findById')
            ->with(1)
            ->andReturn($role);

        $this->delegationRepository
            ->shouldReceive('hasAssignableRole')
            ->with($delegator, $role)
            ->andReturn(true);

        $this->permissionRepository
            ->shouldReceive('findById')
            ->with(1)
            ->andReturn($permission);

        $this->delegationRepository
            ->shouldReceive('hasAssignablePermission')
            ->with($delegator, $permission)
            ->andReturn(true);

        $errors = $this->service->validateDelegation($delegator, $target, [1], [1]);

        $this->assertEmpty($errors);
    }

    #[Test]
    public function super_admin_bypass_can_be_disabled(): void
    {
        $service = new DelegationService(
            delegationRepository: $this->delegationRepository,
            roleRepository: $this->roleRepository,
            permissionRepository: $this->permissionRepository,
            audit: $this->audit,
            superAdminBypassEnabled: false,
            superAdminIdentifier: 'super-admin'
        );

        $delegator = $this->createMockUser(1, canManageUsers: false);
        $role = $this->createMockRole(1, 'admin');
        $superAdminRole = $this->createMockRole(999, 'super-admin');

        $this->roleRepository
            ->shouldReceive('getUserRoles')
            ->with($delegator)
            ->andReturn(collect([$superAdminRole]));

        $result = $service->canAssignRole($delegator, $role);

        $this->assertFalse($result);
    }

    private function createMockUser(
        int $id,
        bool $canManageUsers = false,
        ?int $maxUsers = null
    ): DelegatableUserInterface {
        $user = Mockery::mock(DelegatableUserInterface::class);
        $user->shouldReceive('getDelegatableIdentifier')->andReturn($id);
        $user->shouldReceive('canManageUsers')->andReturn($canManageUsers);
        $user->shouldReceive('getMaxManageableUsers')->andReturn($maxUsers);

        return $user;
    }

    private function createMockUserWithCreator(int $id, ?int $creatorId): DelegatableUserInterface
    {
        $user = Mockery::mock(DelegatableUserInterface::class);
        $user->shouldReceive('getDelegatableIdentifier')->andReturn($id);
        $user->shouldReceive('canManageUsers')->andReturn(false);
        $user->shouldReceive('getMaxManageableUsers')->andReturn(null);

        if ($creatorId !== null) {
            $creator = Mockery::mock(DelegatableUserInterface::class);
            $creator->shouldReceive('getDelegatableIdentifier')->andReturn($creatorId);
            $user->creator = $creator;
        } else {
            $user->creator = null;
        }

        return $user;
    }

    private function createMockRole(int $id, string $name): RoleInterface
    {
        $role = Mockery::mock(RoleInterface::class);
        $role->shouldReceive('getRoleIdentifier')->andReturn($id);
        $role->shouldReceive('getRoleName')->andReturn($name);
        $role->shouldReceive('getRoleGuard')->andReturn('web');

        return $role;
    }

    private function createMockPermission(int $id, string $name): PermissionInterface
    {
        $permission = Mockery::mock(PermissionInterface::class);
        $permission->shouldReceive('getPermissionIdentifier')->andReturn($id);
        $permission->shouldReceive('getPermissionName')->andReturn($name);
        $permission->shouldReceive('getPermissionGuard')->andReturn('web');

        return $permission;
    }
}
