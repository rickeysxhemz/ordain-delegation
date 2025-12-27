<?php

declare(strict_types=1);

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;
use Ordain\Delegation\Services\Audit\LogDelegationAudit;
use Ordain\Delegation\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->audit = new LogDelegationAudit(channel: 'stack');

    $this->delegator = Mockery::mock(DelegatableUserInterface::class);
    $this->delegator->shouldReceive('getDelegatableIdentifier')->andReturn(1);

    $this->target = Mockery::mock(DelegatableUserInterface::class);
    $this->target->shouldReceive('getDelegatableIdentifier')->andReturn(2);

    $this->role = Mockery::mock(RoleInterface::class);
    $this->role->shouldReceive('getRoleIdentifier')->andReturn(10);
    $this->role->shouldReceive('getRoleName')->andReturn('admin');

    $this->permission = Mockery::mock(PermissionInterface::class);
    $this->permission->shouldReceive('getPermissionIdentifier')->andReturn(20);
    $this->permission->shouldReceive('getPermissionName')->andReturn('edit-posts');
});

describe('LogDelegationAudit', function (): void {
    it('logs role assigned event without throwing', function (): void {
        expect(fn () => $this->audit->logRoleAssigned($this->delegator, $this->target, $this->role))
            ->not->toThrow(Exception::class);
    });

    it('logs role revoked event without throwing', function (): void {
        expect(fn () => $this->audit->logRoleRevoked($this->delegator, $this->target, $this->role))
            ->not->toThrow(Exception::class);
    });

    it('logs permission granted event without throwing', function (): void {
        expect(fn () => $this->audit->logPermissionGranted($this->delegator, $this->target, $this->permission))
            ->not->toThrow(Exception::class);
    });

    it('logs permission revoked event without throwing', function (): void {
        expect(fn () => $this->audit->logPermissionRevoked($this->delegator, $this->target, $this->permission))
            ->not->toThrow(Exception::class);
    });

    it('logs delegation scope changed event without throwing', function (): void {
        $oldScope = DelegationScope::none();
        $newScope = DelegationScope::unlimited([1, 2]);

        expect(fn () => $this->audit->logDelegationScopeChanged($this->delegator, $this->target, $oldScope, $newScope))
            ->not->toThrow(Exception::class);
    });

    it('logs unauthorized attempt event without throwing', function (): void {
        expect(fn () => $this->audit->logUnauthorizedAttempt($this->delegator, 'assign_role', ['role_id' => 10]))
            ->not->toThrow(Exception::class);
    });

    it('logs user created event without throwing', function (): void {
        expect(fn () => $this->audit->logUserCreated($this->delegator, $this->target))
            ->not->toThrow(Exception::class);
    });
});
