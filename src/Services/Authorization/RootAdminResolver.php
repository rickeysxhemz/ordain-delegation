<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Authorization;

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;
use Ordain\Delegation\Contracts\RootAdminResolverInterface;

final readonly class RootAdminResolver implements RootAdminResolverInterface
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository,
        private bool $enabled = true,
        private ?string $roleIdentifier = null,
    ) {}

    public function isRootAdmin(DelegatableUserInterface $user): bool
    {
        if (! $this->enabled || $this->roleIdentifier === null) {
            return false;
        }

        // O(1) database query instead of O(n) PHP iteration
        return $this->roleRepository->userHasRoleByName($user, $this->roleIdentifier);
    }
}
