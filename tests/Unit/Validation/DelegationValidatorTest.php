<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationAuthorizerInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\Repositories\PermissionRepositoryInterface;
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Services\Validation\DelegationValidator;

beforeEach(function (): void {
    $this->authorizer = Mockery::mock(DelegationAuthorizerInterface::class);
    $this->roleRepository = Mockery::mock(RoleRepositoryInterface::class);
    $this->permissionRepository = Mockery::mock(PermissionRepositoryInterface::class);

    $this->validator = new DelegationValidator(
        authorizer: $this->authorizer,
        roleRepository: $this->roleRepository,
        permissionRepository: $this->permissionRepository,
    );

    $this->delegator = Mockery::mock(DelegatableUserInterface::class);
    $this->target = Mockery::mock(DelegatableUserInterface::class);
});

describe('validate', function (): void {
    it('returns empty array when all validations pass', function (): void {
        $this->authorizer->shouldReceive('canManageUser')
            ->with($this->delegator, $this->target)
            ->andReturn(true);

        $errors = $this->validator->validate($this->delegator, $this->target);

        expect($errors)->toBeEmpty();
    });

    it('returns error when cannot manage target user', function (): void {
        $this->authorizer->shouldReceive('canManageUser')
            ->with($this->delegator, $this->target)
            ->andReturn(false);

        $errors = $this->validator->validate($this->delegator, $this->target);

        expect($errors)->toHaveKey('target')
            ->and($errors['target'])->toBe('You are not authorized to manage this user.');
    });

    it('validates roles and returns errors for missing roles', function (): void {
        $this->authorizer->shouldReceive('canManageUser')
            ->with($this->delegator, $this->target)
            ->andReturn(true);

        $this->roleRepository->shouldReceive('findByIds')
            ->with([1, 2])
            ->andReturn(new Collection([]));

        $errors = $this->validator->validate($this->delegator, $this->target, [1, 2]);

        expect($errors)->toHaveKey('role_1')
            ->and($errors['role_1'])->toBe('Role with ID 1 not found.')
            ->and($errors)->toHaveKey('role_2')
            ->and($errors['role_2'])->toBe('Role with ID 2 not found.');
    });

    it('validates roles and returns errors for unauthorized roles', function (): void {
        $role = Mockery::mock(RoleInterface::class);
        $role->shouldReceive('getRoleIdentifier')->andReturn(1);
        $role->shouldReceive('getRoleName')->andReturn('admin');

        $this->authorizer->shouldReceive('canManageUser')
            ->with($this->delegator, $this->target)
            ->andReturn(true);

        $this->authorizer->shouldReceive('canAssignRole')
            ->with($this->delegator, $role)
            ->andReturn(false);

        $this->roleRepository->shouldReceive('findByIds')
            ->with([1])
            ->andReturn(new Collection([$role]));

        $errors = $this->validator->validate($this->delegator, $this->target, [1]);

        expect($errors)->toHaveKey('role_1')
            ->and($errors['role_1'])->toBe("You cannot assign the role 'admin'.");
    });

    it('returns no role errors when roles are valid and authorized', function (): void {
        $role = Mockery::mock(RoleInterface::class);
        $role->shouldReceive('getRoleIdentifier')->andReturn(1);

        $this->authorizer->shouldReceive('canManageUser')
            ->with($this->delegator, $this->target)
            ->andReturn(true);

        $this->authorizer->shouldReceive('canAssignRole')
            ->with($this->delegator, $role)
            ->andReturn(true);

        $this->roleRepository->shouldReceive('findByIds')
            ->with([1])
            ->andReturn(new Collection([$role]));

        $errors = $this->validator->validate($this->delegator, $this->target, [1]);

        expect($errors)->toBeEmpty();
    });

    it('validates permissions and returns errors for missing permissions', function (): void {
        $this->authorizer->shouldReceive('canManageUser')
            ->with($this->delegator, $this->target)
            ->andReturn(true);

        $this->permissionRepository->shouldReceive('findByIds')
            ->with([1, 2])
            ->andReturn(new Collection([]));

        $errors = $this->validator->validate($this->delegator, $this->target, [], [1, 2]);

        expect($errors)->toHaveKey('permission_1')
            ->and($errors['permission_1'])->toBe('Permission with ID 1 not found.')
            ->and($errors)->toHaveKey('permission_2')
            ->and($errors['permission_2'])->toBe('Permission with ID 2 not found.');
    });

    it('validates permissions and returns errors for unauthorized permissions', function (): void {
        $permission = Mockery::mock(PermissionInterface::class);
        $permission->shouldReceive('getPermissionIdentifier')->andReturn(1);
        $permission->shouldReceive('getPermissionName')->andReturn('edit-posts');

        $this->authorizer->shouldReceive('canManageUser')
            ->with($this->delegator, $this->target)
            ->andReturn(true);

        $this->authorizer->shouldReceive('canAssignPermission')
            ->with($this->delegator, $permission)
            ->andReturn(false);

        $this->permissionRepository->shouldReceive('findByIds')
            ->with([1])
            ->andReturn(new Collection([$permission]));

        $errors = $this->validator->validate($this->delegator, $this->target, [], [1]);

        expect($errors)->toHaveKey('permission_1')
            ->and($errors['permission_1'])->toBe("You cannot grant the permission 'edit-posts'.");
    });

    it('validates both roles and permissions together', function (): void {
        $role = Mockery::mock(RoleInterface::class);
        $role->shouldReceive('getRoleIdentifier')->andReturn(1);

        $permission = Mockery::mock(PermissionInterface::class);
        $permission->shouldReceive('getPermissionIdentifier')->andReturn(2);

        $this->authorizer->shouldReceive('canManageUser')
            ->with($this->delegator, $this->target)
            ->andReturn(true);

        $this->authorizer->shouldReceive('canAssignRole')
            ->with($this->delegator, $role)
            ->andReturn(true);

        $this->authorizer->shouldReceive('canAssignPermission')
            ->with($this->delegator, $permission)
            ->andReturn(true);

        $this->roleRepository->shouldReceive('findByIds')
            ->with([1])
            ->andReturn(new Collection([$role]));

        $this->permissionRepository->shouldReceive('findByIds')
            ->with([2])
            ->andReturn(new Collection([$permission]));

        $errors = $this->validator->validate($this->delegator, $this->target, [1], [2]);

        expect($errors)->toBeEmpty();
    });

    it('skips role validation when no roles provided', function (): void {
        $this->authorizer->shouldReceive('canManageUser')
            ->with($this->delegator, $this->target)
            ->andReturn(true);

        $this->roleRepository->shouldNotReceive('findByIds');

        $errors = $this->validator->validate($this->delegator, $this->target, []);

        expect($errors)->toBeEmpty();
    });

    it('skips permission validation when no permissions provided', function (): void {
        $this->authorizer->shouldReceive('canManageUser')
            ->with($this->delegator, $this->target)
            ->andReturn(true);

        $this->permissionRepository->shouldNotReceive('findByIds');

        $errors = $this->validator->validate($this->delegator, $this->target, [], []);

        expect($errors)->toBeEmpty();
    });
});
