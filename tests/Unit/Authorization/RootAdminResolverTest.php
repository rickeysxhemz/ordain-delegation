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
            roleIdentifier: 'root-admin',
        );

        expect($resolver->isRootAdmin($this->user))->toBeFalse();
    });

    it('returns false when role identifier is null', function (): void {
        $resolver = new RootAdminResolver(
            roleRepository: $this->roleRepository,
            enabled: true,
            roleIdentifier: null,
        );

        expect($resolver->isRootAdmin($this->user))->toBeFalse();
    });

    it('returns true when user has root admin role', function (): void {
        $this->roleRepository->shouldReceive('userHasRoleByName')
            ->with($this->user, 'root-admin')
            ->andReturn(true);

        $resolver = new RootAdminResolver(
            roleRepository: $this->roleRepository,
            enabled: true,
            roleIdentifier: 'root-admin',
        );

        expect($resolver->isRootAdmin($this->user))->toBeTrue();
    });

    it('returns false when user does not have root admin role', function (): void {
        $this->roleRepository->shouldReceive('userHasRoleByName')
            ->with($this->user, 'root-admin')
            ->andReturn(false);

        $resolver = new RootAdminResolver(
            roleRepository: $this->roleRepository,
            enabled: true,
            roleIdentifier: 'root-admin',
        );

        expect($resolver->isRootAdmin($this->user))->toBeFalse();
    });

    it('returns false when user has no roles', function (): void {
        $this->roleRepository->shouldReceive('userHasRoleByName')
            ->with($this->user, 'root-admin')
            ->andReturn(false);

        $resolver = new RootAdminResolver(
            roleRepository: $this->roleRepository,
            enabled: true,
            roleIdentifier: 'root-admin',
        );

        expect($resolver->isRootAdmin($this->user))->toBeFalse();
    });

    it('finds root admin role among multiple roles', function (): void {
        // The new implementation uses userHasRoleByName which does a direct check
        // so this test now just verifies that the method correctly identifies the role
        $this->roleRepository->shouldReceive('userHasRoleByName')
            ->with($this->user, 'root-admin')
            ->andReturn(true);

        $resolver = new RootAdminResolver(
            roleRepository: $this->roleRepository,
            enabled: true,
            roleIdentifier: 'root-admin',
        );

        expect($resolver->isRootAdmin($this->user))->toBeTrue();
    });
});
