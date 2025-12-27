<?php

declare(strict_types=1);

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\Repositories\DelegationRepositoryInterface;
use Ordain\Delegation\Contracts\RootAdminResolverInterface;
use Ordain\Delegation\Contracts\TransactionManagerInterface;
use Ordain\Delegation\Exceptions\UnauthorizedDelegationException;
use Ordain\Delegation\Services\Quota\QuotaManager;

beforeEach(function (): void {
    $this->delegationRepository = Mockery::mock(DelegationRepositoryInterface::class);
    $this->rootAdminResolver = Mockery::mock(RootAdminResolverInterface::class);
    $this->transactionManager = Mockery::mock(TransactionManagerInterface::class);

    $this->quotaManager = new QuotaManager(
        delegationRepository: $this->delegationRepository,
        rootAdminResolver: $this->rootAdminResolver,
        transactionManager: $this->transactionManager,
    );

    $this->delegator = Mockery::mock(DelegatableUserInterface::class);
});

describe('canCreateUsers', function (): void {
    it('returns true for root admin', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(true);

        expect($this->quotaManager->canCreateUsers($this->delegator))->toBeTrue();
    });

    it('returns false when user cannot manage users', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('canManageUsers')->andReturn(false);

        expect($this->quotaManager->canCreateUsers($this->delegator))->toBeFalse();
    });

    it('returns true when user has not reached limit', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('canManageUsers')->andReturn(true);
        $this->delegator->shouldReceive('getMaxManageableUsers')->andReturn(10);

        $this->delegationRepository->shouldReceive('getCreatedUsersCount')
            ->with($this->delegator)
            ->andReturn(5);

        expect($this->quotaManager->canCreateUsers($this->delegator))->toBeTrue();
    });

    it('returns false when user has reached limit', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('canManageUsers')->andReturn(true);
        $this->delegator->shouldReceive('getMaxManageableUsers')->andReturn(10);

        $this->delegationRepository->shouldReceive('getCreatedUsersCount')
            ->with($this->delegator)
            ->andReturn(10);

        expect($this->quotaManager->canCreateUsers($this->delegator))->toBeFalse();
    });

    it('returns true when max users is null (unlimited)', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('canManageUsers')->andReturn(true);
        $this->delegator->shouldReceive('getMaxManageableUsers')->andReturn(null);

        expect($this->quotaManager->canCreateUsers($this->delegator))->toBeTrue();
    });
});

describe('hasReachedLimit', function (): void {
    it('returns false for root admin', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(true);

        expect($this->quotaManager->hasReachedLimit($this->delegator))->toBeFalse();
    });

    it('returns false when max users is null', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('getMaxManageableUsers')->andReturn(null);

        expect($this->quotaManager->hasReachedLimit($this->delegator))->toBeFalse();
    });

    it('returns true when created count equals max', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('getMaxManageableUsers')->andReturn(5);

        $this->delegationRepository->shouldReceive('getCreatedUsersCount')
            ->with($this->delegator)
            ->andReturn(5);

        expect($this->quotaManager->hasReachedLimit($this->delegator))->toBeTrue();
    });

    it('returns true when created count exceeds max', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('getMaxManageableUsers')->andReturn(5);

        $this->delegationRepository->shouldReceive('getCreatedUsersCount')
            ->with($this->delegator)
            ->andReturn(6);

        expect($this->quotaManager->hasReachedLimit($this->delegator))->toBeTrue();
    });
});

describe('getCreatedUsersCount', function (): void {
    it('returns count from repository', function (): void {
        $this->delegationRepository->shouldReceive('getCreatedUsersCount')
            ->with($this->delegator)
            ->andReturn(42);

        expect($this->quotaManager->getCreatedUsersCount($this->delegator))->toBe(42);
    });
});

describe('getRemainingQuota', function (): void {
    it('returns null for root admin', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(true);

        expect($this->quotaManager->getRemainingQuota($this->delegator))->toBeNull();
    });

    it('returns null when max users is null', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('getMaxManageableUsers')->andReturn(null);

        expect($this->quotaManager->getRemainingQuota($this->delegator))->toBeNull();
    });

    it('returns remaining quota correctly', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('getMaxManageableUsers')->andReturn(10);

        $this->delegationRepository->shouldReceive('getCreatedUsersCount')
            ->with($this->delegator)
            ->andReturn(3);

        expect($this->quotaManager->getRemainingQuota($this->delegator))->toBe(7);
    });

    it('returns zero when quota exhausted', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('getMaxManageableUsers')->andReturn(5);

        $this->delegationRepository->shouldReceive('getCreatedUsersCount')
            ->with($this->delegator)
            ->andReturn(10);

        expect($this->quotaManager->getRemainingQuota($this->delegator))->toBe(0);
    });
});

describe('withLock', function (): void {
    it('executes callback within transaction when quota available', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(true);

        $this->transactionManager->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->transactionManager->shouldReceive('lockUserForUpdate')
            ->with($this->delegator)
            ->once();

        $result = $this->quotaManager->withLock($this->delegator, fn () => 'success');

        expect($result)->toBe('success');
    });

    it('throws exception when cannot create users with max limit', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('canManageUsers')->andReturn(true);
        $this->delegator->shouldReceive('getMaxManageableUsers')->andReturn(5);
        $this->delegator->shouldReceive('getDelegatableIdentifier')->andReturn(1);

        $this->delegationRepository->shouldReceive('getCreatedUsersCount')
            ->with($this->delegator)
            ->andReturn(5);

        $this->transactionManager->shouldReceive('transaction')
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->transactionManager->shouldReceive('lockUserForUpdate')
            ->with($this->delegator);

        expect(fn () => $this->quotaManager->withLock($this->delegator, fn () => 'success'))
            ->toThrow(UnauthorizedDelegationException::class);
    });

    it('throws exception when user cannot create users', function (): void {
        $this->rootAdminResolver->shouldReceive('isRootAdmin')
            ->with($this->delegator)
            ->andReturn(false);

        $this->delegator->shouldReceive('canManageUsers')->andReturn(false);
        $this->delegator->shouldReceive('getMaxManageableUsers')->andReturn(null);
        $this->delegator->shouldReceive('getDelegatableIdentifier')->andReturn(1);

        $this->transactionManager->shouldReceive('transaction')
            ->andReturnUsing(fn (callable $callback) => $callback());

        $this->transactionManager->shouldReceive('lockUserForUpdate')
            ->with($this->delegator);

        expect(fn () => $this->quotaManager->withLock($this->delegator, fn () => 'success'))
            ->toThrow(UnauthorizedDelegationException::class);
    });
});
