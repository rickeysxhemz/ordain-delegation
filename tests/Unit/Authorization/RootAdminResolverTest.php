<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;
use Ordain\Delegation\Contracts\RoleInterface;
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
        $role = Mockery::mock(RoleInterface::class);
        $role->shouldReceive('getRoleName')->andReturn('root-admin');

        $this->roleRepository->shouldReceive('getUserRoles')
            ->with($this->user)
            ->andReturn(new Collection([$role]));

        $resolver = new RootAdminResolver(
            roleRepository: $this->roleRepository,
            enabled: true,
            roleIdentifier: 'root-admin',
        );

        expect($resolver->isRootAdmin($this->user))->toBeTrue();
    });

    it('returns false when user does not have root admin role', function (): void {
        $role = Mockery::mock(RoleInterface::class);
        $role->shouldReceive('getRoleName')->andReturn('editor');

        $this->roleRepository->shouldReceive('getUserRoles')
            ->with($this->user)
            ->andReturn(new Collection([$role]));

        $resolver = new RootAdminResolver(
            roleRepository: $this->roleRepository,
            enabled: true,
            roleIdentifier: 'root-admin',
        );

        expect($resolver->isRootAdmin($this->user))->toBeFalse();
    });

    it('returns false when user has no roles', function (): void {
        $this->roleRepository->shouldReceive('getUserRoles')
            ->with($this->user)
            ->andReturn(new Collection([]));

        $resolver = new RootAdminResolver(
            roleRepository: $this->roleRepository,
            enabled: true,
            roleIdentifier: 'root-admin',
        );

        expect($resolver->isRootAdmin($this->user))->toBeFalse();
    });

    it('finds root admin role among multiple roles', function (): void {
        $role1 = Mockery::mock(RoleInterface::class);
        $role1->shouldReceive('getRoleName')->andReturn('editor');

        $role2 = Mockery::mock(RoleInterface::class);
        $role2->shouldReceive('getRoleName')->andReturn('root-admin');

        $this->roleRepository->shouldReceive('getUserRoles')
            ->with($this->user)
            ->andReturn(new Collection([$role1, $role2]));

        $resolver = new RootAdminResolver(
            roleRepository: $this->roleRepository,
            enabled: true,
            roleIdentifier: 'root-admin',
        );

        expect($resolver->isRootAdmin($this->user))->toBeTrue();
    });
});
