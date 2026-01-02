<?php

declare(strict_types=1);

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\Repositories\DelegationRepositoryInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Contracts\RootAdminResolverInterface;
use Ordain\Delegation\Services\Authorization\AuthorizationContext;
use Ordain\Delegation\Services\Authorization\Pipes\CheckHierarchyPipe;
use Ordain\Delegation\Services\Authorization\Pipes\CheckRoleInScopePipe;
use Ordain\Delegation\Services\Authorization\Pipes\CheckRootAdminPipe;
use Ordain\Delegation\Services\Authorization\Pipes\CheckUserManagementPipe;

beforeEach(function (): void {
    $this->delegator = Mockery::mock(DelegatableUserInterface::class);
    $this->target = Mockery::mock(DelegatableUserInterface::class);
    $this->role = Mockery::mock(RoleInterface::class);
    $this->permission = Mockery::mock(PermissionInterface::class);
    $this->rootAdminResolver = Mockery::mock(RootAdminResolverInterface::class);
    $this->delegationRepository = Mockery::mock(DelegationRepositoryInterface::class);

    $this->nextCalled = false;
    $this->next = function (AuthorizationContext $ctx): AuthorizationContext {
        $this->nextCalled = true;

        return $ctx;
    };
});

describe('CheckRootAdminPipe', function (): void {
    it('grants access when delegator is root admin', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(true);

        $pipe = new CheckRootAdminPipe($this->rootAdminResolver);
        $context = AuthorizationContext::forRoleAssignment($this->delegator, $this->role);

        $result = $pipe->handle($context, $this->next);

        expect($result->isGranted())->toBeTrue()
            ->and($this->nextCalled)->toBeFalse();
    });

    it('passes to next when delegator is not root admin', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $pipe = new CheckRootAdminPipe($this->rootAdminResolver);
        $context = AuthorizationContext::forRoleAssignment($this->delegator, $this->role);

        $pipe->handle($context, $this->next);

        expect($this->nextCalled)->toBeTrue();
    });
});

describe('CheckUserManagementPipe', function (): void {
    it('passes to next when delegator can manage users', function (): void {
        $this->delegator->shouldReceive('canManageUsers')->andReturn(true);

        $pipe = new CheckUserManagementPipe;
        $context = AuthorizationContext::forRoleAssignment($this->delegator, $this->role);

        $pipe->handle($context, $this->next);

        expect($this->nextCalled)->toBeTrue();
    });

    it('denies when delegator cannot manage users', function (): void {
        $this->delegator->shouldReceive('canManageUsers')->andReturn(false);

        $pipe = new CheckUserManagementPipe;
        $context = AuthorizationContext::forRoleAssignment($this->delegator, $this->role);

        $result = $pipe->handle($context, $this->next);

        expect($result->isDenied())->toBeTrue()
            ->and($result->getDeniedReason())->toBe('User cannot manage other users')
            ->and($this->nextCalled)->toBeFalse();
    });
});

