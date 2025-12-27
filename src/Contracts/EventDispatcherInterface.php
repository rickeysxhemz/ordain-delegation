<?php

declare(strict_types=1);

namespace Ordain\Delegation\Contracts;

/**
 * Abstraction for event dispatching.
 */
interface EventDispatcherInterface
{
    /**
     * Dispatch an event to listeners.
     */
    public function dispatch(object $event): void;
}
