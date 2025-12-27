<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Audit;

use Illuminate\Support\Facades\Log;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Domain\Enums\DelegationAction;

/**
 * Log-based implementation of audit logging.
 *
 * Writes delegation events to Laravel's logging system.
 */
final readonly class LogDelegationAudit extends AbstractDelegationAudit
{
    public function __construct(
        private string $channel = 'stack',
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

        Log::channel($this->channel)->{$action->severity()}($message, $context);
    }
}