describe('CheckHierarchyPipe', function (): void {
    it('passes to next when no target user', function (): void {
        $pipe = new CheckHierarchyPipe;
        $context = AuthorizationContext::forRoleAssignment($this->delegator, $this->role);

        $pipe->handle($context, $this->next);

        expect($this->nextCalled)->toBeTrue();
    });

    it('denies when delegator tries to manage themselves', function (): void {
        $this->delegator->shouldReceive('getDelegatableIdentifier')->andReturn(1);
        $this->target->shouldReceive('getDelegatableIdentifier')->andReturn(1);

        $pipe = new CheckHierarchyPipe;
        $context = AuthorizationContext::forRoleAssignment($this->delegator, $this->role, $this->target);

        $result = $pipe->handle($context, $this->next);

        expect($result->isDenied())->toBeTrue()
            ->and($result->getDeniedReason())->toBe('Cannot manage yourself')
            ->and($this->nextCalled)->toBeFalse();
    });

    it('denies when target has no creator', function (): void {
        $this->delegator->shouldReceive('getDelegatableIdentifier')->andReturn(1);
        $this->target->shouldReceive('getDelegatableIdentifier')->andReturn(2);
        $this->target->shouldReceive('getCreator')->andReturn(null);

        $pipe = new CheckHierarchyPipe;
        $context = AuthorizationContext::forRoleAssignment($this->delegator, $this->role, $this->target);

        $result = $pipe->handle($context, $this->next);

        expect($result->isDenied())->toBeTrue()
            ->and($result->getDeniedReason())->toBe('Target user has no creator')
            ->and($this->nextCalled)->toBeFalse();
    });

    it('denies when delegator is not the creator', function (): void {
        $creator = Mockery::mock(DelegatableUserInterface::class);
        $creator->shouldReceive('getDelegatableIdentifier')->andReturn(999);

        $this->delegator->shouldReceive('getDelegatableIdentifier')->andReturn(1);
        $this->target->shouldReceive('getDelegatableIdentifier')->andReturn(2);
        $this->target->shouldReceive('getCreator')->andReturn($creator);

        $pipe = new CheckHierarchyPipe;
        $context = AuthorizationContext::forRoleAssignment($this->delegator, $this->role, $this->target);

        $result = $pipe->handle($context, $this->next);

        expect($result->isDenied())->toBeTrue()
            ->and($result->getDeniedReason())->toBe('Can only manage users you created')
            ->and($this->nextCalled)->toBeFalse();
    });

    it('passes when delegator is the creator', function (): void {
        $this->delegator->shouldReceive('getDelegatableIdentifier')->andReturn(1);
        $this->target->shouldReceive('getDelegatableIdentifier')->andReturn(2);
        $this->target->shouldReceive('getCreator')->andReturn($this->delegator);

        $pipe = new CheckHierarchyPipe;
        $context = AuthorizationContext::forRoleAssignment($this->delegator, $this->role, $this->target);

        $pipe->handle($context, $this->next);

        expect($this->nextCalled)->toBeTrue();
    });
});

describe('CheckRoleInScopePipe', function (): void {
    it('grants when role is in assignable scope', function (): void {
        $this->delegationRepository->shouldReceive('hasAssignableRole')
            ->with($this->delegator, $this->role)
            ->andReturn(true);

        $pipe = new CheckRoleInScopePipe($this->delegationRepository);
        $context = AuthorizationContext::forRoleAssignment($this->delegator, $this->role);

        $result = $pipe->handle($context, $this->next);

        expect($result->isGranted())->toBeTrue()
            ->and($this->nextCalled)->toBeFalse();
    });

    it('denies when role is not in assignable scope', function (): void {
        $this->delegationRepository->shouldReceive('hasAssignableRole')
            ->with($this->delegator, $this->role)
            ->andReturn(false);

        $pipe = new CheckRoleInScopePipe($this->delegationRepository);
        $context = AuthorizationContext::forRoleAssignment($this->delegator, $this->role);

        $result = $pipe->handle($context, $this->next);

        expect($result->isDenied())->toBeTrue()
            ->and($result->getDeniedReason())->toBe('Role not in assignable scope')
            ->and($this->nextCalled)->toBeFalse();
    });

    it('grants when permission is in assignable scope', function (): void {
        $this->delegationRepository->shouldReceive('hasAssignablePermission')
            ->with($this->delegator, $this->permission)
            ->andReturn(true);

        $pipe = new CheckRoleInScopePipe($this->delegationRepository);
        $context = AuthorizationContext::forPermissionAssignment($this->delegator, $this->permission);

        $result = $pipe->handle($context, $this->next);

        expect($result->isGranted())->toBeTrue();
    });

    it('denies when permission is not in assignable scope', function (): void {
        $this->delegationRepository->shouldReceive('hasAssignablePermission')
            ->with($this->delegator, $this->permission)
            ->andReturn(false);

        $pipe = new CheckRoleInScopePipe($this->delegationRepository);
        $context = AuthorizationContext::forPermissionAssignment($this->delegator, $this->permission);

        $result = $pipe->handle($context, $this->next);

        expect($result->isDenied())->toBeTrue()
            ->and($result->getDeniedReason())->toBe('Permission not in assignable scope');
    });

    it('grants for manage_user action when checks pass', function (): void {
        $pipe = new CheckRoleInScopePipe($this->delegationRepository);
        $context = AuthorizationContext::forUserManagement($this->delegator, $this->target);

        $result = $pipe->handle($context, $this->next);

        expect($result->isGranted())->toBeTrue();
    });

    it('passes to next for unknown action', function (): void {
        $pipe = new CheckRoleInScopePipe($this->delegationRepository);
        // Create a context without role or permission
        $context = new AuthorizationContext(
            delegator: $this->delegator,
            action: 'unknown_action',
        );

        $pipe->handle($context, $this->next);

        expect($this->nextCalled)->toBeTrue();
    });
});

