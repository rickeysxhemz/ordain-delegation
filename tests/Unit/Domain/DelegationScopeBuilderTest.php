<?php

declare(strict_types=1);

use Ordain\Delegation\Domain\Builders\DelegationScopeBuilder;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;

describe('DelegationScopeBuilder', function (): void {
    describe('create', function (): void {
        it('creates a new builder instance', function (): void {
            $builder = DelegationScopeBuilder::create();

            expect($builder)->toBeInstanceOf(DelegationScopeBuilder::class);
        });

        it('builds scope with default values', function (): void {
            $scope = DelegationScopeBuilder::create()->build();

            expect($scope->canManageUsers)->toBeFalse()
                ->and($scope->maxManageableUsers)->toBeNull()
                ->and($scope->assignableRoleIds)->toBe([])
                ->and($scope->assignablePermissionIds)->toBe([]);
        });
    });

    describe('from', function (): void {
        it('creates builder from existing scope', function (): void {
            $original = new DelegationScope(
                canManageUsers: true,
                maxManageableUsers: 5,
                assignableRoleIds: [1, 2],
                assignablePermissionIds: [3, 4],
            );

            $scope = DelegationScopeBuilder::from($original)->build();

            expect($scope->canManageUsers)->toBeTrue()
                ->and($scope->maxManageableUsers)->toBe(5)
                ->and($scope->assignableRoleIds)->toBe([1, 2])
                ->and($scope->assignablePermissionIds)->toBe([3, 4]);
        });
    });

    describe('userManagement', function (): void {
        it('enables user management', function (): void {
            $scope = DelegationScopeBuilder::create()
                ->userManagement(true)
                ->build();

            expect($scope->canManageUsers)->toBeTrue();
        });

        it('disables user management', function (): void {
            $scope = DelegationScopeBuilder::create()
                ->userManagement(true)
                ->userManagement(false)
                ->build();

            expect($scope->canManageUsers)->toBeFalse();
        });
    });

    describe('allowUserManagement', function (): void {
        it('enables user management', function (): void {
            $scope = DelegationScopeBuilder::create()
                ->allowUserManagement()
                ->build();

            expect($scope->canManageUsers)->toBeTrue();
        });
    });

    describe('denyUserManagement', function (): void {
        it('disables user management', function (): void {
            $scope = DelegationScopeBuilder::create()
                ->allowUserManagement()
                ->denyUserManagement()
                ->build();

            expect($scope->canManageUsers)->toBeFalse();
        });
    });

    describe('maxUsers', function (): void {
        it('sets max users limit', function (): void {
            $scope = DelegationScopeBuilder::create()
                ->maxUsers(10)
                ->build();

            expect($scope->maxManageableUsers)->toBe(10);
        });

        it('sets null for unlimited', function (): void {
            $scope = DelegationScopeBuilder::create()
                ->maxUsers(10)
                ->maxUsers(null)
                ->build();

            expect($scope->maxManageableUsers)->toBeNull();
        });
    });

    describe('unlimited', function (): void {
        it('enables unlimited user management', function (): void {
            $scope = DelegationScopeBuilder::create()
                ->unlimited()
                ->build();

            expect($scope->canManageUsers)->toBeTrue()
                ->and($scope->maxManageableUsers)->toBeNull();
        });
    });

    describe('limited', function (): void {
        it('enables limited user management', function (): void {
            $scope = DelegationScopeBuilder::create()
                ->limited(25)
                ->build();

            expect($scope->canManageUsers)->toBeTrue()
                ->and($scope->maxManageableUsers)->toBe(25);
        });
    });

    describe('withRoles', function (): void {
        it('sets assignable role IDs', function (): void {
            $scope = DelegationScopeBuilder::create()
                ->withRoles([1, 2, 3])
                ->build();

            expect($scope->assignableRoleIds)->toBe([1, 2, 3]);
        });

        it('replaces existing roles', function (): void {
            $scope = DelegationScopeBuilder::create()
                ->withRoles([1, 2])
                ->withRoles([3, 4])
                ->build();

            expect($scope->assignableRoleIds)->toBe([3, 4]);
        });
    });

    describe('addRoles', function (): void {
        it('adds role IDs from array', function (): void {
            $scope = DelegationScopeBuilder::create()
                ->withRoles([1])
                ->addRoles([2, 3])
                ->build();

            expect($scope->assignableRoleIds)->toBe([1, 2, 3]);
        });

        it('adds single role ID', function (): void {
            $scope = DelegationScopeBuilder::create()
                ->addRoles(1)
                ->build();

            expect($scope->assignableRoleIds)->toBe([1]);
        });
    });

    describe('addRole', function (): void {
        it('adds a single role', function (): void {
            $scope = DelegationScopeBuilder::create()
                ->addRole(1)
                ->addRole(2)
                ->build();

            expect($scope->assignableRoleIds)->toBe([1, 2]);
        });

        it('accepts string role IDs', function (): void {
            $scope = DelegationScopeBuilder::create()
                ->addRole('admin')
                ->addRole('editor')
                ->build();

            expect($scope->assignableRoleIds)->toBe(['admin', 'editor']);
        });
    });

    describe('withPermissions', function (): void {
        it('sets assignable permission IDs', function (): void {
            $scope = DelegationScopeBuilder::create()
                ->withPermissions([10, 20, 30])
                ->build();

            expect($scope->assignablePermissionIds)->toBe([10, 20, 30]);
        });

        it('replaces existing permissions', function (): void {
            $scope = DelegationScopeBuilder::create()
                ->withPermissions([10])
                ->withPermissions([20])
                ->build();

            expect($scope->assignablePermissionIds)->toBe([20]);
        });
    });

    describe('addPermissions', function (): void {
        it('adds permission IDs from array', function (): void {
            $scope = DelegationScopeBuilder::create()
                ->withPermissions([10])
                ->addPermissions([20, 30])
                ->build();

            expect($scope->assignablePermissionIds)->toBe([10, 20, 30]);
        });

        it('adds single permission ID', function (): void {
            $scope = DelegationScopeBuilder::create()
                ->addPermissions(10)
                ->build();

            expect($scope->assignablePermissionIds)->toBe([10]);
        });
    });

    describe('addPermission', function (): void {
        it('adds a single permission', function (): void {
            $scope = DelegationScopeBuilder::create()
                ->addPermission(10)
                ->addPermission(20)
                ->build();

            expect($scope->assignablePermissionIds)->toBe([10, 20]);
        });

        it('accepts string permission IDs', function (): void {
            $scope = DelegationScopeBuilder::create()
                ->addPermission('create-posts')
                ->addPermission('edit-posts')
                ->build();

            expect($scope->assignablePermissionIds)->toBe(['create-posts', 'edit-posts']);
        });
    });

    describe('fluent chaining', function (): void {
        it('supports full fluent interface', function (): void {
            $scope = DelegationScopeBuilder::create()
                ->allowUserManagement()
                ->maxUsers(10)
                ->withRoles([1, 2])
                ->addRole(3)
                ->withPermissions([10])
                ->addPermission(20)
                ->build();

            expect($scope->canManageUsers)->toBeTrue()
                ->and($scope->maxManageableUsers)->toBe(10)
                ->and($scope->assignableRoleIds)->toBe([1, 2, 3])
                ->and($scope->assignablePermissionIds)->toBe([10, 20]);
        });
    });

    describe('DelegationScope::builder', function (): void {
        it('provides builder via static method', function (): void {
            $scope = DelegationScope::builder()
                ->unlimited()
                ->withRoles([1, 2, 3])
                ->build();

            expect($scope->canManageUsers)->toBeTrue()
                ->and($scope->assignableRoleIds)->toBe([1, 2, 3]);
        });
    });
});
