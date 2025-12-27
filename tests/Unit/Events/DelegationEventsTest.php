<?php

declare(strict_types=1);

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;
use Ordain\Delegation\Events\DelegationScopeUpdated;
use Ordain\Delegation\Events\PermissionGranted;
use Ordain\Delegation\Events\PermissionRevoked;
use Ordain\Delegation\Events\RoleDelegated;
use Ordain\Delegation\Events\RoleRevoked;
use Ordain\Delegation\Events\UnauthorizedDelegationAttempted;

describe('RoleDelegated', function (): void {
    it('stores delegator, target, and role', function (): void {
        $delegator = Mockery::mock(DelegatableUserInterface::class);
        $target = Mockery::mock(DelegatableUserInterface::class);
        $role = Mockery::mock(RoleInterface::class);

        $event = new RoleDelegated($delegator, $target, $role);

        expect($event->delegator)->toBe($delegator)
            ->and($event->target)->toBe($target)
            ->and($event->role)->toBe($role);
    });
});

describe('RoleRevoked', function (): void {
    it('stores delegator, target, and role', function (): void {
        $delegator = Mockery::mock(DelegatableUserInterface::class);
        $target = Mockery::mock(DelegatableUserInterface::class);
        $role = Mockery::mock(RoleInterface::class);

        $event = new RoleRevoked($delegator, $target, $role);

        expect($event->delegator)->toBe($delegator)
            ->and($event->target)->toBe($target)
            ->and($event->role)->toBe($role);
    });
});

describe('PermissionGranted', function (): void {
    it('stores delegator, target, and permission', function (): void {
        $delegator = Mockery::mock(DelegatableUserInterface::class);
        $target = Mockery::mock(DelegatableUserInterface::class);
        $permission = Mockery::mock(PermissionInterface::class);

        $event = new PermissionGranted($delegator, $target, $permission);

        expect($event->delegator)->toBe($delegator)
            ->and($event->target)->toBe($target)
            ->and($event->permission)->toBe($permission);
    });
});

describe('PermissionRevoked', function (): void {
    it('stores delegator, target, and permission', function (): void {
        $delegator = Mockery::mock(DelegatableUserInterface::class);
        $target = Mockery::mock(DelegatableUserInterface::class);
        $permission = Mockery::mock(PermissionInterface::class);

        $event = new PermissionRevoked($delegator, $target, $permission);

        expect($event->delegator)->toBe($delegator)
            ->and($event->target)->toBe($target)
            ->and($event->permission)->toBe($permission);
    });
});

describe('DelegationScopeUpdated', function (): void {
    it('stores user and scopes', function (): void {
        $user = Mockery::mock(DelegatableUserInterface::class);
        $oldScope = DelegationScope::none();
        $newScope = DelegationScope::unlimited();

        $event = new DelegationScopeUpdated($user, $oldScope, $newScope);

        expect($event->user)->toBe($user)
            ->and($event->oldScope)->toBe($oldScope)
            ->and($event->newScope)->toBe($newScope);
    });
});

describe('UnauthorizedDelegationAttempted', function (): void {
    it('stores delegator, action, and context', function (): void {
        $delegator = Mockery::mock(DelegatableUserInterface::class);
        $action = 'assign_role';
        $context = ['role_id' => 1];

        $event = new UnauthorizedDelegationAttempted($delegator, $action, $context);

        expect($event->delegator)->toBe($delegator)
            ->and($event->action)->toBe($action)
            ->and($event->context)->toBe($context);
    });

    it('defaults to empty context', function (): void {
        $delegator = Mockery::mock(DelegatableUserInterface::class);
        $action = 'assign_role';

        $event = new UnauthorizedDelegationAttempted($delegator, $action);

        expect($event->context)->toBe([]);
    });
});
