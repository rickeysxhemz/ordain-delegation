<?php

declare(strict_types=1);

use Ordain\Delegation\Contracts\AuthorizationPipelineInterface;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Services\Authorization\DelegationAuthorizer;

beforeEach(function (): void {
    $this->pipeline = Mockery::mock(AuthorizationPipelineInterface::class);

    $this->authorizer = new DelegationAuthorizer(
        pipeline: $this->pipeline,
    );

    $this->delegator = Mockery::mock(DelegatableUserInterface::class);
    $this->target = Mockery::mock(DelegatableUserInterface::class);
    $this->role = Mockery::mock(RoleInterface::class);
    $this->permission = Mockery::mock(PermissionInterface::class);
});

describe('canAssignRole', function (): void {
    it('delegates to pipeline canAssignRole', function (): void {
        $this->pipeline->shouldReceive('canAssignRole')
            ->with($this->delegator, $this->role, null)
            ->once()
            ->andReturn(true);

        expect($this->authorizer->canAssignRole($this->delegator, $this->role))->toBeTrue();
    });

    it('passes target to pipeline when provided', function (): void {
        $this->pipeline->shouldReceive('canAssignRole')
            ->with($this->delegator, $this->role, $this->target)
            ->once()
            ->andReturn(false);

        expect($this->authorizer->canAssignRole($this->delegator, $this->role, $this->target))->toBeFalse();
    });
});

describe('canAssignPermission', function (): void {
    it('delegates to pipeline canAssignPermission', function (): void {
        $this->pipeline->shouldReceive('canAssignPermission')
            ->with($this->delegator, $this->permission, null)
            ->once()
            ->andReturn(true);

        expect($this->authorizer->canAssignPermission($this->delegator, $this->permission))->toBeTrue();
    });

    it('passes target to pipeline when provided', function (): void {
        $this->pipeline->shouldReceive('canAssignPermission')
            ->with($this->delegator, $this->permission, $this->target)
            ->once()
            ->andReturn(false);

        expect($this->authorizer->canAssignPermission($this->delegator, $this->permission, $this->target))->toBeFalse();
    });
});

describe('canRevokeRole', function (): void {
    it('delegates to pipeline canAssignRole for revocation', function (): void {
        $this->pipeline->shouldReceive('canAssignRole')
            ->with($this->delegator, $this->role, $this->target)
            ->once()
            ->andReturn(true);

        expect($this->authorizer->canRevokeRole($this->delegator, $this->role, $this->target))->toBeTrue();
    });
});

describe('canRevokePermission', function (): void {
    it('delegates to pipeline canAssignPermission for revocation', function (): void {
        $this->pipeline->shouldReceive('canAssignPermission')
            ->with($this->delegator, $this->permission, $this->target)
            ->once()
            ->andReturn(true);

        expect($this->authorizer->canRevokePermission($this->delegator, $this->permission, $this->target))->toBeTrue();
    });
});

describe('canManageUser', function (): void {
    it('delegates to pipeline canManageUser', function (): void {
        $this->pipeline->shouldReceive('canManageUser')
            ->with($this->delegator, $this->target)
            ->once()
            ->andReturn(true);

        expect($this->authorizer->canManageUser($this->delegator, $this->target))->toBeTrue();
    });

    it('returns false when pipeline returns false', function (): void {
        $this->pipeline->shouldReceive('canManageUser')
            ->with($this->delegator, $this->target)
            ->once()
            ->andReturn(false);

        expect($this->authorizer->canManageUser($this->delegator, $this->target))->toBeFalse();
    });
});
