<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Audit;

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Domain\Enums\DelegationAction;
use Psr\Log\LoggerInterface;

/**
 * Log-based implementation of audit logging.
 *
 * Writes delegation events to Laravel's logging system.
 */
final readonly class LogDelegationAudit extends AbstractDelegationAudit
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    protected function log(
        DelegationAction $action,
        DelegatableUserInterface $performedBy,
        ?DelegatableUserInterface $targetUser,
        array $metadata,
    ): void {
        $context = array_merge([
            'delegator_id' => $performedBy->getDelegatableIdentifier(),
        ], $metadata);

        if ($targetUser !== null) {
            $context['target_id'] = $targetUser->getDelegatableIdentifier();
        }

        $message = sprintf('[Delegation] %s', $action->label());

        $this->logger->{$action->severity()}($message, $context);
    }
}
