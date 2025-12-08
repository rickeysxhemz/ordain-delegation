<?php

declare(strict_types=1);

namespace Ordain\Delegation\Tests\Unit;

use InvalidArgumentException;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DelegationScopeTest extends TestCase
{
    #[Test]
    public function it_creates_default_scope_with_no_abilities(): void
    {
        $scope = new DelegationScope;

        $this->assertFalse($scope->canManageUsers);
        $this->assertNull($scope->maxManageableUsers);
        $this->assertEmpty($scope->assignableRoleIds);
        $this->assertEmpty($scope->assignablePermissionIds);
    }

    #[Test]
    public function it_creates_scope_with_all_properties(): void
    {
        $scope = new DelegationScope(
            canManageUsers: true,
            maxManageableUsers: 10,
            assignableRoleIds: [1, 2, 3],
            assignablePermissionIds: [4, 5, 6],
        );

        $this->assertTrue($scope->canManageUsers);
        $this->assertSame(10, $scope->maxManageableUsers);
        $this->assertSame([1, 2, 3], $scope->assignableRoleIds);
        $this->assertSame([4, 5, 6], $scope->assignablePermissionIds);
    }

    #[Test]
    public function it_throws_exception_for_negative_max_users(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max manageable users cannot be negative.');

        new DelegationScope(maxManageableUsers: -1);
    }

    #[Test]
    public function none_factory_creates_empty_scope(): void
    {
        $scope = DelegationScope::none();

        $this->assertFalse($scope->canManageUsers);
        $this->assertNull($scope->maxManageableUsers);
        $this->assertEmpty($scope->assignableRoleIds);
        $this->assertEmpty($scope->assignablePermissionIds);
    }

    #[Test]
    public function unlimited_factory_creates_unlimited_scope(): void
    {
        $scope = DelegationScope::unlimited([1, 2], [3, 4]);

        $this->assertTrue($scope->canManageUsers);
        $this->assertNull($scope->maxManageableUsers);
        $this->assertSame([1, 2], $scope->assignableRoleIds);
        $this->assertSame([3, 4], $scope->assignablePermissionIds);
    }

    #[Test]
    public function limited_factory_creates_limited_scope(): void
    {
        $scope = DelegationScope::limited(5, [1, 2], [3, 4]);

        $this->assertTrue($scope->canManageUsers);
        $this->assertSame(5, $scope->maxManageableUsers);
        $this->assertSame([1, 2], $scope->assignableRoleIds);
        $this->assertSame([3, 4], $scope->assignablePermissionIds);
    }

    #[Test]
    public function allows_user_management_returns_correct_value(): void
    {
        $scopeEnabled = new DelegationScope(canManageUsers: true);
        $scopeDisabled = new DelegationScope(canManageUsers: false);

        $this->assertTrue($scopeEnabled->allowsUserManagement());
        $this->assertFalse($scopeDisabled->allowsUserManagement());
    }

    #[Test]
    public function has_unlimited_users_returns_correct_value(): void
    {
        $unlimited = new DelegationScope(canManageUsers: true, maxManageableUsers: null);
        $limited = new DelegationScope(canManageUsers: true, maxManageableUsers: 10);
        $disabled = new DelegationScope(canManageUsers: false, maxManageableUsers: null);

        $this->assertTrue($unlimited->hasUnlimitedUsers());
        $this->assertFalse($limited->hasUnlimitedUsers());
        $this->assertFalse($disabled->hasUnlimitedUsers());
    }

    #[Test]
    public function can_assign_role_id_checks_correctly(): void
    {
        $scope = new DelegationScope(assignableRoleIds: [1, 2, 3]);

        $this->assertTrue($scope->canAssignRoleId(1));
        $this->assertTrue($scope->canAssignRoleId(2));
        $this->assertTrue($scope->canAssignRoleId(3));
        $this->assertFalse($scope->canAssignRoleId(4));
        $this->assertFalse($scope->canAssignRoleId(999));
    }

    #[Test]
    public function can_assign_permission_id_checks_correctly(): void
    {
        $scope = new DelegationScope(assignablePermissionIds: [10, 20, 30]);

        $this->assertTrue($scope->canAssignPermissionId(10));
        $this->assertTrue($scope->canAssignPermissionId(20));
        $this->assertTrue($scope->canAssignPermissionId(30));
        $this->assertFalse($scope->canAssignPermissionId(40));
        $this->assertFalse($scope->canAssignPermissionId(999));
    }

    #[Test]
    public function can_assign_role_id_works_with_string_ids(): void
    {
        $scope = new DelegationScope(assignableRoleIds: ['admin', 'editor', 'viewer']);

        $this->assertTrue($scope->canAssignRoleId('admin'));
        $this->assertTrue($scope->canAssignRoleId('editor'));
        $this->assertFalse($scope->canAssignRoleId('superadmin'));
    }

    #[Test]
    public function with_user_management_creates_new_scope(): void
    {
        $original = new DelegationScope(
            canManageUsers: false,
            maxManageableUsers: 10,
            assignableRoleIds: [1],
            assignablePermissionIds: [2],
        );

        $modified = $original->withUserManagement(true);

        $this->assertFalse($original->canManageUsers);
        $this->assertTrue($modified->canManageUsers);
        $this->assertSame(10, $modified->maxManageableUsers);
        $this->assertSame([1], $modified->assignableRoleIds);
        $this->assertSame([2], $modified->assignablePermissionIds);
    }

    #[Test]
    public function with_max_users_creates_new_scope(): void
    {
        $original = new DelegationScope(
            canManageUsers: true,
            maxManageableUsers: 10,
            assignableRoleIds: [1],
            assignablePermissionIds: [2],
        );

        $modified = $original->withMaxUsers(20);

        $this->assertSame(10, $original->maxManageableUsers);
        $this->assertSame(20, $modified->maxManageableUsers);
        $this->assertTrue($modified->canManageUsers);
    }

    #[Test]
    public function with_assignable_roles_creates_new_scope(): void
    {
        $original = new DelegationScope(assignableRoleIds: [1, 2]);
        $modified = $original->withAssignableRoles([3, 4, 5]);

        $this->assertSame([1, 2], $original->assignableRoleIds);
        $this->assertSame([3, 4, 5], $modified->assignableRoleIds);
    }

    #[Test]
    public function with_assignable_permissions_creates_new_scope(): void
    {
        $original = new DelegationScope(assignablePermissionIds: [1, 2]);
        $modified = $original->withAssignablePermissions([3, 4, 5]);

        $this->assertSame([1, 2], $original->assignablePermissionIds);
        $this->assertSame([3, 4, 5], $modified->assignablePermissionIds);
    }

    #[Test]
    public function to_array_returns_correct_structure(): void
    {
        $scope = new DelegationScope(
            canManageUsers: true,
            maxManageableUsers: 15,
            assignableRoleIds: [1, 2],
            assignablePermissionIds: [3, 4],
        );

        $array = $scope->toArray();

        $this->assertSame([
            'can_manage_users' => true,
            'max_manageable_users' => 15,
            'assignable_role_ids' => [1, 2],
            'assignable_permission_ids' => [3, 4],
        ], $array);
    }

    #[Test]
    public function from_array_creates_correct_scope(): void
    {
        $data = [
            'can_manage_users' => true,
            'max_manageable_users' => 15,
            'assignable_role_ids' => [1, 2],
            'assignable_permission_ids' => [3, 4],
        ];

        $scope = DelegationScope::fromArray($data);

        $this->assertTrue($scope->canManageUsers);
        $this->assertSame(15, $scope->maxManageableUsers);
        $this->assertSame([1, 2], $scope->assignableRoleIds);
        $this->assertSame([3, 4], $scope->assignablePermissionIds);
    }

    #[Test]
    public function from_array_handles_missing_keys(): void
    {
        $scope = DelegationScope::fromArray([]);

        $this->assertFalse($scope->canManageUsers);
        $this->assertNull($scope->maxManageableUsers);
        $this->assertEmpty($scope->assignableRoleIds);
        $this->assertEmpty($scope->assignablePermissionIds);
    }

    #[Test]
    public function equals_returns_true_for_same_values(): void
    {
        $scope1 = new DelegationScope(
            canManageUsers: true,
            maxManageableUsers: 10,
            assignableRoleIds: [1, 2],
            assignablePermissionIds: [3, 4],
        );

        $scope2 = new DelegationScope(
            canManageUsers: true,
            maxManageableUsers: 10,
            assignableRoleIds: [1, 2],
            assignablePermissionIds: [3, 4],
        );

        $this->assertTrue($scope1->equals($scope2));
    }

    #[Test]
    public function equals_returns_false_for_different_values(): void
    {
        $scope1 = new DelegationScope(canManageUsers: true, maxManageableUsers: 10);
        $scope2 = new DelegationScope(canManageUsers: true, maxManageableUsers: 20);
        $scope3 = new DelegationScope(canManageUsers: false, maxManageableUsers: 10);

        $this->assertFalse($scope1->equals($scope2));
        $this->assertFalse($scope1->equals($scope3));
    }

    #[Test]
    public function scope_is_immutable(): void
    {
        $scope = new DelegationScope(
            canManageUsers: true,
            maxManageableUsers: 10,
            assignableRoleIds: [1, 2],
            assignablePermissionIds: [3, 4],
        );

        $scope->withMaxUsers(20);
        $scope->withUserManagement(false);
        $scope->withAssignableRoles([5, 6]);
        $scope->withAssignablePermissions([7, 8]);

        $this->assertTrue($scope->canManageUsers);
        $this->assertSame(10, $scope->maxManageableUsers);
        $this->assertSame([1, 2], $scope->assignableRoleIds);
        $this->assertSame([3, 4], $scope->assignablePermissionIds);
    }
}
