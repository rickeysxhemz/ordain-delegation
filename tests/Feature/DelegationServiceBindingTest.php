<?php

declare(strict_types=1);

namespace Ordain\Delegation\Tests\Feature;

use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Services\CachedDelegationService;
use Ordain\Delegation\Services\DelegationService;

describe('DelegationService container binding', function (): void {
    it('resolves the caching decorator when cache is enabled', function (): void {
        $service = $this->app->make(DelegationServiceInterface::class);

        expect($service)->toBeInstanceOf(CachedDelegationService::class);
    });

    it('resolves the plain service when cache is disabled', function (): void {
        config()->set('permission-delegation.cache.enabled', false);
        $this->app->forgetScopedInstances();

        $service = $this->app->make(DelegationServiceInterface::class);

        expect($service)->toBeInstanceOf(DelegationService::class);
    });

    it('aliases the delegation facade accessor to the service interface', function (): void {
        expect($this->app->make('delegation'))->toBe($this->app->make(DelegationServiceInterface::class));
    });
});
