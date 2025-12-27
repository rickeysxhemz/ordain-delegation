<?php

declare(strict_types=1);

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\Repositories\DelegationRepositoryInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Contracts\RootAdminResolverInterface;
use Ordain\Delegation\Services\Authorization\DelegationAuthorizer;

beforeEach(function (): void {
    $this->delegationRepository = Mockery::mock(DelegationRepositoryInterface::class);
    $this->rootAdminResolver = Mockery::mock(RootAdminResolverInterface::class);

    $this->authorizer = new DelegationAuthorizer(
        delegationRepository: $this->delegationRepository,
        rootAdminResolver: $this->rootAdminResolver,
    );

    $this->delegator = Mockery::mock(DelegatableUserInterface::class);
    $this->target = Mockery::mock(DelegatableUserInterface::class);
    $this->role = Mockery::mock(RoleInterface::class);
    $this->permission = Mockery::mock(PermissionInterface::class);
});

describe('canAssignRole', function (): void {
    it('returns true when delegator is root admin', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(true);

        expect($this->authorizer->canAssignRole($this->delegator, $this->role))->toBeTrue();
    });

    it('returns false when delegator cannot manage users', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('canManageUsers')->andReturn(false);

        expect($this->authorizer->canAssignRole($this->delegator, $this->role))->toBeFalse();
    });

    it('returns false when delegator cannot manage target user', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('canManageUsers')->andReturn(true);
        $this->delegator->shouldReceive('getDelegatableIdentifier')->andReturn(1);

        $this->target->shouldReceive('getDelegatableIdentifier')->andReturn(2);
        $this->target->shouldReceive('getCreator')->andReturn(null);

        expect($this->authorizer->canAssignRole($this->delegator, $this->role, $this->target))->toBeFalse();
    });

    it('returns true when delegator has assignable role', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('canManageUsers')->andReturn(true);

        $this->delegationRepository->shouldReceive('hasAssignableRole')
            ->with($this->delegator, $this->role)
            ->andReturn(true);

        expect($this->authorizer->canAssignRole($this->delegator, $this->role))->toBeTrue();
    });

    it('returns false when delegator does not have assignable role', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('canManageUsers')->andReturn(true);

        $this->delegationRepository->shouldReceive('hasAssignableRole')
            ->with($this->delegator, $this->role)
            ->andReturn(false);

        expect($this->authorizer->canAssignRole($this->delegator, $this->role))->toBeFalse();
    });
});

describe('canAssignPermission', function (): void {
    it('returns true when delegator is root admin', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(true);

        expect($this->authorizer->canAssignPermission($this->delegator, $this->permission))->toBeTrue();
    });

    it('returns false when delegator cannot manage users', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('canManageUsers')->andReturn(false);

        expect($this->authorizer->canAssignPermission($this->delegator, $this->permission))->toBeFalse();
    });

    it('returns true when delegator has assignable permission', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('canManageUsers')->andReturn(true);

        $this->delegationRepository->shouldReceive('hasAssignablePermission')
            ->with($this->delegator, $this->permission)
            ->andReturn(true);

        expect($this->authorizer->canAssignPermission($this->delegator, $this->permission))->toBeTrue();
    });
});

describe('canRevokeRole', function (): void {
    it('delegates to canAssignRole', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(true);

        expect($this->authorizer->canRevokeRole($this->delegator, $this->role, $this->target))->toBeTrue();
    });
});

describe('canRevokePermission', function (): void {
    it('delegates to canAssignPermission', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(true);

        expect($this->authorizer->canRevokePermission($this->delegator, $this->permission, $this->target))->toBeTrue();
    });
});

describe('canManageUser', function (): void {
    it('returns true when delegator is root admin', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(true);

        expect($this->authorizer->canManageUser($this->delegator, $this->target))->toBeTrue();
    });

    it('returns false when delegator tries to manage themselves', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('getDelegatableIdentifier')->andReturn(1);
        $this->target->shouldReceive('getDelegatableIdentifier')->andReturn(1);

        expect($this->authorizer->canManageUser($this->delegator, $this->target))->toBeFalse();
    });

    it('returns false when delegator cannot manage users', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('getDelegatableIdentifier')->andReturn(1);
        $this->target->shouldReceive('getDelegatableIdentifier')->andReturn(2);
        $this->delegator->shouldReceive('canManageUsers')->andReturn(false);

        expect($this->authorizer->canManageUser($this->delegator, $this->target))->toBeFalse();
    });

    it('returns false when target has no creator', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('getDelegatableIdentifier')->andReturn(1);
        $this->target->shouldReceive('getDelegatableIdentifier')->andReturn(2);
        $this->delegator->shouldReceive('canManageUsers')->andReturn(true);
        $this->target->shouldReceive('getCreator')->andReturn(null);

        expect($this->authorizer->canManageUser($this->delegator, $this->target))->toBeFalse();
    });

    it('returns false when target was created by different user', function (): void {
        $creator = Mockery::mock(DelegatableUserInterface::class);
        $creator->shouldReceive('getDelegatableIdentifier')->andReturn(3);

        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('getDelegatableIdentifier')->andReturn(1);
        $this->target->shouldReceive('getDelegatableIdentifier')->andReturn(2);
        $this->delegator->shouldReceive('canManageUsers')->andReturn(true);
        $this->target->shouldReceive('getCreator')->andReturn($creator);

        expect($this->authorizer->canManageUser($this->delegator, $this->target))->toBeFalse();
    });

    it('returns true when target was created by delegator', function (): void {
        $creator = Mockery::mock(DelegatableUserInterface::class);
        $creator->shouldReceive('getDelegatableIdentifier')->andReturn(1);

        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('getDelegatableIdentifier')->andReturn(1);
        $this->target->shouldReceive('getDelegatableIdentifier')->andReturn(2);
        $this->delegator->shouldReceive('canManageUsers')->andReturn(true);
        $this->target->shouldReceive('getCreator')->andReturn($creator);

        expect($this->authorizer->canManageUser($this->delegator, $this->target))->toBeTrue();
    });
});
