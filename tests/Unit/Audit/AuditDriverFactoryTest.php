<?php

declare(strict_types=1);

use Ordain\Delegation\Contracts\DelegationAuditInterface;
use Ordain\Delegation\Services\Audit\AuditDriverFactory;
use Ordain\Delegation\Services\Audit\DatabaseDelegationAudit;
use Ordain\Delegation\Services\Audit\LogDelegationAudit;
use Ordain\Delegation\Services\Audit\NullDelegationAudit;
use Ordain\Delegation\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->factory = new AuditDriverFactory($this->app);
});

describe('AuditDriverFactory', function (): void {
    it('returns NullDelegationAudit when audit is disabled', function (): void {
        config(['permission-delegation.audit.enabled' => false]);

        $audit = $this->factory->create();

        expect($audit)->toBeInstanceOf(NullDelegationAudit::class);
    });

    it('returns DatabaseDelegationAudit for database driver', function (): void {
        config([
            'permission-delegation.audit.enabled' => true,
            'permission-delegation.audit.driver' => 'database',
            'permission-delegation.tables.delegation_audit_logs' => 'audit_logs',
        ]);

        $audit = $this->factory->create();

        expect($audit)->toBeInstanceOf(DatabaseDelegationAudit::class);
    });

    it('returns LogDelegationAudit for log driver', function (): void {
        config([
            'permission-delegation.audit.enabled' => true,
            'permission-delegation.audit.driver' => 'log',
            'permission-delegation.audit.log_channel' => 'daily',
        ]);

        $audit = $this->factory->create();

        expect($audit)->toBeInstanceOf(LogDelegationAudit::class);
    });

    it('returns NullDelegationAudit for null driver', function (): void {
        config([
            'permission-delegation.audit.enabled' => true,
            'permission-delegation.audit.driver' => 'null',
        ]);

        $audit = $this->factory->create();

        expect($audit)->toBeInstanceOf(NullDelegationAudit::class);
    });

    it('supports custom driver registration via extend', function (): void {
        config([
            'permission-delegation.audit.enabled' => true,
            'permission-delegation.audit.driver' => 'custom',
        ]);

        $customAudit = Mockery::mock(DelegationAuditInterface::class);

        $this->factory->extend('custom', fn () => $customAudit);

        $audit = $this->factory->create();

        expect($audit)->toBe($customAudit);
    });

    it('clears custom drivers', function (): void {
        $this->factory->extend('custom', fn () => new NullDelegationAudit);

        expect($this->factory->hasDriver('custom'))->toBeTrue();

        $this->factory->clearCustomDrivers();

        expect($this->factory->hasDriver('custom'))->toBeFalse();
    });
});
