<?php

declare(strict_types=1);

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationAuthorizerInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\QuotaManagerInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Domain\Specifications\CanAssignPermissionsSpecification;
use Ordain\Delegation\Domain\Specifications\CanAssignRolesSpecification;
use Ordain\Delegation\Domain\Specifications\CanManageUserSpecification;
use Ordain\Delegation\Domain\Specifications\DelegationContext;
use Ordain\Delegation\Domain\Specifications\QuotaNotExceededSpecification;
use Ordain\Delegation\Domain\Specifications\UserIsCreatorSpecification;

beforeEach(function (): void {
    $this->delegator = Mockery::mock(DelegatableUserInterface::class);
    $this->target = Mockery::mock(DelegatableUserInterface::class);
    $this->authorizer = Mockery::mock(DelegationAuthorizerInterface::class);
    $this->quotaManager = Mockery::mock(QuotaManagerInterface::class);
});

describe('DelegationContext', function (): void {
    it('creates context for user management', function (): void {
        $context = DelegationContext::forUserManagement($this->delegator, $this->target);

        expect($context->delegator)->toBe($this->delegator)
            ->and($context->target)->toBe($this->target)
            ->and($context->roles)->toBe([])
            ->and($context->permissions)->toBe([]);
    });

    it('creates context for delegation', function (): void {
        $role = Mockery::mock(RoleInterface::class);
        $permission = Mockery::mock(PermissionInterface::class);

        $context = DelegationContext::forDelegation(
            $this->delegator,
            $this->target,
            [$role],
            [$permission],
        );

        expect($context->delegator)->toBe($this->delegator)
            ->and($context->target)->toBe($this->target)
            ->and($context->roles)->toBe([$role])
            ->and($context->permissions)->toBe([$permission]);
    });
});

describe('CanManageUserSpecification', function (): void {
    it('returns false for invalid context type', function (): void {
        $spec = new CanManageUserSpecification($this->authorizer);

        expect($spec->isSatisfiedBy('invalid'))->toBeFalse()
            ->and($spec->getFailureReason())->toBe('Invalid context type');
    });

    it('returns false when target is null', function (): void {
        $spec = new CanManageUserSpecification($this->authorizer);
        $context = new DelegationContext($this->delegator);

        expect($spec->isSatisfiedBy($context))->toBeFalse()
            ->and($spec->getFailureReason())->toBe('Target user is required');
    });

    it('returns false when authorizer denies', function (): void {
        $this->authorizer->shouldReceive('canManageUser')
            ->with($this->delegator, $this->target)
            ->andReturn(false);

        $spec = new CanManageUserSpecification($this->authorizer);
        $context = DelegationContext::forUserManagement($this->delegator, $this->target);

        expect($spec->isSatisfiedBy($context))->toBeFalse()
            ->and($spec->getFailureReason())->toBe('You are not authorized to manage this user');
    });

    it('returns true when authorizer allows', function (): void {
        $this->authorizer->shouldReceive('canManageUser')
            ->with($this->delegator, $this->target)
            ->andReturn(true);

        $spec = new CanManageUserSpecification($this->authorizer);
        $context = DelegationContext::forUserManagement($this->delegator, $this->target);

        expect($spec->isSatisfiedBy($context))->toBeTrue()
            ->and($spec->getFailureReason())->toBeNull();
    });
});

