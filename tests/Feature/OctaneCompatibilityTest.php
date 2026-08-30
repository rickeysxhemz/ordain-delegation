<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Ordain\Delegation\Adapters\SpatieRoleAdapter;
use Ordain\Delegation\Contracts\DelegationAuditInterface;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Tests\Fixtures\User;
use Ordain\Delegation\View\BladeDirectives;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Octane keeps the application in memory across requests: providers boot once
 * per worker, and between requests the framework flushes scoped instances via
 * forgetScopedInstances(). Anything that captures a scoped service, or a value
 * derived from the request, outlives the request it belongs to.
 *
 * These tests simulate that lifecycle — boot once, then flush and re-resolve —
 * so a regression that only manifests under a long-lived worker still fails
 * here, under the ordinary test runner.
 */
function simulateOctaneRequest(string $ipAddress): void
{
    app()->instance('request', Request::create(
        uri: '/',
        server: ['REMOTE_ADDR' => $ipAddress, 'HTTP_USER_AGENT' => 'Octane'],
    ));
}

function simulateOctaneRequestBoundary(): void
{
    app()->forgetScopedInstances();
}

describe('Octane compatibility', function (): void {
    it('resolves the delegation service per request rather than the one captured at boot', function (): void {
        // Worker boot: directives are registered once for the life of the worker.
        app(BladeDirectives::class)->register();

        $user = User::create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
            'can_manage_users' => true,
        ]);

        $this->actingAs($user);

        expect(Blade::check('canDelegate'))->toBeTrue();

        // Next request in the same worker.
        simulateOctaneRequestBoundary();

        $replacement = Mockery::mock(DelegationServiceInterface::class);
        $replacement->shouldReceive('canCreateUsers')->andReturnFalse();
        $this->app->instance(DelegationServiceInterface::class, $replacement);

        expect(Blade::check('canDelegate'))->toBeFalse(
            'the directive is still holding the service captured when the worker booted',
        );
    });

    it('resolves the role repository per request rather than the one captured at boot', function (): void {
        app(BladeDirectives::class)->register();

        $user = User::create([
            'name' => 'Manager',
            'email' => 'manager2@example.com',
            'can_manage_users' => true,
        ]);

        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $user->assignableRoles()->attach($role->id);

        $this->actingAs($user);

        expect(Blade::check('canAssignRole', 'editor'))->toBeTrue();

        simulateOctaneRequestBoundary();

        $service = Mockery::mock(DelegationServiceInterface::class);
        $service->shouldReceive('canAssignRole')->andReturnFalse();
        $this->app->instance(DelegationServiceInterface::class, $service);

        expect(Blade::check('canAssignRole', 'editor'))->toBeFalse(
            'the directive is still holding the services captured when the worker booted',
        );
    });

    it('rebuilds the audit context per request instead of freezing the first one', function (): void {
        $delegator = User::create(['name' => 'Delegator', 'email' => 'd@example.com']);
        $target = User::create(['name' => 'Target', 'email' => 't@example.com']);
        $role = SpatieRoleAdapter::fromModel(Role::create(['name' => 'admin', 'guard_name' => 'web']));

        config()->set('permission-delegation.audit.driver', 'database');

        simulateOctaneRequestBoundary();
        simulateOctaneRequest('10.0.0.1');
        app(DelegationAuditInterface::class)->logRoleAssigned($delegator, $target, $role);

        simulateOctaneRequestBoundary();
        simulateOctaneRequest('10.0.0.2');
        app(DelegationAuditInterface::class)->logRoleAssigned($delegator, $target, $role);

        $addresses = DB::table('delegation_audit_logs')->orderBy('id')->pluck('ip_address')->all();

        expect($addresses)->toBe(
            ['10.0.0.1', '10.0.0.2'],
            'the audit context was captured once and reused for a later request',
        );
    });

    it('keeps delegation state out of the container between requests', function (): void {
        simulateOctaneRequest('10.0.0.1');
        $first = app(DelegationServiceInterface::class);

        simulateOctaneRequestBoundary();

        simulateOctaneRequest('10.0.0.2');
        $second = app(DelegationServiceInterface::class);

        expect($second)->not->toBe(
            $first,
            'the delegation service survived a request boundary, so it must be bound as scoped',
        );
    });
});
