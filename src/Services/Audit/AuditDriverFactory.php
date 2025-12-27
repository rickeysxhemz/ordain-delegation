<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Audit;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Ordain\Delegation\Contracts\DelegationAuditInterface;

/**
 * Factory for creating audit driver instances based on configuration.
 */
final class AuditDriverFactory
{
    /**
     * @throws BindingResolutionException
     */
    public static function create(Application $app): DelegationAuditInterface
    {
        if (! config('permission-delegation.audit.enabled', true)) {
            return new NullDelegationAudit;
        }

        /** @var string $driver */
        $driver = config('permission-delegation.audit.driver', 'database');

        return match ($driver) {
            'database' => DatabaseDelegationAudit::withCurrentRequest(
                tableName: (string) config('permission-delegation.tables.delegation_audit_logs', 'delegation_audit_logs'),
            ),
            'log' => new LogDelegationAudit(
                channel: (string) config('permission-delegation.audit.log_channel', 'stack'),
            ),
            'null' => new NullDelegationAudit,
            default => $app->make($driver),
        };
    }
}