describe('CanAssignRolesSpecification', function (): void {
    it('returns false for invalid context type', function (): void {
        $spec = new CanAssignRolesSpecification($this->authorizer);

        expect($spec->isSatisfiedBy('invalid'))->toBeFalse()
            ->and($spec->getFailureReason())->toBe('Invalid context type');
    });

    it('returns true when no roles to check', function (): void {
        $spec = new CanAssignRolesSpecification($this->authorizer);
        $context = new DelegationContext($this->delegator, $this->target);

        expect($spec->isSatisfiedBy($context))->toBeTrue();
    });

    it('returns true when all roles can be assigned', function (): void {
        $role1 = Mockery::mock(RoleInterface::class);
        $role2 = Mockery::mock(RoleInterface::class);

        $this->authorizer->shouldReceive('canAssignRole')
            ->with($this->delegator, $role1, $this->target)
            ->andReturn(true);
        $this->authorizer->shouldReceive('canAssignRole')
            ->with($this->delegator, $role2, $this->target)
            ->andReturn(true);

        $spec = new CanAssignRolesSpecification($this->authorizer);
        $context = DelegationContext::forDelegation($this->delegator, $this->target, [$role1, $role2]);

        expect($spec->isSatisfiedBy($context))->toBeTrue()
            ->and($spec->getRoleErrors())->toBe([]);
    });

    it('returns false when a role cannot be assigned', function (): void {
        $role = Mockery::mock(RoleInterface::class);
        $role->shouldReceive('getRoleName')->andReturn('admin');

        $this->authorizer->shouldReceive('canAssignRole')
            ->with($this->delegator, $role, $this->target)
            ->andReturn(false);

        $spec = new CanAssignRolesSpecification($this->authorizer);
        $context = DelegationContext::forDelegation($this->delegator, $this->target, [$role]);

        expect($spec->isSatisfiedBy($context))->toBeFalse()
            ->and($spec->getRoleErrors())->toHaveKey('admin');
    });

    it('collects all role errors', function (): void {
        $role1 = Mockery::mock(RoleInterface::class);
        $role1->shouldReceive('getRoleName')->andReturn('admin');
        $role2 = Mockery::mock(RoleInterface::class);
        $role2->shouldReceive('getRoleName')->andReturn('editor');

        $this->authorizer->shouldReceive('canAssignRole')->andReturn(false);

        $spec = new CanAssignRolesSpecification($this->authorizer);
        $context = DelegationContext::forDelegation($this->delegator, $this->target, [$role1, $role2]);

        $spec->isSatisfiedBy($context);

        expect($spec->getRoleErrors())->toHaveCount(2)
            ->toHaveKey('admin')
            ->toHaveKey('editor');
    });

    it('skips non-RoleInterface items in roles array', function (): void {
        $validRole = Mockery::mock(RoleInterface::class);
        $this->authorizer->shouldReceive('canAssignRole')
            ->with($this->delegator, $validRole, $this->target)
            ->once()
            ->andReturn(true);

        $spec = new CanAssignRolesSpecification($this->authorizer);

        // Mix valid RoleInterface with invalid items (strings, nulls, objects)
        $context = new DelegationContext(
            delegator: $this->delegator,
            target: $this->target,
            roles: ['invalid_string', $validRole, null, new stdClass],
        );

        $result = $spec->isSatisfiedBy($context);

        // Should pass because only valid role is checked and passes
        expect($result)->toBeTrue()
            ->and($spec->getRoleErrors())->toBe([]);
    });
});

describe('CanAssignPermissionsSpecification', function (): void {
    it('returns false for invalid context type', function (): void {
        $spec = new CanAssignPermissionsSpecification($this->authorizer);

        expect($spec->isSatisfiedBy('invalid'))->toBeFalse()
            ->and($spec->getFailureReason())->toBe('Invalid context type');
    });

    it('returns true when no permissions to check', function (): void {
        $spec = new CanAssignPermissionsSpecification($this->authorizer);
        $context = new DelegationContext($this->delegator, $this->target);

        expect($spec->isSatisfiedBy($context))->toBeTrue();
    });

    it('returns true when all permissions can be assigned', function (): void {
        $perm1 = Mockery::mock(PermissionInterface::class);
        $perm2 = Mockery::mock(PermissionInterface::class);

        $this->authorizer->shouldReceive('canAssignPermission')
            ->andReturn(true);

        $spec = new CanAssignPermissionsSpecification($this->authorizer);
        $context = DelegationContext::forDelegation($this->delegator, $this->target, [], [$perm1, $perm2]);

        expect($spec->isSatisfiedBy($context))->toBeTrue()
            ->and($spec->getPermissionErrors())->toBe([]);
    });

    it('returns false when a permission cannot be assigned', function (): void {
        $perm = Mockery::mock(PermissionInterface::class);
        $perm->shouldReceive('getPermissionName')->andReturn('create-posts');

        $this->authorizer->shouldReceive('canAssignPermission')
            ->with($this->delegator, $perm, $this->target)
            ->andReturn(false);

        $spec = new CanAssignPermissionsSpecification($this->authorizer);
        $context = DelegationContext::forDelegation($this->delegator, $this->target, [], [$perm]);

        expect($spec->isSatisfiedBy($context))->toBeFalse()
            ->and($spec->getPermissionErrors())->toHaveKey('create-posts');
    });

    it('skips non-PermissionInterface items in permissions array', function (): void {
        $validPerm = Mockery::mock(PermissionInterface::class);
        $this->authorizer->shouldReceive('canAssignPermission')
            ->with($this->delegator, $validPerm, $this->target)
            ->once()
            ->andReturn(true);

        $spec = new CanAssignPermissionsSpecification($this->authorizer);

        // Mix valid PermissionInterface with invalid items (strings, nulls, objects)
        $context = new DelegationContext(
            delegator: $this->delegator,
            target: $this->target,
            permissions: ['invalid_string', $validPerm, null, new stdClass],
        );

        $result = $spec->isSatisfiedBy($context);

        // Should pass because only valid permission is checked and passes
        expect($result)->toBeTrue()
            ->and($spec->getPermissionErrors())->toBe([]);
    });
});

