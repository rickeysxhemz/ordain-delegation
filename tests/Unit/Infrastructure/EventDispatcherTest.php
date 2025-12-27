<?php

declare(strict_types=1);

use Illuminate\Contracts\Events\Dispatcher;
use Ordain\Delegation\Services\Infrastructure\EventDispatcher;
use Ordain\Delegation\Services\Infrastructure\NullEventDispatcher;

describe('EventDispatcher', function (): void {
    it('dispatches event when enabled', function (): void {
        $laravelDispatcher = Mockery::mock(Dispatcher::class);
        $event = new stdClass;

        $laravelDispatcher->shouldReceive('dispatch')
            ->with($event)
            ->once();

        $dispatcher = new EventDispatcher(
            dispatcher: $laravelDispatcher,
            enabled: true,
        );

        $dispatcher->dispatch($event);
    });

    it('does not dispatch event when disabled', function (): void {
        $laravelDispatcher = Mockery::mock(Dispatcher::class);
        $event = new stdClass;

        $laravelDispatcher->shouldNotReceive('dispatch');

        $dispatcher = new EventDispatcher(
            dispatcher: $laravelDispatcher,
            enabled: false,
        );

        $dispatcher->dispatch($event);
    });

    it('is enabled by default', function (): void {
        $laravelDispatcher = Mockery::mock(Dispatcher::class);
        $event = new stdClass;

        $laravelDispatcher->shouldReceive('dispatch')
            ->with($event)
            ->once();

        $dispatcher = new EventDispatcher(
            dispatcher: $laravelDispatcher,
        );

        $dispatcher->dispatch($event);
    });
});

describe('NullEventDispatcher', function (): void {
    it('does nothing when dispatch is called', function (): void {
        $dispatcher = new NullEventDispatcher;
        $event = new stdClass;

        // Should not throw any exception
        $dispatcher->dispatch($event);

        expect(true)->toBeTrue();
    });
});
