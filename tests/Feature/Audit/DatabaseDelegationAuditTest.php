<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Ordain\Delegation\Adapters\SpatiePermissionAdapter;
use Ordain\Delegation\Adapters\SpatieRoleAdapter;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;
use Ordain\Delegation\Services\Audit\AuditContext;
use Ordain\Delegation\Services\Audit\DatabaseDelegationAudit;
use Ordain\Delegation\Tests\Fixtures\User;
use ReflectionClass;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->context = new AuditContext('127.0.0.1', 'Test Agent');
    $this->audit = new DatabaseDelegationAudit('delegation_audit_logs', $this->context);

    $this->delegator = User::create([
        'name' => 'Delegator',
        'email' => 'delegator@example.com',
    ]);

    $this->target = User::create([
        'name' => 'Target',
        'email' => 'target@example.com',
    ]);
});

describe('DatabaseDelegationAudit', function (): void {
    it('logs role assigned event', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $roleAdapter = SpatieRoleAdapter::fromModel($role);

        $this->audit->logRoleAssigned($this->delegator, $this->target, $roleAdapter);

        $log = DB::table('delegation_audit_logs')->first();

        expect($log)->not->toBeNull();
        expect($log->action)->toBe('role_assigned');
        expect((int) $log->performed_by_id)->toBe($this->delegator->id);
        expect((int) $log->target_user_id)->toBe($this->target->id);
        expect($log->ip_address)->toBe('127.0.0.1');
        expect($log->user_agent)->toBe('Test Agent');
    });

    it('logs role revoked event', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $roleAdapter = SpatieRoleAdapter::fromModel($role);

        $this->audit->logRoleRevoked($this->delegator, $this->target, $roleAdapter);

        $log = DB::table('delegation_audit_logs')->first();

        expect($log)->not->toBeNull();
        expect($log->action)->toBe('role_revoked');
    });

    it('logs permission granted event', function (): void {
        $permission = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        $permAdapter = SpatiePermissionAdapter::fromModel($permission);

        $this->audit->logPermissionGranted($this->delegator, $this->target, $permAdapter);

        $log = DB::table('delegation_audit_logs')->first();

        expect($log)->not->toBeNull();
        expect($log->action)->toBe('permission_granted');
    });

    it('logs permission revoked event', function (): void {
        $permission = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        $permAdapter = SpatiePermissionAdapter::fromModel($permission);

        $this->audit->logPermissionRevoked($this->delegator, $this->target, $permAdapter);

        $log = DB::table('delegation_audit_logs')->first();

        expect($log)->not->toBeNull();
        expect($log->action)->toBe('permission_revoked');
    });

    it('logs delegation scope changed event', function (): void {
        $oldScope = DelegationScope::none();
        $newScope = DelegationScope::unlimited([1, 2]);

        $changes = [
            'old' => $oldScope->toArray(),
            'new' => $newScope->toArray(),
        ];

        $this->audit->logDelegationScopeChanged($this->delegator, $this->target, $changes);

        $log = DB::table('delegation_audit_logs')->first();

        expect($log)->not->toBeNull();
        expect($log->action)->toBe('scope_updated');
    });

    it('logs unauthorized attempt event', function (): void {
        $this->audit->logUnauthorizedAttempt($this->delegator, 'assign_role', ['role_id' => 1]);

        $log = DB::table('delegation_audit_logs')->first();

        expect($log)->not->toBeNull();
        expect($log->action)->toBe('unauthorized_attempt');
        expect($log->target_user_id)->toBeNull();
    });

    it('logs user created event', function (): void {
        $this->audit->logUserCreated($this->delegator, $this->target);

        $log = DB::table('delegation_audit_logs')->first();

        expect($log)->not->toBeNull();
        expect($log->action)->toBe('user_created');
    });

    it('creates instance with current request context', function (): void {
        $audit = DatabaseDelegationAudit::withCurrentRequest();

        expect($audit)->toBeInstanceOf(DatabaseDelegationAudit::class);
    });

    it('stores metadata as JSON', function (): void {
        $this->audit->logUnauthorizedAttempt($this->delegator, 'assign_role', [
            'role_id' => 1,
            'reason' => 'Not authorized',
        ]);

        $log = DB::table('delegation_audit_logs')->first();
        $metadata = json_decode($log->metadata, true);

        expect($metadata)->toHaveKey('attempted_action');
        expect($metadata['attempted_action'])->toBe('assign_role');
        expect($metadata)->toHaveKey('role_id');
    });

    it('throws exception for invalid table name', function (): void {
        new DatabaseDelegationAudit('table-with-dashes', $this->context);
    })->throws(InvalidArgumentException::class, 'Invalid table name');

    it('throws exception for table name starting with number', function (): void {
        new DatabaseDelegationAudit('123table', $this->context);
    })->throws(InvalidArgumentException::class, 'Invalid table name');

    it('throws exception for table name with special characters', function (): void {
        new DatabaseDelegationAudit('table.name', $this->context);
    })->throws(InvalidArgumentException::class, 'Invalid table name');

    it('accepts valid table names', function (): void {
        $audit1 = new DatabaseDelegationAudit('valid_table_name', $this->context);
        $audit2 = new DatabaseDelegationAudit('_underscore_start', $this->context);
        $audit3 = new DatabaseDelegationAudit('TableName123', $this->context);

        expect($audit1)->toBeInstanceOf(DatabaseDelegationAudit::class);
        expect($audit2)->toBeInstanceOf(DatabaseDelegationAudit::class);
        expect($audit3)->toBeInstanceOf(DatabaseDelegationAudit::class);
    });

    it('handles non-serializable metadata gracefully', function (): void {
        // Create a resource that cannot be JSON encoded
        $resource = fopen('php://memory', 'r');

        // Use reflection to call safeJsonEncode with invalid data
        $reflection = new ReflectionClass($this->audit);
        $method = $reflection->getMethod('safeJsonEncode');
        $method->setAccessible(true);

        $result = $method->invoke($this->audit, ['resource' => $resource]);
        $decoded = json_decode($result, true);

        fclose($resource);

        expect($decoded)->toHaveKey('_encoding_error');
        expect($decoded['_encoding_error'])->toBe('Failed to encode metadata');
    });
});
