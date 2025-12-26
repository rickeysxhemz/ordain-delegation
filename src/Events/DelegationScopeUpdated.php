<?php

declare(strict_types=1);

namespace Ordain\Delegation\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;

/**
 * Event dispatched when a user's delegation scope is updated.
 */
final class DelegationScopeUpdated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public DelegatableUserInterface $user,
        public DelegationScope $oldScope,
        public DelegationScope $newScope,
        public ?DelegatableUserInterface $admin = null,
    ) {}
}