describe('QuotaNotExceededSpecification', function (): void {
    it('returns false for invalid context type', function (): void {
        $spec = new QuotaNotExceededSpecification($this->quotaManager);

        expect($spec->isSatisfiedBy('invalid'))->toBeFalse()
            ->and($spec->getFailureReason())->toBe('Invalid context type');
    });

    it('returns false when quota is exceeded', function (): void {
        $this->quotaManager->shouldReceive('hasReachedLimit')
            ->with($this->delegator)
            ->andReturn(true);

        $spec = new QuotaNotExceededSpecification($this->quotaManager);
        $context = new DelegationContext($this->delegator);

        expect($spec->isSatisfiedBy($context))->toBeFalse()
            ->and($spec->getFailureReason())->toBe('User management quota exceeded');
    });

    it('returns true when quota is not exceeded', function (): void {
        $this->quotaManager->shouldReceive('hasReachedLimit')
            ->with($this->delegator)
            ->andReturn(false);

        $spec = new QuotaNotExceededSpecification($this->quotaManager);
        $context = new DelegationContext($this->delegator);

        expect($spec->isSatisfiedBy($context))->toBeTrue()
            ->and($spec->getFailureReason())->toBeNull();
    });
});

describe('UserIsCreatorSpecification', function (): void {
    it('returns false for invalid context type', function (): void {
        $spec = new UserIsCreatorSpecification;

        expect($spec->isSatisfiedBy('invalid'))->toBeFalse()
            ->and($spec->getFailureReason())->toBe('Invalid context type');
    });

    it('returns false when target is null', function (): void {
        $spec = new UserIsCreatorSpecification;
        $context = new DelegationContext($this->delegator);

        expect($spec->isSatisfiedBy($context))->toBeFalse()
            ->and($spec->getFailureReason())->toBe('Target user is required');
    });

    it('returns false when target has no creator', function (): void {
        $this->target->shouldReceive('getCreator')->andReturn(null);

        $spec = new UserIsCreatorSpecification;
        $context = DelegationContext::forUserManagement($this->delegator, $this->target);

        expect($spec->isSatisfiedBy($context))->toBeFalse()
            ->and($spec->getFailureReason())->toBe('Target user has no creator');
    });

    it('returns false when delegator is not the creator', function (): void {
        $creator = Mockery::mock(DelegatableUserInterface::class);
        $creator->shouldReceive('getDelegatableIdentifier')->andReturn(999);
        $this->delegator->shouldReceive('getDelegatableIdentifier')->andReturn(1);
        $this->target->shouldReceive('getCreator')->andReturn($creator);

        $spec = new UserIsCreatorSpecification;
        $context = DelegationContext::forUserManagement($this->delegator, $this->target);

        expect($spec->isSatisfiedBy($context))->toBeFalse()
            ->and($spec->getFailureReason())->toBe('Only the creator can manage this user');
    });

    it('returns true when delegator is the creator', function (): void {
        $this->delegator->shouldReceive('getDelegatableIdentifier')->andReturn(1);
        $this->target->shouldReceive('getCreator')->andReturn($this->delegator);

        $spec = new UserIsCreatorSpecification;
        $context = DelegationContext::forUserManagement($this->delegator, $this->target);

        expect($spec->isSatisfiedBy($context))->toBeTrue()
            ->and($spec->getFailureReason())->toBeNull();
    });
});

describe('composing delegation specifications', function (): void {
    it('can combine CanManageUser with QuotaNotExceeded', function (): void {
        $this->authorizer->shouldReceive('canManageUser')
            ->with($this->delegator, $this->target)
            ->andReturn(true);
        $this->quotaManager->shouldReceive('hasReachedLimit')
            ->with($this->delegator)
            ->andReturn(false);

        $spec = (new CanManageUserSpecification($this->authorizer))
            ->and(new QuotaNotExceededSpecification($this->quotaManager));

        $context = DelegationContext::forUserManagement($this->delegator, $this->target);

        expect($spec->isSatisfiedBy($context))->toBeTrue();
    });

    it('fails when quota is exceeded even if can manage', function (): void {
        $this->authorizer->shouldReceive('canManageUser')
            ->with($this->delegator, $this->target)
            ->andReturn(true);
        $this->quotaManager->shouldReceive('hasReachedLimit')
            ->with($this->delegator)
            ->andReturn(true);

        $spec = (new CanManageUserSpecification($this->authorizer))
            ->and(new QuotaNotExceededSpecification($this->quotaManager));

        $context = DelegationContext::forUserManagement($this->delegator, $this->target);

        expect($spec->isSatisfiedBy($context))->toBeFalse()
            ->and($spec->getFailureReason())->toBe('User management quota exceeded');
    });
});
