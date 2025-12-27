<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Infrastructure;

use Illuminate\Contracts\Events\Dispatcher;
use Ordain\Delegation\Contracts\EventDispatcherInterface;

/**
 * Laravel event dispatcher wrapper.
 */
final readonly class EventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private Dispatcher $dispatcher,
        private bool $enabled = true,
    ) {}

    public function dispatch(object $event): void
    {
        if ($this->enabled) {
            $this->dispatcher->dispatch($event);
        }
    }
}
