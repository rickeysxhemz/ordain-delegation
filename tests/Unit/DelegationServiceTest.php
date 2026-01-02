<?php

declare(strict_types=1);

namespace Ordain\Delegation\Tests\Unit;

use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationAuditInterface;
use Ordain\Delegation\Contracts\DelegationAuthorizerInterface;
use Ordain\Delegation\Contracts\DelegationValidatorInterface;
use Ordain\Delegation\Contracts\EventDispatcherInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\QuotaManagerInterface;
use Ordain\Delegation\Contracts\Repositories\DelegationRepositoryInterface;
use Ordain\Delegation\Contracts\Repositories\PermissionRepositoryInterface;
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Contracts\RootAdminResolverInterface;
use Ordain\Delegation\Contracts\TransactionManagerInterface;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;
use Ordain\Delegation\Exceptions\UnauthorizedDelegationException;
use Ordain\Delegation\Services\DelegationService;

beforeEach(function (): void {
    $this->authorizer = Mockery::mock(DelegationAuthorizerInterface::class);
    $this->quotaManager = Mockery::mock(QuotaManagerInterface::class);
    $this->validator = Mockery::mock(DelegationValidatorInterface::class);
    $this->rootAdminResolver = Mockery::mock(RootAdminResolverInterface::class);
    $this->delegationRepository = Mockery::mock(DelegationRepositoryInterface::class);
    $this->roleRepository = Mockery::mock(RoleRepositoryInterface::class);
    $this->permissionRepository = Mockery::mock(PermissionRepositoryInterface::class);
    $this->transactionManager = Mockery::mock(TransactionManagerInterface::class);
    $this->eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
    $this->audit = Mockery::mock(DelegationAuditInterface::class);

    $this->service = new DelegationService(
        authorizer: $this->authorizer,
        quotaManager: $this->quotaManager,
        validator: $this->validator,
        rootAdminResolver: $this->rootAdminResolver,
        delegationRepository: $this->delegationRepository,
        roleRepository: $this->roleRepository,
        permissionRepository: $this->permissionRepository,
        transactionManager: $this->transactionManager,
        eventDispatcher: $this->eventDispatcher,
        audit: $this->audit,
    );
});

function createMockUser(int $id, bool $canManageUsers = true, ?int $maxUsers = null): MockInterface&DelegatableUserInterface
{
    $user = Mockery::mock(DelegatableUserInterface::class);
    $user->shouldReceive('getDelegatableIdentifier')->andReturn($id);
    $user->shouldReceive('canManageUsers')->andReturn($canManageUsers);
    $user->shouldReceive('getMaxManageableUsers')->andReturn($maxUsers);
    $user->shouldReceive('getCreator')->andReturn(null);

    return $user;
}

function createMockUserWithCreator(int $id, int $creatorId): MockInterface&DelegatableUserInterface
{
    $creator = Mockery::mock(DelegatableUserInterface::class);
    $creator->shouldReceive('getDelegatableIdentifier')->andReturn($creatorId);

    $user = Mockery::mock(DelegatableUserInterface::class);
    $user->shouldReceive('getDelegatableIdentifier')->andReturn($id);
    $user->shouldReceive('canManageUsers')->andReturn(true);
    $user->shouldReceive('getMaxManageableUsers')->andReturn(null);
    $user->shouldReceive('getCreator')->andReturn($creator);

    return $user;
}

function createMockRole(int $id, string $name): MockInterface&RoleInterface
{
    $role = Mockery::mock(RoleInterface::class);
    $role->shouldReceive('getRoleIdentifier')->andReturn($id);
    $role->shouldReceive('getRoleName')->andReturn($name);
    $role->shouldReceive('getRoleGuard')->andReturn('web');

    return $role;
}

function createMockPermission(int $id, string $name): MockInterface&PermissionInterface
{
    $permission = Mockery::mock(PermissionInterface::class);
    $permission->shouldReceive('getPermissionIdentifier')->andReturn($id);
    $permission->shouldReceive('getPermissionName')->andReturn($name);
    $permission->shouldReceive('getPermissionGuard')->andReturn('web');

    return $permission;
}

