<?php

declare(strict_types=1);

use Ordain\Delegation\Domain\ValueObjects\DelegationScope;

describe('DelegationScope', function (): void {
    it('creates scope with no abilities by default', function (): void {
        $scope = new DelegationScope;

        expect($scope->canManageUsers)->toBeFalse()
            ->and($scope->maxManageableUsers)->toBeNull()
            ->and($scope->assignableRoleIds)->toBeEmpty()
            ->and($scope->assignablePermissionIds)->toBeEmpty();
    });

    it('creates scope with given values', function (): void {
        $scope = new DelegationScope(
            canManageUsers: true,
            maxManageableUsers: 10,
            assignableRoleIds: [1, 2, 3],
            assignablePermissionIds: [4, 5],
        );

        expect($scope->canManageUsers)->toBeTrue()
            ->and($scope->maxManageableUsers)->toBe(10)
            ->and($scope->assignableRoleIds)->toBe([1, 2, 3])
            ->and($scope->assignablePermissionIds)->toBe([4, 5]);
    });

    it('throws on negative max users', function (): void {
        new DelegationScope(maxManageableUsers: -1);
    })->throws(InvalidArgumentException::class, 'Max manageable users cannot be negative.');

    it('creates empty scope via none factory', function (): void {
        $scope = DelegationScope::none();

        expect($scope->canManageUsers)->toBeFalse()
            ->and($scope->maxManageableUsers)->toBeNull()
            ->and($scope->assignableRoleIds)->toBeEmpty();
    });

    it('creates unlimited scope via factory', function (): void {
        $scope = DelegationScope::unlimited([1, 2, 3], [4, 5]);

        expect($scope->canManageUsers)->toBeTrue()
            ->and($scope->maxManageableUsers)->toBeNull()
            ->and($scope->assignableRoleIds)->toBe([1, 2, 3])
            ->and($scope->assignablePermissionIds)->toBe([4, 5]);
    });

    it('creates limited scope via factory', function (): void {
        $scope = DelegationScope::limited(5, [1, 2]);

        expect($scope->canManageUsers)->toBeTrue()
            ->and($scope->maxManageableUsers)->toBe(5)
            ->and($scope->assignableRoleIds)->toBe([1, 2]);
    });

    it('checks if user management is allowed', function (): void {
        $allowed = DelegationScope::unlimited();
        $notAllowed = DelegationScope::none();

        expect($allowed->allowsUserManagement())->toBeTrue()
            ->and($notAllowed->allowsUserManagement())->toBeFalse();
    });

    it('checks if users are unlimited', function (): void {
        $unlimited = DelegationScope::unlimited();
        $limited = DelegationScope::limited(10);
        $none = DelegationScope::none();

        expect($unlimited->hasUnlimitedUsers())->toBeTrue()
            ->and($limited->hasUnlimitedUsers())->toBeFalse()
            ->and($none->hasUnlimitedUsers())->toBeFalse();
    });

    it('checks if role ID is assignable', function (): void {
        $scope = DelegationScope::unlimited([1, 2, 3]);

        expect($scope->canAssignRoleId(1))->toBeTrue()
            ->and($scope->canAssignRoleId(2))->toBeTrue()
            ->and($scope->canAssignRoleId(4))->toBeFalse();
    });

    it('checks if permission ID is assignable', function (): void {
        $scope = DelegationScope::unlimited([], [1, 2]);

        expect($scope->canAssignPermissionId(1))->toBeTrue()
            ->and($scope->canAssignPermissionId(3))->toBeFalse();
    });

    it('returns new instance on with methods', function (): void {
        $original = DelegationScope::none();
        $modified = $original->withUserManagement(true);

        expect($modified)->not->toBe($original)
            ->and($modified->canManageUsers)->toBeTrue()
            ->and($original->canManageUsers)->toBeFalse();
    });

    it('updates max users immutably', function (): void {
        $original = DelegationScope::unlimited();
        $modified = $original->withMaxUsers(10);

        expect($modified)->not->toBe($original)
            ->and($modified->maxManageableUsers)->toBe(10)
            ->and($original->maxManageableUsers)->toBeNull();
    });

    it('updates assignable roles immutably', function (): void {
        $original = DelegationScope::unlimited([1, 2]);
        $modified = $original->withAssignableRoles([3, 4, 5]);

        expect($modified)->not->toBe($original)
            ->and($modified->assignableRoleIds)->toBe([3, 4, 5])
            ->and($original->assignableRoleIds)->toBe([1, 2]);
    });

    it('updates assignable permissions immutably', function (): void {
        $original = DelegationScope::unlimited([], [1]);
        $modified = $original->withAssignablePermissions([2, 3]);

        expect($modified)->not->toBe($original)
            ->and($modified->assignablePermissionIds)->toBe([2, 3])
            ->and($original->assignablePermissionIds)->toBe([1]);
    });

    it('converts to array', function (): void {
        $scope = DelegationScope::limited(5, [1, 2], [3]);

        expect($scope->toArray())->toBe([
            'can_manage_users' => true,
            'max_manageable_users' => 5,
            'assignable_role_ids' => [1, 2],
            'assignable_permission_ids' => [3],
        ]);
    });

    it('creates from array', function (): void {
        $data = [
            'can_manage_users' => true,
            'max_manageable_users' => 10,
            'assignable_role_ids' => [1, 2],
            'assignable_permission_ids' => [3, 4],
        ];

        $scope = DelegationScope::fromArray($data);

        expect($scope->canManageUsers)->toBeTrue()
            ->and($scope->maxManageableUsers)->toBe(10)
            ->and($scope->assignableRoleIds)->toBe([1, 2])
            ->and($scope->assignablePermissionIds)->toBe([3, 4]);
    });

    it('checks equality correctly', function (): void {
        $scope1 = DelegationScope::limited(5, [1, 2], [3]);
        $scope2 = DelegationScope::limited(5, [1, 2], [3]);
        $scope3 = DelegationScope::limited(10, [1, 2], [3]);

        expect($scope1->equals($scope2))->toBeTrue()
            ->and($scope1->equals($scope3))->toBeFalse();
    });

    it('throws exception for invalid role ID type', function (): void {
        new DelegationScope(
            assignableRoleIds: [1, 2, ['invalid']],
        );
    })->throws(InvalidArgumentException::class, 'Assignable role IDs must be integers or strings.');

    it('throws exception for invalid permission ID type', function (): void {
        new DelegationScope(
            assignablePermissionIds: [1, new stdClass],
        );
    })->throws(InvalidArgumentException::class, 'Assignable permission IDs must be integers or strings.');

    it('filters out empty strings from role IDs', function (): void {
        $scope = new DelegationScope(
            assignableRoleIds: [1, '', 2, '', 3],
        );

        expect($scope->assignableRoleIds)->toBe([1, 2, 3]);
    });

    it('filters out empty strings from permission IDs', function (): void {
        $scope = new DelegationScope(
            assignablePermissionIds: ['read', '', 'write'],
        );

        expect($scope->assignablePermissionIds)->toBe(['read', 'write']);
    });

    it('removes duplicate role IDs', function (): void {
        $scope = new DelegationScope(
            assignableRoleIds: [1, 2, 1, 3, 2, 3],
        );

        expect($scope->assignableRoleIds)->toBe([1, 2, 3]);
    });

    it('removes duplicate permission IDs', function (): void {
        $scope = new DelegationScope(
            assignablePermissionIds: ['read', 'write', 'read', 'delete'],
        );

        expect($scope->assignablePermissionIds)->toBe(['read', 'write', 'delete']);
    });

    it('treats string and integer IDs as different', function (): void {
        $scope = new DelegationScope(
            assignableRoleIds: [1, '1', 2, '2'],
        );

        expect($scope->assignableRoleIds)->toBe([1, '1', 2, '2']);
    });
});
