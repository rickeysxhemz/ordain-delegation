<?php

declare(strict_types=1);

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;
use Ordain\Delegation\Services\Authorization\RootAdminResolver;

beforeEach(function (): void {
    $this->roleRepository = Mockery::mock(RoleRepositoryInterface::class);
    $this->user = Mockery::mock(DelegatableUserInterface::class);
    $this->user->shouldReceive('getDelegatableIdentifier')->andReturn(1);
});

describe('isRootAdmin', function (): void {
    it('returns false when disabled', function (): void {
        $resolver = new RootAdminResolver(
            roleRepository: $this->roleRepository,
            enabled: false,
            roleIdentifiers: ['root-admin'],
        );

        expect($resolver->isRootAdmin($this->user))->toBeFalse();
    });

    it('returns false when role identifiers is empty', function (): void {
        $resolver = new RootAdminResolver(
            roleRepository: $this->roleRepository,
            enabled: true,
            roleIdentifiers: [],
        );

        expect($resolver->isRootAdmin($this->user))->toBeFalse();
    });

    it('returns true when user has root admin role', function (): void {
        $this->roleRepository->shouldReceive('userHasRoleByName')
            ->with($this->user, 'root-admin', null)
            ->andReturn(true);

        $resolver = new RootAdminResolver(
            roleRepository: $this->roleRepository,
            enabled: true,
            roleIdentifiers: ['root-admin'],
        );

        expect($resolver->isRootAdmin($this->user))->toBeTrue();
    });

    it('returns false when user does not have root admin role', function (): void {
        $this->roleRepository->shouldReceive('userHasRoleByName')
            ->with($this->user, 'root-admin', null)
            ->andReturn(false);

        $resolver = new RootAdminResolver(
            roleRepository: $this->roleRepository,
            enabled: true,
            roleIdentifiers: ['root-admin'],
        );

        expect($resolver->isRootAdmin($this->user))->toBeFalse();
    });

    it('returns false when user has no roles', function (): void {
        $this->roleRepository->shouldReceive('userHasRoleByName')
            ->with($this->user, 'root-admin', null)
            ->andReturn(false);

        $resolver = new RootAdminResolver(
            roleRepository: $this->roleRepository,
            enabled: true,
            roleIdentifiers: ['root-admin'],
        );

        expect($resolver->isRootAdmin($this->user))->toBeFalse();
    });

    it('finds root admin role among multiple roles', function (): void {
        $this->roleRepository->shouldReceive('userHasRoleByName')
            ->with($this->user, 'root-admin', null)
            ->andReturn(true);

        $resolver = new RootAdminResolver(
            roleRepository: $this->roleRepository,
            enabled: true,
            roleIdentifiers: ['root-admin'],
        );

        expect($resolver->isRootAdmin($this->user))->toBeTrue();
    });

    it('checks root admin role with specific guard', function (): void {
        $this->roleRepository->shouldReceive('userHasRoleByName')
            ->with($this->user, 'root-admin', 'admin')
            ->andReturn(true);

        $resolver = new RootAdminResolver(
            roleRepository: $this->roleRepository,
            enabled: true,
            roleIdentifiers: ['root-admin'],
            guard: 'admin',
        );

        expect($resolver->isRootAdmin($this->user))->toBeTrue();
    });

    it('returns false when user has root admin role on different guard', function (): void {
        $this->roleRepository->shouldReceive('userHasRoleByName')
            ->with($this->user, 'root-admin', 'admin')
            ->andReturn(false);

        $resolver = new RootAdminResolver(
            roleRepository: $this->roleRepository,
            enabled: true,
            roleIdentifiers: ['root-admin'],
            guard: 'admin',
        );

        expect($resolver->isRootAdmin($this->user))->toBeFalse();
    });

    it('returns true when user has any of multiple root admin roles', function (): void {
        $this->roleRepository->shouldReceive('userHasRoleByName')
            ->with($this->user, 'root-admin', null)
            ->andReturn(false);
        $this->roleRepository->shouldReceive('userHasRoleByName')
            ->with($this->user, 'super-admin', null)
            ->andReturn(true);

        $resolver = new RootAdminResolver(
            roleRepository: $this->roleRepository,
            enabled: true,
            roleIdentifiers: ['root-admin', 'super-admin'],
        );

        expect($resolver->isRootAdmin($this->user))->toBeTrue();
    });

    it('returns false when user has none of multiple root admin roles', function (): void {
        $this->roleRepository->shouldReceive('userHasRoleByName')
            ->with($this->user, 'root-admin', null)
            ->andReturn(false);
        $this->roleRepository->shouldReceive('userHasRoleByName')
            ->with($this->user, 'super-admin', null)
            ->andReturn(false);

        $resolver = new RootAdminResolver(
            roleRepository: $this->roleRepository,
            enabled: true,
            roleIdentifiers: ['root-admin', 'super-admin'],
        );

        expect($resolver->isRootAdmin($this->user))->toBeFalse();
    });
});
