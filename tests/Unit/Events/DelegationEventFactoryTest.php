<?php

declare(strict_types=1);

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;
use Ordain\Delegation\Events\DelegationEventFactory;
use Ordain\Delegation\Events\DelegationScopeUpdated;
use Ordain\Delegation\Events\PermissionGranted;
use Ordain\Delegation\Events\PermissionRevoked;
use Ordain\Delegation\Events\RoleDelegated;
use Ordain\Delegation\Events\RoleRevoked;

beforeEach(function (): void {
    $this->factory = new DelegationEventFactory;
    $this->delegator = Mockery::mock(DelegatableUserInterface::class);
    $this->target = Mockery::mock(DelegatableUserInterface::class);
    $this->role = Mockery::mock(RoleInterface::class);
    $this->permission = Mockery::mock(PermissionInterface::class);
});

describe('DelegationEventFactory', function (): void {
    it('creates RoleDelegated event', function (): void {
        $event = $this->factory->createRoleDelegated($this->delegator, $this->target, $this->role);

        expect($event)->toBeInstanceOf(RoleDelegated::class)
            ->and($event->delegator)->toBe($this->delegator)
            ->and($event->target)->toBe($this->target)
            ->and($event->role)->toBe($this->role);
    });

    it('creates RoleRevoked event', function (): void {
        $event = $this->factory->createRoleRevoked($this->delegator, $this->target, $this->role);

        expect($event)->toBeInstanceOf(RoleRevoked::class)
            ->and($event->delegator)->toBe($this->delegator)
            ->and($event->target)->toBe($this->target)
            ->and($event->role)->toBe($this->role);
    });

    it('creates PermissionGranted event', function (): void {
        $event = $this->factory->createPermissionGranted($this->delegator, $this->target, $this->permission);

        expect($event)->toBeInstanceOf(PermissionGranted::class)
            ->and($event->delegator)->toBe($this->delegator)
            ->and($event->target)->toBe($this->target)
            ->and($event->permission)->toBe($this->permission);
    });

    it('creates PermissionRevoked event', function (): void {
        $event = $this->factory->createPermissionRevoked($this->delegator, $this->target, $this->permission);

        expect($event)->toBeInstanceOf(PermissionRevoked::class)
            ->and($event->delegator)->toBe($this->delegator)
            ->and($event->target)->toBe($this->target)
            ->and($event->permission)->toBe($this->permission);
    });

    it('creates DelegationScopeUpdated event', function (): void {
        $oldScope = DelegationScope::none();
        $newScope = DelegationScope::unlimited([1, 2]);

        $event = $this->factory->createDelegationScopeUpdated($this->delegator, $oldScope, $newScope, $this->target);

        expect($event)->toBeInstanceOf(DelegationScopeUpdated::class)
            ->and($event->user)->toBe($this->delegator)
            ->and($event->oldScope)->toBe($oldScope)
            ->and($event->newScope)->toBe($newScope)
            ->and($event->admin)->toBe($this->target);
    });

    it('creates DelegationScopeUpdated event without admin', function (): void {
        $oldScope = DelegationScope::none();
        $newScope = DelegationScope::unlimited([1]);

        $event = $this->factory->createDelegationScopeUpdated($this->delegator, $oldScope, $newScope);

        expect($event)->toBeInstanceOf(DelegationScopeUpdated::class)
            ->and($event->admin)->toBeNull();
    });
});
