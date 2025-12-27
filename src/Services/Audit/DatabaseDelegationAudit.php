<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Audit;

use Illuminate\Support\Facades\DB;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Domain\Enums\DelegationAction;

/**
 * Database-based implementation of audit logging.
 *
 * Stores delegation events in a database table for querying and reporting.
 */
final readonly class DatabaseDelegationAudit extends AbstractDelegationAudit
{
    /**
     * @param  string  $tableName  The audit log table name
     * @param  AuditContext  $context  Request context for IP/User-Agent
     */
    public function __construct(
        private string $tableName = 'delegation_audit_logs',
        private AuditContext $context = new AuditContext,
    ) {}

    /**
     * Create instance with context from current request.
     */
    public static function withCurrentRequest(string $tableName = 'delegation_audit_logs'): self
    {
        return new self(
            tableName: $tableName,
            context: AuditContext::fromRequest(request()),
        );
    }

    protected function log(
        DelegationAction $action,
        DelegatableUserInterface $performedBy,
        ?DelegatableUserInterface $targetUser,
        array $metadata,
    ): void {
        DB::table($this->tableName)->insert([
            'action' => $action->value,
            'performed_by_id' => $performedBy->getDelegatableIdentifier(),
            'target_user_id' => $targetUser?->getDelegatableIdentifier(),
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'ip_address' => $this->context->ipAddress,
            'user_agent' => $this->context->userAgent,
            'created_at' => now(),
        ]);
    }
}