describe('AuthorizationContext', function (): void {
    it('creates context for role assignment', function (): void {
        $context = AuthorizationContext::forRoleAssignment($this->delegator, $this->role, $this->target);

        expect($context->delegator)->toBe($this->delegator)
            ->and($context->role)->toBe($this->role)
            ->and($context->target)->toBe($this->target)
            ->and($context->action)->toBe('assign_role')
            ->and($context->permission)->toBeNull();
    });

    it('creates context for permission assignment', function (): void {
        $context = AuthorizationContext::forPermissionAssignment($this->delegator, $this->permission, $this->target);

        expect($context->delegator)->toBe($this->delegator)
            ->and($context->permission)->toBe($this->permission)
            ->and($context->target)->toBe($this->target)
            ->and($context->action)->toBe('assign_permission')
            ->and($context->role)->toBeNull();
    });

    it('creates context for user management', function (): void {
        $context = AuthorizationContext::forUserManagement($this->delegator, $this->target);

        expect($context->delegator)->toBe($this->delegator)
            ->and($context->target)->toBe($this->target)
            ->and($context->action)->toBe('manage_user')
            ->and($context->role)->toBeNull()
            ->and($context->permission)->toBeNull();
    });

    it('grants authorization', function (): void {
        $context = AuthorizationContext::forRoleAssignment($this->delegator, $this->role);

        $granted = $context->grant();

        expect($granted->isGranted())->toBeTrue()
            ->and($granted->isDenied())->toBeFalse();
    });

    it('denies authorization with reason', function (): void {
        $context = AuthorizationContext::forRoleAssignment($this->delegator, $this->role);

        $denied = $context->deny('Test reason');

        expect($denied->isDenied())->toBeTrue()
            ->and($denied->isGranted())->toBeFalse()
            ->and($denied->getDeniedReason())->toBe('Test reason');
    });

    it('is not resolved initially', function (): void {
        $context = AuthorizationContext::forRoleAssignment($this->delegator, $this->role);

        expect($context->isResolved())->toBeFalse()
            ->and($context->getResult())->toBeNull();
    });

    it('is resolved after grant', function (): void {
        $context = AuthorizationContext::forRoleAssignment($this->delegator, $this->role);
        $context->grant();

        expect($context->isResolved())->toBeTrue()
            ->and($context->getResult())->toBeTrue();
    });

    it('is resolved after deny', function (): void {
        $context = AuthorizationContext::forRoleAssignment($this->delegator, $this->role);
        $context->deny('Denied');

        expect($context->isResolved())->toBeTrue()
            ->and($context->getResult())->toBeFalse();
    });

    it('denies authorization without reason', function (): void {
        $context = AuthorizationContext::forRoleAssignment($this->delegator, $this->role);

        $denied = $context->deny();

        expect($denied->isDenied())->toBeTrue()
            ->and($denied->getDeniedReason())->toBeNull();
    });
});
