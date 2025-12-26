<?php

declare(strict_types=1);

namespace Ordain\Delegation\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\PermissionInterface;

/**
 * Event dispatched when a permission is granted to a user via delegation.
 */
final class PermissionGranted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public DelegatableUserInterface $delegator,
        public DelegatableUserInterface $target,
        public PermissionInterface $permission,
    ) {}
}