describe('DelegationService', function (): void {
    describe('canAssignRole', function (): void {
        it('delegates to authorizer', function (): void {
            $delegator = createMockUser(1);
            $role = createMockRole(1, 'admin');

            $this->authorizer->shouldReceive('canAssignRole')
                ->once()
                ->with($delegator, $role, null)
                ->andReturn(true);

            $result = $this->service->canAssignRole($delegator, $role);

            expect($result)->toBeTrue();
        });

        it('passes target to authorizer when provided', function (): void {
            $delegator = createMockUser(1);
            $target = createMockUser(2);
            $role = createMockRole(1, 'admin');

            $this->authorizer->shouldReceive('canAssignRole')
                ->once()
                ->with($delegator, $role, $target)
                ->andReturn(false);

            $result = $this->service->canAssignRole($delegator, $role, $target);

            expect($result)->toBeFalse();
        });
    });

    describe('canAssignPermission', function (): void {
        it('delegates to authorizer', function (): void {
            $delegator = createMockUser(1);
            $permission = createMockPermission(1, 'create-posts');

            $this->authorizer->shouldReceive('canAssignPermission')
                ->once()
                ->with($delegator, $permission, null)
                ->andReturn(true);

            $result = $this->service->canAssignPermission($delegator, $permission);

            expect($result)->toBeTrue();
        });
    });

    describe('canRevokeRole', function (): void {
        it('delegates to authorizer', function (): void {
            $delegator = createMockUser(1);
            $target = createMockUser(2);
            $role = createMockRole(1, 'admin');

            $this->authorizer->shouldReceive('canRevokeRole')
                ->once()
                ->with($delegator, $role, $target)
                ->andReturn(true);

            $result = $this->service->canRevokeRole($delegator, $role, $target);

            expect($result)->toBeTrue();
        });
    });

    describe('canRevokePermission', function (): void {
        it('delegates to authorizer', function (): void {
            $delegator = createMockUser(1);
            $target = createMockUser(2);
            $permission = createMockPermission(1, 'edit-posts');

            $this->authorizer->shouldReceive('canRevokePermission')
                ->once()
                ->with($delegator, $permission, $target)
                ->andReturn(false);

            $result = $this->service->canRevokePermission($delegator, $permission, $target);

            expect($result)->toBeFalse();
        });
    });

    describe('canCreateUsers', function (): void {
        it('delegates to quota manager', function (): void {
            $delegator = createMockUser(1);

            $this->quotaManager->shouldReceive('canCreateUsers')
                ->once()
                ->with($delegator)
                ->andReturn(true);

            $result = $this->service->canCreateUsers($delegator);

            expect($result)->toBeTrue();
        });
    });

    describe('hasReachedUserLimit', function (): void {
        it('delegates to quota manager', function (): void {
            $delegator = createMockUser(1);

            $this->quotaManager->shouldReceive('hasReachedLimit')
                ->once()
                ->with($delegator)
                ->andReturn(false);

            $result = $this->service->hasReachedUserLimit($delegator);

            expect($result)->toBeFalse();
        });
    });

    describe('getCreatedUsersCount', function (): void {
        it('delegates to quota manager', function (): void {
            $delegator = createMockUser(1);

            $this->quotaManager->shouldReceive('getCreatedUsersCount')
                ->once()
                ->with($delegator)
                ->andReturn(5);

            $result = $this->service->getCreatedUsersCount($delegator);

            expect($result)->toBe(5);
        });
    });

    describe('getRemainingUserQuota', function (): void {
        it('delegates to quota manager', function (): void {
            $delegator = createMockUser(1);

            $this->quotaManager->shouldReceive('getRemainingQuota')
                ->once()
                ->with($delegator)
                ->andReturn(10);

            $result = $this->service->getRemainingUserQuota($delegator);

            expect($result)->toBe(10);
        });

        it('returns null for unlimited quota', function (): void {
            $delegator = createMockUser(1);

            $this->quotaManager->shouldReceive('getRemainingQuota')
                ->once()
                ->with($delegator)
                ->andReturn(null);

            $result = $this->service->getRemainingUserQuota($delegator);

            expect($result)->toBeNull();
        });
    });

    describe('withQuotaLock', function (): void {
        it('delegates to quota manager', function (): void {
            $delegator = createMockUser(1);
            $newUser = createMockUser(2);
            $callback = fn () => $newUser;

            $this->quotaManager->shouldReceive('withLock')
                ->once()
                ->with($delegator, $callback)
                ->andReturn($newUser);

            $result = $this->service->withQuotaLock($delegator, $callback);

            expect($result)->toBe($newUser);
        });
    });

    describe('getAssignableRoles', function (): void {
        it('returns all roles for super admin', function (): void {
            $delegator = createMockUser(1);
            $roles = new Collection([createMockRole(1, 'admin')]);

            $this->rootAdminResolver->shouldReceive('isRootAdmin')
                ->with($delegator)
                ->andReturn(true);

            $this->roleRepository->shouldReceive('all')
                ->once()
                ->andReturn($roles);

            $result = $this->service->getAssignableRoles($delegator);

            expect($result)->toBe($roles);
        });

        it('returns delegated roles for non-super-admin', function (): void {
            $delegator = createMockUser(1);
            $roles = new Collection([createMockRole(1, 'editor')]);

            $this->rootAdminResolver->shouldReceive('isRootAdmin')
                ->with($delegator)
                ->andReturn(false);

            $this->delegationRepository->shouldReceive('getAssignableRoles')
                ->once()
                ->with($delegator)
                ->andReturn($roles);

            $result = $this->service->getAssignableRoles($delegator);

            expect($result)->toBe($roles);
        });
    });

    describe('getAssignablePermissions', function (): void {
        it('returns all permissions for super admin', function (): void {
            $delegator = createMockUser(1);
            $permissions = new Collection([createMockPermission(1, 'create-posts')]);

            $this->rootAdminResolver->shouldReceive('isRootAdmin')
                ->with($delegator)
                ->andReturn(true);

            $this->permissionRepository->shouldReceive('all')
                ->once()
                ->andReturn($permissions);

            $result = $this->service->getAssignablePermissions($delegator);

            expect($result)->toBe($permissions);
        });

        it('returns delegated permissions for non-super-admin', function (): void {
            $delegator = createMockUser(1);
            $permissions = new Collection([createMockPermission(1, 'edit-posts')]);

            $this->rootAdminResolver->shouldReceive('isRootAdmin')
                ->with($delegator)
                ->andReturn(false);

            $this->delegationRepository->shouldReceive('getAssignablePermissions')
                ->once()
                ->with($delegator)
                ->andReturn($permissions);

            $result = $this->service->getAssignablePermissions($delegator);

            expect($result)->toBe($permissions);
        });
    });

    describe('delegateRole', function (): void {
        it('assigns role when authorized', function (): void {
            $delegator = createMockUser(1);
            $target = createMockUser(2);
            $role = createMockRole(1, 'editor');

            $this->authorizer->shouldReceive('canAssignRole')
                ->with($delegator, $role, $target)
                ->andReturn(true);

            $this->roleRepository->shouldReceive('assignToUser')
                ->once()
                ->with($target, $role);

            $this->audit->shouldReceive('logRoleAssigned')
                ->once()
                ->with($delegator, $target, $role);

            $this->eventDispatcher->shouldReceive('dispatch')
                ->once();

            $this->service->delegateRole($delegator, $target, $role);
        });

        it('throws exception when not authorized', function (): void {
            $delegator = createMockUser(1);
            $target = createMockUser(2);
            $role = createMockRole(1, 'admin');

            $this->authorizer->shouldReceive('canAssignRole')
                ->with($delegator, $role, $target)
                ->andReturn(false);

            $this->audit->shouldReceive('logUnauthorizedAttempt')
                ->once();

            $this->service->delegateRole($delegator, $target, $role);
        })->throws(UnauthorizedDelegationException::class, "User is not authorized to assign role 'admin'.");
    });

    describe('delegatePermission', function (): void {
        it('grants permission when authorized', function (): void {
            $delegator = createMockUser(1);
            $target = createMockUser(2);
            $permission = createMockPermission(1, 'create-posts');

            $this->authorizer->shouldReceive('canAssignPermission')
                ->with($delegator, $permission, $target)
                ->andReturn(true);

            $this->permissionRepository->shouldReceive('assignToUser')
                ->once()
                ->with($target, $permission);

            $this->audit->shouldReceive('logPermissionGranted')
                ->once()
                ->with($delegator, $target, $permission);

            $this->eventDispatcher->shouldReceive('dispatch')
                ->once();

            $this->service->delegatePermission($delegator, $target, $permission);
        });

        it('throws exception when not authorized', function (): void {
            $delegator = createMockUser(1);
            $target = createMockUser(2);
            $permission = createMockPermission(1, 'delete-posts');

            $this->authorizer->shouldReceive('canAssignPermission')
                ->with($delegator, $permission, $target)
                ->andReturn(false);

            $this->audit->shouldReceive('logUnauthorizedAttempt')
                ->once();

            $this->service->delegatePermission($delegator, $target, $permission);
        })->throws(UnauthorizedDelegationException::class, "User is not authorized to grant permission 'delete-posts'.");
    });

    describe('revokeRole', function (): void {
        it('revokes role when authorized', function (): void {
            $delegator = createMockUser(1);
            $target = createMockUser(2);
            $role = createMockRole(1, 'editor');

            $this->authorizer->shouldReceive('canRevokeRole')
                ->with($delegator, $role, $target)
                ->andReturn(true);

            $this->roleRepository->shouldReceive('removeFromUser')
                ->once()
                ->with($target, $role);

            $this->audit->shouldReceive('logRoleRevoked')
                ->once()
                ->with($delegator, $target, $role);

            $this->eventDispatcher->shouldReceive('dispatch')
                ->once();

            $this->service->revokeRole($delegator, $target, $role);
        });

        it('throws exception when not authorized', function (): void {
            $delegator = createMockUser(1);
            $target = createMockUser(2);
            $role = createMockRole(1, 'admin');

            $this->authorizer->shouldReceive('canRevokeRole')
                ->with($delegator, $role, $target)
                ->andReturn(false);

            $this->audit->shouldReceive('logUnauthorizedAttempt')
                ->once();

            $this->service->revokeRole($delegator, $target, $role);
        })->throws(UnauthorizedDelegationException::class, "User is not authorized to revoke role 'admin'.");
    });

    describe('revokePermission', function (): void {
        it('revokes permission when authorized', function (): void {
            $delegator = createMockUser(1);
            $target = createMockUser(2);
            $permission = createMockPermission(1, 'edit-posts');

            $this->authorizer->shouldReceive('canRevokePermission')
                ->with($delegator, $permission, $target)
                ->andReturn(true);

            $this->permissionRepository->shouldReceive('removeFromUser')
                ->once()
                ->with($target, $permission);

            $this->audit->shouldReceive('logPermissionRevoked')
                ->once()
                ->with($delegator, $target, $permission);

            $this->eventDispatcher->shouldReceive('dispatch')
                ->once();

            $this->service->revokePermission($delegator, $target, $permission);
        });

        it('throws exception when not authorized', function (): void {
            $delegator = createMockUser(1);
            $target = createMockUser(2);
            $permission = createMockPermission(1, 'delete-posts');

            $this->authorizer->shouldReceive('canRevokePermission')
                ->with($delegator, $permission, $target)
                ->andReturn(false);

            $this->audit->shouldReceive('logUnauthorizedAttempt')
                ->once();

            $this->service->revokePermission($delegator, $target, $permission);
        })->throws(UnauthorizedDelegationException::class, "User is not authorized to revoke permission 'delete-posts'.");
    });

    describe('canManageUser', function (): void {
        it('delegates to authorizer', function (): void {
            $delegator = createMockUser(1);
            $target = createMockUser(2);

            $this->authorizer->shouldReceive('canManageUser')
                ->once()
                ->with($delegator, $target)
                ->andReturn(true);

            $result = $this->service->canManageUser($delegator, $target);

            expect($result)->toBeTrue();
        });
    });

    describe('validateDelegation', function (): void {
        it('delegates to validator', function (): void {
            $delegator = createMockUser(1);
            $target = createMockUser(2);
            $roleIds = [1, 2];
            $permissionIds = [3, 4];
            $errors = ['role_1' => 'Cannot assign role'];

            $this->validator->shouldReceive('validate')
                ->once()
                ->with($delegator, $target, $roleIds, $permissionIds)
                ->andReturn($errors);

            $result = $this->service->validateDelegation($delegator, $target, $roleIds, $permissionIds);

            expect($result)->toBe($errors);
        });
    });

    describe('getDelegationScope', function (): void {
        it('returns delegation scope for user', function (): void {
            $user = createMockUser(1, canManageUsers: true, maxUsers: 10);
            $roles = new Collection([createMockRole(1, 'editor')]);
            $permissions = new Collection([createMockPermission(1, 'create-posts')]);

            $this->delegationRepository->shouldReceive('getAssignableRoles')
                ->with($user)
                ->andReturn($roles);

            $this->delegationRepository->shouldReceive('getAssignablePermissions')
                ->with($user)
                ->andReturn($permissions);

            $scope = $this->service->getDelegationScope($user);

            expect($scope->canManageUsers)->toBeTrue();
            expect($scope->maxManageableUsers)->toBe(10);
            expect($scope->assignableRoleIds)->toBe([1]);
            expect($scope->assignablePermissionIds)->toBe([1]);
        });
    });

    describe('setDelegationScope', function (): void {
        it('updates scope and dispatches event when changed', function (): void {
            $user = createMockUser(1, canManageUsers: false, maxUsers: null);
            $admin = createMockUser(99);
            $newScope = new DelegationScope(
                canManageUsers: true,
                maxManageableUsers: 5,
                assignableRoleIds: [1, 2],
                assignablePermissionIds: [3],
            );

            $this->delegationRepository->shouldReceive('getAssignableRoles')
                ->andReturn(new Collection);
            $this->delegationRepository->shouldReceive('getAssignablePermissions')
                ->andReturn(new Collection);

            $this->transactionManager->shouldReceive('transaction')
                ->once()
                ->andReturnUsing(fn ($callback) => $callback());

            $this->delegationRepository->shouldReceive('updateDelegationSettings')
                ->once()
                ->with($user, true, 5);

            $this->delegationRepository->shouldReceive('syncAssignableRoles')
                ->once()
                ->with($user, [1, 2]);

            $this->delegationRepository->shouldReceive('syncAssignablePermissions')
                ->once()
                ->with($user, [3]);

            $this->audit->shouldReceive('logDelegationScopeChanged')
                ->once();

            $this->eventDispatcher->shouldReceive('dispatch')
                ->once();

            $this->service->setDelegationScope($user, $newScope, $admin);
        });

        it('does not dispatch event when scope unchanged', function (): void {
            $user = createMockUser(1, canManageUsers: true, maxUsers: 5);
            $existingRoles = new Collection([createMockRole(1, 'editor')]);
            $existingPerms = new Collection([createMockPermission(1, 'create')]);
            $scope = new DelegationScope(
                canManageUsers: true,
                maxManageableUsers: 5,
                assignableRoleIds: [1],
                assignablePermissionIds: [1],
            );

            $this->delegationRepository->shouldReceive('getAssignableRoles')
                ->andReturn($existingRoles);
            $this->delegationRepository->shouldReceive('getAssignablePermissions')
                ->andReturn($existingPerms);

            $this->transactionManager->shouldReceive('transaction')
                ->once()
                ->andReturnUsing(fn ($callback) => $callback());

            $this->delegationRepository->shouldReceive('updateDelegationSettings')->once();
            $this->delegationRepository->shouldReceive('syncAssignableRoles')->once();
            $this->delegationRepository->shouldReceive('syncAssignablePermissions')->once();

            $this->audit->shouldNotReceive('logDelegationScopeChanged');
            $this->eventDispatcher->shouldNotReceive('dispatch');

            $this->service->setDelegationScope($user, $scope);
        });
    });

    describe('delegateRoles', function (): void {
        it('does nothing when roles array is empty', function (): void {
            $delegator = createMockUser(1);
            $target = createMockUser(2);

            $this->transactionManager->shouldNotReceive('transaction');

            $this->service->delegateRoles($delegator, $target, []);
        });

        it('delegates multiple roles in transaction', function (): void {
            $delegator = createMockUser(1);
            $target = createMockUser(2);
            $role1 = createMockRole(1, 'editor');
            $role2 = createMockRole(2, 'moderator');

            $this->transactionManager->shouldReceive('transaction')
                ->once()
                ->andReturnUsing(fn ($callback) => $callback());

            $this->authorizer->shouldReceive('canAssignRole')
                ->with($delegator, $role1, $target)
                ->andReturn(true);
            $this->authorizer->shouldReceive('canAssignRole')
                ->with($delegator, $role2, $target)
                ->andReturn(true);

            $this->roleRepository->shouldReceive('assignToUser')
                ->with($target, $role1)
                ->once();
            $this->roleRepository->shouldReceive('assignToUser')
                ->with($target, $role2)
                ->once();

            $this->audit->shouldReceive('logRoleAssigned')->twice();
            $this->eventDispatcher->shouldReceive('dispatch')->twice();

            $this->service->delegateRoles($delegator, $target, [$role1, $role2]);
        });
    });

    describe('delegatePermissions', function (): void {
        it('does nothing when permissions array is empty', function (): void {
            $delegator = createMockUser(1);
            $target = createMockUser(2);

            $this->transactionManager->shouldNotReceive('transaction');

            $this->service->delegatePermissions($delegator, $target, []);
        });

        it('delegates multiple permissions in transaction', function (): void {
            $delegator = createMockUser(1);
            $target = createMockUser(2);
            $perm1 = createMockPermission(1, 'create-posts');
            $perm2 = createMockPermission(2, 'edit-posts');

            $this->transactionManager->shouldReceive('transaction')
                ->once()
                ->andReturnUsing(fn ($callback) => $callback());

            $this->authorizer->shouldReceive('canAssignPermission')
                ->with($delegator, $perm1, $target)
                ->andReturn(true);
            $this->authorizer->shouldReceive('canAssignPermission')
                ->with($delegator, $perm2, $target)
                ->andReturn(true);

            $this->permissionRepository->shouldReceive('assignToUser')
                ->with($target, $perm1)
                ->once();
            $this->permissionRepository->shouldReceive('assignToUser')
                ->with($target, $perm2)
                ->once();

            $this->audit->shouldReceive('logPermissionGranted')->twice();
            $this->eventDispatcher->shouldReceive('dispatch')->twice();

            $this->service->delegatePermissions($delegator, $target, [$perm1, $perm2]);
        });
    });

    describe('revokeRoles', function (): void {
        it('does nothing when roles array is empty', function (): void {
            $delegator = createMockUser(1);
            $target = createMockUser(2);

            $this->transactionManager->shouldNotReceive('transaction');

            $this->service->revokeRoles($delegator, $target, []);
        });

        it('revokes multiple roles in transaction', function (): void {
            $delegator = createMockUser(1);
            $target = createMockUser(2);
            $role1 = createMockRole(1, 'editor');
            $role2 = createMockRole(2, 'moderator');

            $this->transactionManager->shouldReceive('transaction')
                ->once()
                ->andReturnUsing(fn ($callback) => $callback());

            $this->authorizer->shouldReceive('canRevokeRole')
                ->with($delegator, $role1, $target)
                ->andReturn(true);
            $this->authorizer->shouldReceive('canRevokeRole')
                ->with($delegator, $role2, $target)
                ->andReturn(true);

            $this->roleRepository->shouldReceive('removeFromUser')
                ->with($target, $role1)
                ->once();
            $this->roleRepository->shouldReceive('removeFromUser')
                ->with($target, $role2)
                ->once();

            $this->audit->shouldReceive('logRoleRevoked')->twice();
            $this->eventDispatcher->shouldReceive('dispatch')->twice();

            $this->service->revokeRoles($delegator, $target, [$role1, $role2]);
        });
    });

    describe('revokePermissions', function (): void {
        it('does nothing when permissions array is empty', function (): void {
            $delegator = createMockUser(1);
            $target = createMockUser(2);

            $this->transactionManager->shouldNotReceive('transaction');

            $this->service->revokePermissions($delegator, $target, []);
        });

        it('revokes multiple permissions in transaction', function (): void {
            $delegator = createMockUser(1);
            $target = createMockUser(2);
            $perm1 = createMockPermission(1, 'create-posts');
            $perm2 = createMockPermission(2, 'edit-posts');

            $this->transactionManager->shouldReceive('transaction')
                ->once()
                ->andReturnUsing(fn ($callback) => $callback());

            $this->authorizer->shouldReceive('canRevokePermission')
                ->with($delegator, $perm1, $target)
                ->andReturn(true);
            $this->authorizer->shouldReceive('canRevokePermission')
                ->with($delegator, $perm2, $target)
                ->andReturn(true);

            $this->permissionRepository->shouldReceive('removeFromUser')
                ->with($target, $perm1)
                ->once();
            $this->permissionRepository->shouldReceive('removeFromUser')
                ->with($target, $perm2)
                ->once();

            $this->audit->shouldReceive('logPermissionRevoked')->twice();
            $this->eventDispatcher->shouldReceive('dispatch')->twice();

            $this->service->revokePermissions($delegator, $target, [$perm1, $perm2]);
        });
    });
});
