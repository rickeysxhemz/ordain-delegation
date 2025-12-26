<?php

declare(strict_types=1);

namespace Ordain\Delegation\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Ordain\Delegation\Contracts\DelegatableUserInterface;

/**
 * Event dispatched when an unauthorized delegation is attempted.
 */
final class UnauthorizedDelegationAttempted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public DelegatableUserInterface $delegator,
        public string $action,
        public array $context = [],
    ) {}
}
