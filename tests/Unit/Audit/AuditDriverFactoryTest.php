<?php

declare(strict_types=1);

use Ordain\Delegation\Services\Audit\AuditDriverFactory;
use Ordain\Delegation\Services\Audit\DatabaseDelegationAudit;
use Ordain\Delegation\Services\Audit\LogDelegationAudit;
use Ordain\Delegation\Services\Audit\NullDelegationAudit;
use Ordain\Delegation\Tests\TestCase;

uses(TestCase::class);

describe('AuditDriverFactory', function (): void {
    it('returns NullDelegationAudit when audit is disabled', function (): void {
        config(['permission-delegation.audit.enabled' => false]);

        $audit = AuditDriverFactory::create($this->app);

        expect($audit)->toBeInstanceOf(NullDelegationAudit::class);
    });

    it('returns DatabaseDelegationAudit for database driver', function (): void {
        config([
            'permission-delegation.audit.enabled' => true,
            'permission-delegation.audit.driver' => 'database',
            'permission-delegation.tables.delegation_audit_logs' => 'audit_logs',
        ]);

        $audit = AuditDriverFactory::create($this->app);

        expect($audit)->toBeInstanceOf(DatabaseDelegationAudit::class);
    });

    it('returns LogDelegationAudit for log driver', function (): void {
        config([
            'permission-delegation.audit.enabled' => true,
            'permission-delegation.audit.driver' => 'log',
            'permission-delegation.audit.log_channel' => 'daily',
        ]);

        $audit = AuditDriverFactory::create($this->app);

        expect($audit)->toBeInstanceOf(LogDelegationAudit::class);
    });

    it('returns NullDelegationAudit for null driver', function (): void {
        config([
            'permission-delegation.audit.enabled' => true,
            'permission-delegation.audit.driver' => 'null',
        ]);

        $audit = AuditDriverFactory::create($this->app);

        expect($audit)->toBeInstanceOf(NullDelegationAudit::class);
    });
});
