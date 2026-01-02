<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Audit;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Domain\Enums\DelegationAction;

/**
 * Database-based implementation of audit logging.
 *
 * Stores delegation events in a database table for querying and reporting.
 */
final readonly class DatabaseDelegationAudit extends AbstractDelegationAudit
{
    private const VALID_TABLE_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    /**
     * @param  string  $tableName  The audit log table name
     * @param  AuditContext  $context  Request context for IP/User-Agent
     *
     * @throws InvalidArgumentException If table name contains invalid characters
     */
    public function __construct(
        private string $tableName = 'delegation_audit_logs',
        private AuditContext $context = new AuditContext,
    ) {
        if (! preg_match(self::VALID_TABLE_PATTERN, $this->tableName)) {
            throw new InvalidArgumentException(
                'Invalid table name. Table name must start with a letter or underscore and contain only alphanumeric characters and underscores.',
            );
        }
    }

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
            'metadata' => $this->safeJsonEncode($metadata),
            'ip_address' => $this->context->ipAddress,
            'user_agent' => $this->context->userAgent,
            'created_at' => now(),
        ]);
    }

    /**
     * Safely encode metadata to JSON with fallback on error.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function safeJsonEncode(array $metadata): string
    {
        try {
            return json_encode($metadata, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return json_encode([
                '_encoding_error' => 'Failed to encode metadata',
                '_error_message' => $e->getMessage(),
            ], JSON_THROW_ON_ERROR);
        }
    }
}
