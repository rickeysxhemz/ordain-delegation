<?php

declare(strict_types=1);

use Illuminate\Pipeline\Pipeline;
use Mockery;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\Repositories\DelegationRepositoryInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Contracts\RootAdminResolverInterface;
use Ordain\Delegation\Services\Authorization\AuthorizationPipeline;

beforeEach(function (): void {
    $this->pipeline = app(Pipeline::class);
    $this->rootAdminResolver = Mockery::mock(RootAdminResolverInterface::class);
    $this->delegationRepository = Mockery::mock(DelegationRepositoryInterface::class);
    $this->delegator = Mockery::mock(DelegatableUserInterface::class);
    $this->target = Mockery::mock(DelegatableUserInterface::class);
    $this->role = Mockery::mock(RoleInterface::class);
    $this->permission = Mockery::mock(PermissionInterface::class);

    $this->delegator->shouldReceive('getDelegatableIdentifier')->andReturn(1);
    $this->target->shouldReceive('getDelegatableIdentifier')->andReturn(2);

    $this->authPipeline = new AuthorizationPipeline(
        pipeline: $this->pipeline,
        rootAdminResolver: $this->rootAdminResolver,
        delegationRepository: $this->delegationRepository,
    );
});

describe('AuthorizationPipeline', function (): void {
    describe('canAssignRole', function (): void {
        it('returns true when root admin', function (): void {
            $this->rootAdminResolver->shouldReceive('isRootAdmin')
                ->with($this->delegator)
                ->andReturn(true);

            $result = $this->authPipeline->canAssignRole($this->delegator, $this->role);

            expect($result)->toBeTrue();
        });

        it('returns true when role is in scope without target', function (): void {
            $this->rootAdminResolver->shouldReceive('isRootAdmin')
                ->with($this->delegator)
                ->andReturn(false);

            $this->delegator->shouldReceive('canManageUsers')->andReturn(true);

            $this->delegationRepository->shouldReceive('hasAssignableRole')
                ->with($this->delegator, $this->role)
                ->andReturn(true);

            $result = $this->authPipeline->canAssignRole($this->delegator, $this->role);

            expect($result)->toBeTrue();
        });

        it('returns false when user cannot manage users', function (): void {
            $this->rootAdminResolver->shouldReceive('isRootAdmin')
                ->with($this->delegator)
                ->andReturn(false);

            $this->delegator->shouldReceive('canManageUsers')->andReturn(false);

            $result = $this->authPipeline->canAssignRole($this->delegator, $this->role);

            expect($result)->toBeFalse();
        });
    });

    describe('canAssignPermission', function (): void {
        it('returns true when root admin', function (): void {
            $this->rootAdminResolver->shouldReceive('isRootAdmin')
                ->with($this->delegator)
                ->andReturn(true);

            $result = $this->authPipeline->canAssignPermission($this->delegator, $this->permission);

            expect($result)->toBeTrue();
        });

        it('returns true when permission is in scope without target', function (): void {
            $this->rootAdminResolver->shouldReceive('isRootAdmin')
                ->with($this->delegator)
                ->andReturn(false);

            $this->delegator->shouldReceive('canManageUsers')->andReturn(true);

            $this->delegationRepository->shouldReceive('hasAssignablePermission')
                ->with($this->delegator, $this->permission)
                ->andReturn(true);

            $result = $this->authPipeline->canAssignPermission($this->delegator, $this->permission);

            expect($result)->toBeTrue();
        });

        it('returns false when permission is not in scope', function (): void {
            $this->rootAdminResolver->shouldReceive('isRootAdmin')
                ->with($this->delegator)
                ->andReturn(false);

            $this->delegator->shouldReceive('canManageUsers')->andReturn(true);

            $this->delegationRepository->shouldReceive('hasAssignablePermission')
                ->with($this->delegator, $this->permission)
                ->andReturn(false);

            $result = $this->authPipeline->canAssignPermission($this->delegator, $this->permission);

            expect($result)->toBeFalse();
        });

        it('returns false when user cannot manage users', function (): void {
            $this->rootAdminResolver->shouldReceive('isRootAdmin')
                ->with($this->delegator)
                ->andReturn(false);

            $this->delegator->shouldReceive('canManageUsers')->andReturn(false);

            $result = $this->authPipeline->canAssignPermission($this->delegator, $this->permission);

            expect($result)->toBeFalse();
        });

        it('checks hierarchy when target is provided', function (): void {
            $this->rootAdminResolver->shouldReceive('isRootAdmin')
                ->with($this->delegator)
                ->andReturn(false);

            $this->delegator->shouldReceive('canManageUsers')->andReturn(true);
            $this->target->shouldReceive('getCreator')->andReturn($this->delegator);

            $this->delegationRepository->shouldReceive('hasAssignablePermission')
                ->with($this->delegator, $this->permission)
                ->andReturn(true);

            $result = $this->authPipeline->canAssignPermission($this->delegator, $this->permission, $this->target);

            expect($result)->toBeTrue();
        });
    });

    describe('canManageUser', function (): void {
        it('returns true when root admin', function (): void {
            $this->rootAdminResolver->shouldReceive('isRootAdmin')
                ->with($this->delegator)
                ->andReturn(true);

            $result = $this->authPipeline->canManageUser($this->delegator, $this->target);

            expect($result)->toBeTrue();
        });

        it('returns true when delegator is creator of target', function (): void {
            $this->rootAdminResolver->shouldReceive('isRootAdmin')
                ->with($this->delegator)
                ->andReturn(false);

            $this->delegator->shouldReceive('canManageUsers')->andReturn(true);
            $this->target->shouldReceive('getCreator')->andReturn($this->delegator);

            $result = $this->authPipeline->canManageUser($this->delegator, $this->target);

            expect($result)->toBeTrue();
        });

        it('returns false when delegator is not creator of target', function (): void {
            $creator = Mockery::mock(DelegatableUserInterface::class);
            $creator->shouldReceive('getDelegatableIdentifier')->andReturn(999);

            $this->rootAdminResolver->shouldReceive('isRootAdmin')
                ->with($this->delegator)
                ->andReturn(false);

            $this->delegator->shouldReceive('canManageUsers')->andReturn(true);
            $this->target->shouldReceive('getCreator')->andReturn($creator);

            $result = $this->authPipeline->canManageUser($this->delegator, $this->target);

            expect($result)->toBeFalse();
        });

        it('returns false when target has no creator', function (): void {
            $this->rootAdminResolver->shouldReceive('isRootAdmin')
                ->with($this->delegator)
                ->andReturn(false);

            $this->delegator->shouldReceive('canManageUsers')->andReturn(true);
            $this->target->shouldReceive('getCreator')->andReturn(null);

            $result = $this->authPipeline->canManageUser($this->delegator, $this->target);

            expect($result)->toBeFalse();
        });
    });
});
