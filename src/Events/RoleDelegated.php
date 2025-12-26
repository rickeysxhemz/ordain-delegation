<?php

declare(strict_types=1);

namespace Ordain\Delegation\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\RoleInterface;

/**
 * Event dispatched when a role is delegated to a user.
 */
final class RoleDelegated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public DelegatableUserInterface $delegator,
        public DelegatableUserInterface $target,
        public RoleInterface $role,
    ) {}
}
