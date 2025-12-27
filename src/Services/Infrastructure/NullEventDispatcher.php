<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Infrastructure;

use Ordain\Delegation\Contracts\EventDispatcherInterface;

/**
 * Null implementation that discards all events.
 */
final readonly class NullEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(object $event): void
    {
        // Events are disabled - do nothing
    }
}
